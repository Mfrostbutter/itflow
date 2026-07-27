<?php

// POST /api/v2/time_entries/create.php
// Extension-schema feature (time_entries table is not stock ITFlow).
// Required: hours, tech_id. Optional: ticket_id (must be in client scope),
// rate (default 0), billable (default true), date (default today), note.

$REQUIRE_METHOD = 'POST';
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../_lib/tickets.php';

v2_require_table($mysqli, 'time_entries', 'time_entries.create');

$hours = $body['hours'] ?? null;
if (!is_numeric($hours) || (float) $hours <= 0 || (float) $hours >= 1000) {
    api_fail(422, 'VALIDATION_FAILED', 'hours must be a number greater than 0.', 'hours');
}
$hours = (float) $hours;

$tech_id = v2_p_int($body, 'tech_id');
if ($tech_id === null || $tech_id === 0) {
    api_fail(422, 'VALIDATION_FAILED', 'tech_id is required.', 'tech_id');
}
v2_require_active_user($mysqli, $tech_id, 'tech_id');

$ticket_id = v2_p_int($body, 'ticket_id');
if ($ticket_id !== null && $ticket_id !== 0) {
    $ticket = v2_ticket_fetch($mysqli, $ticket_id, $v2['client_id']);
    if ($ticket === null) {
        api_fail(422, 'VALIDATION_FAILED', "ticket_id $ticket_id not found for this client.", 'ticket_id');
    }
} else {
    $ticket_id = null;
}

$rate = $body['rate'] ?? 0;
if (!is_numeric($rate) || (float) $rate < 0) {
    api_fail(422, 'VALIDATION_FAILED', 'rate must be a non-negative number.', 'rate');
}
$rate = (float) $rate;

$billable = $body['billable'] ?? true;
if (!is_bool($billable) && !in_array($billable, [0, 1, '0', '1'], true)) {
    api_fail(422, 'VALIDATION_FAILED', 'billable must be a boolean.', 'billable');
}
$billable = (int) (bool) $billable;

$date = v2_p_date($body, 'date', date('Y-m-d'));

$note = v2_p_str($body, 'note', '');
if (strlen($note) > 200) {
    api_fail(422, 'VALIDATION_FAILED', 'note must be 200 characters or fewer.', 'note');
}

$stmt = mysqli_prepare(
    $mysqli,
    'INSERT INTO time_entries
     SET entry_client_id = ?, entry_ticket_id = ?, entry_tech_id = ?, entry_hours = ?,
         entry_billable = ?, entry_rate = ?, entry_date = ?, entry_note = ?, entry_created_at = NOW()'
);
mysqli_stmt_bind_param($stmt, 'iiididss', $v2['client_id'], $ticket_id, $tech_id, $hours, $billable, $rate, $date, $note);
mysqli_stmt_execute($stmt);
$entry_id = mysqli_insert_id($mysqli);
mysqli_stmt_close($stmt);

logAction('Time Entry', 'Create', "Logged {$hours}h via API v2 (" . $v2['key_name'] . ')', $v2['client_id'], $ticket_id ?? 0);

$stmt = mysqli_prepare(
    $mysqli,
    'SELECT time_entries.*, users.user_name AS entry_tech_name
     FROM time_entries LEFT JOIN users ON entry_tech_id = user_id
     WHERE entry_id = ? LIMIT 1'
);
mysqli_stmt_bind_param($stmt, 'i', $entry_id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

api_ok($row, null, 201);
