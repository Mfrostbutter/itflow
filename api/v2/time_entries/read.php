<?php

// GET /api/v2/time_entries/read.php
// Extension-schema feature (time_entries table is not stock ITFlow).
// Filters: client scope, ticket_id, tech_id, billable, date_from/date_to.

$REQUIRE_METHOD = 'GET';
require_once __DIR__ . '/../_lib/bootstrap.php';

v2_require_table($mysqli, 'time_entries', 'time_entries.read');

$where = [];
$params = [];
$types = '';

if ($v2['client_id'] !== null) {
    $where[] = 'entry_client_id = ?';
    $params[] = $v2['client_id'];
    $types .= 'i';
}

$ticket_id = v2_p_int($_GET, 'ticket_id');
if ($ticket_id !== null) {
    $where[] = 'entry_ticket_id = ?';
    $params[] = $ticket_id;
    $types .= 'i';
}

$tech_id = v2_p_int($_GET, 'tech_id');
if ($tech_id !== null) {
    $where[] = 'entry_tech_id = ?';
    $params[] = $tech_id;
    $types .= 'i';
}

$billable = v2_p_str($_GET, 'billable');
if ($billable !== null) {
    if (!in_array(strtolower($billable), ['true', 'false', '0', '1'], true)) {
        api_fail(422, 'VALIDATION_FAILED', 'billable must be a boolean.', 'billable');
    }
    $where[] = 'entry_billable = ?';
    $params[] = in_array(strtolower($billable), ['true', '1'], true) ? 1 : 0;
    $types .= 'i';
}

$date_from = v2_p_date($_GET, 'date_from');
if ($date_from !== null) {
    $where[] = 'entry_date >= ?';
    $params[] = $date_from;
    $types .= 's';
}
$date_to = v2_p_date($_GET, 'date_to');
if ($date_to !== null) {
    $where[] = 'entry_date <= ?';
    $params[] = $date_to;
    $types .= 's';
}

$page = v2_p_page($_GET);
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS total FROM time_entries $where_sql");
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $mysqli,
    'SELECT time_entries.*, users.user_name AS entry_tech_name
     FROM time_entries
     LEFT JOIN users ON entry_tech_id = user_id
     ' . $where_sql . '
     ORDER BY entry_date DESC, entry_id DESC
     LIMIT ? OFFSET ?'
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
