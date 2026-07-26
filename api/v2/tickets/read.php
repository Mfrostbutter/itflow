<?php

// GET /api/v2/tickets/read.php
// Filtered ticket read. Filters: ticket_id, client_id, status (name),
// assigned_to, contact_id, created_after/before, updated_after/before,
// q (subject+details), sort (created_at|updated_at|priority), order, pagination.
// Returns tickets with status name and assigned tech name joined in.

$REQUIRE_METHOD = 'GET';
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../_lib/tickets.php';

$where = [];
$params = [];
$types = '';

if ($v2['client_id'] !== null) {
    $where[] = 'ticket_client_id = ?';
    $params[] = $v2['client_id'];
    $types .= 'i';
}

$ticket_id = v2_p_int($_GET, 'ticket_id');
if ($ticket_id !== null) {
    $where[] = 'ticket_id = ?';
    $params[] = $ticket_id;
    $types .= 'i';
}

// Status filters by NAME, resolved against ticket_statuses (case-insensitive)
$status = v2_p_str($_GET, 'status');
if ($status !== null) {
    $where[] = 'ticket_status = ?';
    $params[] = v2_ticket_status_id($mysqli, $status);
    $types .= 'i';
}

$assigned_to = v2_p_int($_GET, 'assigned_to');
if ($assigned_to !== null) {
    $where[] = 'ticket_assigned_to = ?';
    $params[] = $assigned_to;
    $types .= 'i';
}

$contact_id = v2_p_int($_GET, 'contact_id');
if ($contact_id !== null) {
    $where[] = 'ticket_contact_id = ?';
    $params[] = $contact_id;
    $types .= 'i';
}

// Date windows: after = inclusive start of day, before = inclusive end of day
foreach ([
    'created_after'  => ['ticket_created_at', '>= ?'],
    'created_before' => ['ticket_created_at', '< DATE_ADD(?, INTERVAL 1 DAY)'],
    'updated_after'  => ['ticket_updated_at', '>= ?'],
    'updated_before' => ['ticket_updated_at', '< DATE_ADD(?, INTERVAL 1 DAY)'],
] as $key => [$column, $op]) {
    $date = v2_p_date($_GET, $key);
    if ($date !== null) {
        $where[] = "$column $op";
        $params[] = $date;
        $types .= 's';
    }
}

$q = v2_p_str($_GET, 'q');
if ($q !== null) {
    $where[] = "(ticket_subject LIKE CONCAT('%', ?, '%') OR ticket_details LIKE CONCAT('%', ?, '%'))";
    $params[] = $q;
    $params[] = $q;
    $types .= 'ss';
}

// Sort allowlist; priority sorts by rank, not alphabetically
$sort = v2_p_enum($_GET, 'sort', ['created_at', 'updated_at', 'priority'], 'created_at');
$order = v2_p_enum($_GET, 'order', ['asc', 'desc'], 'desc');
$order_by = [
    'created_at' => 'ticket_created_at',
    'updated_at' => 'ticket_updated_at',
    'priority'   => "FIELD(ticket_priority, 'Low', 'Medium', 'High')",
][$sort] . ' ' . strtoupper($order) . ', ticket_id ' . strtoupper($order);

$page = v2_p_page($_GET);
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Total across the filter (own COUNT, not affected rows or leaked handles)
$stmt = mysqli_prepare($mysqli, "SELECT COUNT(*) AS total FROM tickets $where_sql");
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);

// Page of rows
$stmt = mysqli_prepare(
    $mysqli,
    "SELECT tickets.*, ticket_statuses.ticket_status_name, users.user_name AS ticket_assigned_to_name
     FROM tickets
     LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
     LEFT JOIN users ON ticket_assigned_to = user_id
     $where_sql
     ORDER BY $order_by
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
