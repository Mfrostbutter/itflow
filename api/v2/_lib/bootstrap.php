<?php

// api/v2 bootstrap: envelope, method gate, JSON body, auth, client scope.
// Every endpoint sets $REQUIRE_METHOD ('GET'|'POST') then requires this file.
// On success: $v2 = [key_id, key_name, key_client_id, client_id] and $body (POST).

require_once __DIR__ . '/respond.php';
require_once __DIR__ . '/params.php';

require_once dirname(__DIR__, 3) . '/functions.php';
require_once dirname(__DIR__, 3) . '/config.php';

header('Content-Type: application/json');

// Fork identity - bump fork_api on additive releases, upstream_base on rebases
define('FORK_API_VERSION', '2.0.0');
define('FORK_UPSTREAM_BASE', '26.07.1 (master@698135d)');
define('FORK_FEATURES', []);
define('FORK_V1_EXTENSIONS', ['tickets/update', 'tickets/reply']);

// Any uncaught error (incl. mysqli exceptions) -> clean 500, details to error_log only
set_exception_handler(function ($e) {
    error_log('API v2 error: ' . $e->getMessage());
    api_fail(500, 'INTERNAL', 'Internal server error.');
});

// Method gate
$REQUIRE_METHOD = $REQUIRE_METHOD ?? 'GET';
if ($_SERVER['REQUEST_METHOD'] !== $REQUIRE_METHOD) {
    api_fail(405, 'METHOD_NOT_ALLOWED', "This endpoint accepts $REQUIRE_METHOD only.");
}

// Request context for logAction() parity with v1
$ip = sanitizeInput(getIP());
$user_agent = sanitizeInput($_SERVER['HTTP_USER_AGENT'] ?? '');
$session_ip = $ip;
$session_user_agent = $user_agent;

// Body: JSON only. Empty body -> []. Anything unparseable -> 400.
$body = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            api_fail(400, 'MALFORMED_JSON', 'Request body must be a JSON object.');
        }
        $body = $decoded;
    }
}

// Bearer header, tolerant of SAPI differences in how Authorization surfaces
function v2_bearer_token(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($hdr === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $hdr = $value;
                break;
            }
        }
    }
    if (preg_match('/^Bearer\s+(\S+)$/i', $hdr, $m)) {
        return $m[1];
    }
    return null;
}

// Auth: Bearer preferred, api_key param accepted for v1 back-compat
$api_key = v2_bearer_token();
if ($api_key === null && isset($_GET['api_key'])) {
    $api_key = (string) $_GET['api_key'];
}
if ($api_key === null && isset($body['api_key'])) {
    $api_key = (string) $body['api_key'];
}
if ($api_key === null || $api_key === '') {
    api_fail(401, 'AUTH_INVALID', 'No API key provided. Use Authorization: Bearer <key>.');
}

// Key lookup: prepared statement, result consumed and closed here.
// No SQL handle leaves this scope (see v1 read_output.php phantom-success bug).
$stmt = mysqli_prepare(
    $mysqli,
    "SELECT api_key_id, api_key_name, api_key_client_id, (api_key_expire > NOW()) AS api_key_valid
     FROM api_keys WHERE api_key_secret = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $api_key);
mysqli_stmt_execute($stmt);
$key_result = mysqli_stmt_get_result($stmt);
$key_row = mysqli_fetch_assoc($key_result);
mysqli_stmt_close($stmt);
unset($key_result);

if (!$key_row || !(int) $key_row['api_key_valid']) {
    // Audit parity with v1 auth failures
    $url_path = sanitizeInput(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    $log_stmt = mysqli_prepare(
        $mysqli,
        "INSERT INTO logs SET log_type = 'API', log_action = 'Failed',
         log_description = ?, log_ip = ?, log_user_agent = ?"
    );
    $log_description = ($key_row ? 'Expired key' : 'Incorrect key') . " (endpoint: $url_path)";
    mysqli_stmt_bind_param($log_stmt, 'sss', $log_description, $ip, $user_agent);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);

    if ($key_row) {
        api_fail(401, 'AUTH_EXPIRED', 'API key has expired.');
    }
    api_fail(401, 'AUTH_INVALID', 'API key is invalid.');
}

// Client scope. v1 semantics, made explicit:
//   scoped key: locked to its client; a mismatched explicit client_id -> 403
//   all-clients key: GET may narrow via client_id (else unscoped);
//                    POST must name client_id -> 422
$v2 = [
    'key_id'        => (int) $key_row['api_key_id'],
    'key_name'      => htmlentities($key_row['api_key_name']),
    'key_client_id' => (int) $key_row['api_key_client_id'],
    'client_id'     => null,
];

$param_src = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : $body;
$requested_client_id = v2_p_int($param_src, 'client_id');

if ($v2['key_client_id'] > 0) {
    if ($requested_client_id !== null && $requested_client_id !== $v2['key_client_id']) {
        api_fail(403, 'SCOPE_DENIED', 'This API key is scoped to a different client.', 'client_id');
    }
    $v2['client_id'] = $v2['key_client_id'];
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $requested_client_id === null) {
        api_fail(422, 'VALIDATION_FAILED', 'client_id is required for writes with an all-clients key.', 'client_id');
    }
    $v2['client_id'] = $requested_client_id;
}

unset($key_row, $api_key, $param_src, $requested_client_id);
