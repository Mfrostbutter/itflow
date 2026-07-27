<?php

// GET /api/v2/ticket_replies/read.php
// Ticket thread read in thread order. Scoping rides the parent ticket's
// client. Archived replies are excluded. Filters: ticket_id, type, pagination.

$REQUIRE_METHOD = 'GET';
require_once __DIR__ . '/../_lib/bootstrap.php';

$where = ['ticket_reply_archived_at IS NULL'];
$params = [];
$types = '';

if ($v2['client_id'] !== null) {
    $where[] = 'tickets.ticket_client_id = ?';
    $params[] = $v2['client_id'];
    $types .= 'i';
}

$ticket_id = v2_p_int($_GET, 'ticket_id');
if ($ticket_id !== null) {
    $where[] = 'ticket_reply_ticket_id = ?';
    $params[] = $ticket_id;
    $types .= 'i';
}

$type = v2_p_enum($_GET, 'type', ['public', 'internal']);
if ($type !== null) {
    $where[] = 'ticket_reply_type = ?';
    $params[] = ucfirst($type);
    $types .= 's';
}

$page = v2_p_page($_GET);
$where_sql = 'WHERE ' . implode(' AND ', $where);

$stmt = mysqli_prepare(
    $mysqli,
    "SELECT COUNT(*) AS total
     FROM ticket_replies
     LEFT JOIN tickets ON ticket_reply_ticket_id = ticket_id
     $where_sql"
);
if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare(
    $mysqli,
    "SELECT ticket_replies.*, users.user_name AS ticket_reply_by_name,
            tickets.ticket_client_id
     FROM ticket_replies
     LEFT JOIN tickets ON ticket_reply_ticket_id = ticket_id
     LEFT JOIN users ON ticket_reply_by = user_id
     $where_sql
     ORDER BY ticket_reply_created_at ASC, ticket_reply_id ASC
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
