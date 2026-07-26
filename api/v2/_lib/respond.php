<?php

// api/v2 response envelope: real HTTP status codes, boolean success.
// api_ok / api_fail both terminate the request.

function api_ok($data, ?array $meta = null, int $status = 200): void
{
    $out = ['success' => true, 'data' => $data];
    if ($meta !== null) {
        $out['meta'] = $meta;
    }
    http_response_code($status);
    echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}

function api_fail(int $status, string $code, string $message, ?string $field = null): void
{
    $error = ['code' => $code, 'message' => $message];
    if ($field !== null) {
        $error['field'] = $field;
    }
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit();
}
