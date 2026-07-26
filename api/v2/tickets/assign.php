<?php

// POST /api/v2/tickets/assign.php
// Assign (or unassign with 0) a ticket. Assignment flips status New -> Open,
// matching agent UI behavior. Returns the full updated ticket.

$REQUIRE_METHOD = 'POST';
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../_lib/tickets.php';

$ticket_id = v2_p_int($body, 'ticket_id');
if ($ticket_id === null) {
    api_fail(422, 'VALIDATION_FAILED', 'ticket_id is required.', 'ticket_id');
}
$assigned_to = v2_p_int($body, 'assigned_to');
if ($assigned_to === null) {
    api_fail(422, 'VALIDATION_FAILED', 'assigned_to is required (0 to unassign).', 'assigned_to');
}

$ticket = v2_ticket_fetch($mysqli, $ticket_id, $v2['client_id']);
if ($ticket === null) {
    api_fail(404, 'NOT_FOUND', "Ticket $ticket_id not found for this client.");
}

if ($assigned_to !== 0) {
    v2_require_active_user($mysqli, $assigned_to, 'assigned_to');
}

$stmt = mysqli_prepare(
    $mysqli,
    'UPDATE tickets SET ticket_assigned_to = ?, ticket_updated_at = NOW()
     WHERE ticket_id = ? AND ticket_client_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'iii', $assigned_to, $ticket_id, $v2['client_id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Status flip on assignment only, and only from New (statuses are configurable: resolve by name)
if ($assigned_to !== 0 && strcasecmp($ticket['ticket_status_name'] ?? '', 'New') === 0) {
    $open_stmt = mysqli_prepare($mysqli, "SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name = 'Open' LIMIT 1");
    mysqli_stmt_execute($open_stmt);
    $open_row = mysqli_fetch_assoc(mysqli_stmt_get_result($open_stmt));
    mysqli_stmt_close($open_stmt);
    if ($open_row) {
        $open_id = (int) $open_row['ticket_status_id'];
        $flip_stmt = mysqli_prepare(
            $mysqli,
            'UPDATE tickets SET ticket_status = ? WHERE ticket_id = ? AND ticket_client_id = ? LIMIT 1'
        );
        mysqli_stmt_bind_param($flip_stmt, 'iii', $open_id, $ticket_id, $v2['client_id']);
        mysqli_stmt_execute($flip_stmt);
        mysqli_stmt_close($flip_stmt);
    }
}

$updated = v2_ticket_fetch($mysqli, $ticket_id, $v2['client_id']);

logAction('Ticket', 'Assign', $updated['ticket_subject'] . ' via API v2 (' . $v2['key_name'] . ')', $v2['client_id'], $ticket_id);
logAction('API', 'Success', 'Assigned ticket ' . $updated['ticket_subject'] . ' via API v2 (' . $v2['key_name'] . ')', $v2['client_id']);

api_ok($updated);
