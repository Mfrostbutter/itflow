<?php

// GET /api/v2/agreements/read.php
// Extension-schema feature (agreements table is not stock ITFlow).
// Filters: client scope, status, active (bool), expiring_before (agreement_end).

$REQUIRE_METHOD = 'GET';
require_once __DIR__ . '/../_lib/bootstrap.php';

v2_require_table($mysqli, 'agreements', 'agreements.read');

$where = [];
$params = [];
$types = '';

if ($v2['client_id'] !== null) {
    $where[] = 'agreement_client_id = ?';
    $params[] = $v2['client_id'];
    $types .= 'i';
}

$status = v2_p_str($_GET, 'status');
if ($status !== null) {
    $where[] = 'agreement_status = ?';
    $params[] = $status;
    $types .= 's';
}

$active = v2_p_str($_GET, 'active');
if ($active !== null) {
    if (!in_array(strtolower($active), ['true', 'false', '0', '1'], true)) {
        api_fail(422, 'VALIDATION_FAILED', 'active must be a boolean.', 'active');
    }
    $where[] = in_array(strtolower($active), ['true', '1'], true)
        ? "agreement_status = 'Active'"
        : "agreement_status <> 'Active'";
}

$expiring_before = v2_p_date($_GET, 'expiring_before');
if ($expiring_before !== null) {
    $where[] = 'agreement_end < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $expiring_before;
    $types .= 's';
}

$page = v2_p_page($_GET);
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS total FROM agreements $where_sql");
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $mysqli,
    "SELECT agreements.*, clients.client_name AS agreement_client_name
     FROM agreements
     LEFT JOIN clients ON agreement_client_id = client_id
     $where_sql
     ORDER BY agreement_end ASC, agreement_id ASC
     LIMIT ? OFFSET ?"
);
$params[] = $page['limit'];
$params[] = $page['offset'];
$types .= 'ii';
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
}
mysqli_stmt_close($stmt);

api_ok($rows, [
    'count'    => count($rows),
    'page'     => $page['page'],
    'per_page' => $page['per_page'],
    'total'    => $total,
]);
