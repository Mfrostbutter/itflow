<?php

// POST /api/v2/tickets/update.php
// Partial ticket update: only allowlisted fields present in the body change.
// Unknown fields -> 422. Missing ticket in scope -> 404. Cross-client
// contact/asset references -> 422. Returns the full updated ticket.

$REQUIRE_METHOD = 'POST';
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../_lib/tickets.php';

// body field -> [column, handler]
$allowlist = [
    'subject'              => 'ticket_subject',
    'details'              => 'ticket_details',
    'status'               => 'ticket_status',
    'priority'             => 'ticket_priority',
    'assigned_to'          => 'ticket_assigned_to',
    'contact_id'           => 'ticket_contact_id',
    'asset_id'             => 'ticket_asset_id',
    'billable'             => 'ticket_billable',
    'vendor_ticket_number' => 'ticket_vendor_ticket_number',
    'vendor_id'            => 'ticket_vendor_id',
];
$passthrough = ['ticket_id', 'client_id', 'api_key'];

foreach (array_keys($body) as $key) {
    if (!isset($allowlist[$key]) && !in_array($key, $passthrough, true)) {
        api_fail(422, 'VALIDATION_FAILED', "Unknown field: $key.", $key);
    }
}

$ticket_id = v2_p_int($body, 'ticket_id');
if ($ticket_id === null) {
    api_fail(422, 'VALIDATION_FAILED', 'ticket_id is required.', 'ticket_id');
}

$ticket = v2_ticket_fetch($mysqli, $ticket_id, $v2['client_id']);
if ($ticket === null) {
    api_fail(404, 'NOT_FOUND', "Ticket $ticket_id not found for this client.");
}

$set = [];
$params = [];
$types = '';

if (array_key_exists('subject', $body)) {
    $val = v2_p_str($body, 'subject');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'subject cannot be empty.', 'subject');
    }
    $set[] = 'ticket_subject = ?';
    $params[] = $val;
    $types .= 's';
}

if (array_key_exists('details', $body)) {
    $val = v2_p_str($body, 'details');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'details cannot be empty.', 'details');
    }
    $set[] = 'ticket_details = ?';
    $params[] = $val;
    $types .= 's';
}

if (array_key_exists('status', $body)) {
    $val = v2_p_str($body, 'status');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'status cannot be empty.', 'status');
    }
    $set[] = 'ticket_status = ?';
    $params[] = v2_ticket_status_id($mysqli, $val);
    $types .= 'i';
}

if (array_key_exists('priority', $body)) {
    $set[] = 'ticket_priority = ?';
    $params[] = v2_p_enum($body, 'priority', ['Low', 'Medium', 'High']);
    $types .= 's';
}

if (array_key_exists('assigned_to', $body)) {
    $val = v2_p_int($body, 'assigned_to');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'assigned_to must be an integer (0 to unassign).', 'assigned_to');
    }
    if ($val !== 0) {
        v2_require_active_user($mysqli, $val, 'assigned_to');
    }
    $set[] = 'ticket_assigned_to = ?';
    $params[] = $val;
    $types .= 'i';
}

if (array_key_exists('contact_id', $body)) {
    $val = v2_p_int($body, 'contact_id');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'contact_id must be an integer (0 to clear).', 'contact_id');
    }
    if ($val !== 0) {
        v2_require_in_client($mysqli, 'contacts', 'contact_id', 'contact_client_id', $val, $v2['client_id'], 'contact_id');
    }
    $set[] = 'ticket_contact_id = ?';
    $params[] = $val;
    $types .= 'i';
}

if (array_key_exists('asset_id', $body)) {
    $val = v2_p_int($body, 'asset_id');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'asset_id must be an integer (0 to clear).', 'asset_id');
    }
    if ($val !== 0) {
        v2_require_in_client($mysqli, 'assets', 'asset_id', 'asset_client_id', $val, $v2['client_id'], 'asset_id');
    }
    $set[] = 'ticket_asset_id = ?';
    $params[] = $val;
    $types .= 'i';
}

if (array_key_exists('billable', $body)) {
    $val = $body['billable'];
    if (!is_bool($val) && !in_array($val, [0, 1, '0', '1'], true)) {
        api_fail(422, 'VALIDATION_FAILED', 'billable must be a boolean.', 'billable');
    }
    $set[] = 'ticket_billable = ?';
    $params[] = (int) (bool) $val;
    $types .= 'i';
}

if (array_key_exists('vendor_ticket_number', $body)) {
    // varchar-safe: "ABC-123" round-trips (v1 intval trap fixed)
    $set[] = 'ticket_vendor_ticket_number = ?';
    $params[] = v2_p_str($body, 'vendor_ticket_number', '');
    $types .= 's';
}

if (array_key_exists('vendor_id', $body)) {
    $val = v2_p_int($body, 'vendor_id');
    if ($val === null) {
        api_fail(422, 'VALIDATION_FAILED', 'vendor_id must be an integer (0 to clear).', 'vendor_id');
    }
    $set[] = 'ticket_vendor_id = ?';
    $params[] = $val;
    $types .= 'i';
}

if (!$set) {
    api_fail(422, 'VALIDATION_FAILED', 'No updatable fields provided.');
}

$set[] = 'ticket_updated_at = NOW()';
$params[] = $ticket_id;
$params[] = $v2['client_id'];
$types .= 'ii';

$stmt = mysqli_prepare(
    $mysqli,
    'UPDATE tickets SET ' . implode(', ', $set) . ' WHERE ticket_id = ? AND ticket_client_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$updated = v2_ticket_fetch($mysqli, $ticket_id, $v2['client_id']);

logAction('Ticket', 'Edit', $updated['ticket_subject'] . ' via API v2 (' . $v2['key_name'] . ')', $v2['client_id'], $ticket_id);
logAction('API', 'Success', 'Edited ticket ' . $updated['ticket_subject'] . ' via API v2 (' . $v2['key_name'] . ')', $v2['client_id']);

api_ok($updated);
