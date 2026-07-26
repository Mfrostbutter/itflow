<?php

// POST /api/v2/tickets/reply.php
// Append a reply to a ticket thread. Side effects match the agent path:
// reply insert, ticket touch, first-response stamp on the first Public reply,
// assigned-tech notification. NEVER emails the client. Returns the created
// reply row (201).

$REQUIRE_METHOD = 'POST';
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../_lib/tickets.php';

$ticket_id = v2_p_int($body, 'ticket_id');
if ($ticket_id === null) {
    api_fail(422, 'VALIDATION_FAILED', 'ticket_id is required.', 'ticket_id');
}

// Reply body holds HTML (thread content), stored as-is via prepared statement
$reply = v2_p_str($body, 'reply');
if ($reply === null) {
    api_fail(422, 'VALIDATION_FAILED', 'reply is required.', 'reply');
}

$type = v2_p_enum($body, 'type', ['public', 'internal'], 'internal');
$reply_type = $type === 'public' ? 'Public' : 'Internal';

// Time worked HH:MM:SS; defaults to none so it does not skew tech time reporting
$time_worked = v2_p_str($body, 'time_worked', '00:00:00');
if (!preg_match('/^\d{1,3}:[0-5]\d:[0-5]\d$/', $time_worked)) {
    api_fail(422, 'VALIDATION_FAILED', 'time_worked must be HH:MM:SS.', 'time_worked');
}

// Attribution: API has no session user; 0 unless a tech id is named
$reply_by = v2_p_int($body, 'reply_by', 0);
if ($reply_by !== 0) {
    v2_require_active_user($mysqli, $reply_by, 'reply_by');
}

$ticket = v2_ticket_fetch($mysqli, $ticket_id, $v2['client_id']);
if ($ticket === null) {
    api_fail(404, 'NOT_FOUND', "Ticket $ticket_id not found for this client.");
}

// Insert reply
$stmt = mysqli_prepare(
    $mysqli,
    'INSERT INTO ticket_replies
     SET ticket_reply = ?, ticket_reply_type = ?, ticket_reply_time_worked = ?,
         ticket_reply_by = ?, ticket_reply_ticket_id = ?'
);
mysqli_stmt_bind_param($stmt, 'sssii', $reply, $reply_type, $time_worked, $reply_by, $ticket_id);
mysqli_stmt_execute($stmt);
$reply_id = mysqli_insert_id($mysqli);
mysqli_stmt_close($stmt);

// Touch the ticket
$stmt = mysqli_prepare($mysqli, 'UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $ticket_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// First-response stamp: only the first Public reply counts as a response
if (empty($ticket['ticket_first_response_at']) && $reply_type === 'Public') {
    $stmt = mysqli_prepare($mysqli, 'UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $ticket_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Notify the assigned tech
$assigned_to = (int) $ticket['ticket_assigned_to'];
if ($assigned_to !== 0) {
    $prefix_number = $ticket['ticket_prefix'] . $ticket['ticket_number'];
    $notification = 'API v2 (' . $v2['key_name'] . ') replied to Ticket ' . $prefix_number
        . ' - Subject: ' . $ticket['ticket_subject'] . ' that is assigned to you';
    $action = '/agent/ticket.php?ticket_id=' . $ticket_id . '&client_id=' . $v2['client_id'];
    $stmt = mysqli_prepare(
        $mysqli,
        "INSERT INTO notifications
         SET notification_type = 'Ticket', notification = ?, notification_action = ?,
             notification_client_id = ?, notification_user_id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ssii', $notification, $action, $v2['client_id'], $assigned_to);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

logAction('Ticket', 'Reply', $ticket['ticket_prefix'] . $ticket['ticket_number'] . " ticket via API v2 (" . $v2['key_name'] . ") and was a $reply_type reply", $v2['client_id'], $ticket_id);
logAction('API', 'Success', 'Replied to ticket ' . $ticket['ticket_prefix'] . $ticket['ticket_number'] . ' via API v2 (' . $v2['key_name'] . ')', $v2['client_id']);

// Return the created row with author name joined
$stmt = mysqli_prepare(
    $mysqli,
    'SELECT ticket_replies.*, users.user_name AS ticket_reply_by_name
     FROM ticket_replies LEFT JOIN users ON ticket_reply_by = user_id
     WHERE ticket_reply_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $reply_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

api_ok($row, null, 201);
