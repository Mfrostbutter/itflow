<?php

// api/v2 shared ticket helpers: status name resolution, scoped fetch,
// cross-client reference guards.

// Resolve a status NAME to its id (case-insensitive via table collation). 422 on unknown.
function v2_ticket_status_id(mysqli $mysqli, string $name): int
{
    $stmt = mysqli_prepare($mysqli, 'SELECT ticket_status_id FROM ticket_statuses WHERE ticket_status_name = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        api_fail(422, 'VALIDATION_FAILED', "Unknown ticket status: $name.", 'status');
    }
    return (int) $row['ticket_status_id'];
}

// Fetch one ticket in client scope, with status + tech names joined. Null if absent.
function v2_ticket_fetch(mysqli $mysqli, int $ticket_id, int $client_id): ?array
{
    $stmt = mysqli_prepare(
        $mysqli,
        'SELECT tickets.*, ticket_statuses.ticket_status_name, users.user_name AS ticket_assigned_to_name
         FROM tickets
         LEFT JOIN ticket_statuses ON ticket_status = ticket_status_id
         LEFT JOIN users ON ticket_assigned_to = user_id
         WHERE ticket_id = ? AND ticket_client_id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'ii', $ticket_id, $client_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

// Guard: a referenced row must exist and belong to the ticket's client. 422 otherwise.
// $table/$id_col/$client_col are code-supplied identifiers, never user input.
function v2_require_in_client(mysqli $mysqli, string $table, string $id_col, string $client_col, int $id, int $client_id, string $field): void
{
    $stmt = mysqli_prepare($mysqli, "SELECT $client_col FROM $table WHERE $id_col = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) {
        api_fail(422, 'VALIDATION_FAILED', "$field $id does not exist.", $field);
    }
    if ((int) $row[$client_col] !== $client_id) {
        api_fail(422, 'VALIDATION_FAILED', "$field $id belongs to a different client.", $field);
    }
}

// Guard: user must exist and not be archived. 422 otherwise.
function v2_require_active_user(mysqli $mysqli, int $user_id, string $field): void
{
    $stmt = mysqli_prepare($mysqli, 'SELECT user_archived_at FROM users WHERE user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row || $row['user_archived_at'] !== null) {
        api_fail(422, 'VALIDATION_FAILED', "$field $user_id is not an active user.", $field);
    }
}
