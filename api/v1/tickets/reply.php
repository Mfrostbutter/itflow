<?php

// Reply endpoint for tickets
// Appends a reply to the ticket thread. Never overwrites: this is the audit
// trail, so each call adds a timestamped, attributed row.
// Side effects match the agent UI: bump ticket_updated_at, mark first response
// on the first Public reply, notify the assigned tech.

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id']);

// Default
$insert_id = false;

if (!empty($ticket_id) && isset($_POST['ticket_reply'])) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = $client_id LIMIT 1"));

    if ($ticket_row) {

        // Override so things fail if the ticket ID is bad
        $ticket_id = intval($ticket_row['ticket_id']);
        $ticket_prefix = sanitizeInput($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_subject = sanitizeInput($ticket_row['ticket_subject']);
        $ticket_assigned_to = intval($ticket_row['ticket_assigned_to']);
        $ticket_first_response_at = $ticket_row['ticket_first_response_at'];

        $reply = mysqli_real_escape_string($mysqli, $_POST['ticket_reply']);

        // Internal is agent-only, Public is visible to the contact, Client is
        // the contact's own reply. Anything else is refused rather than stored.
        $reply_type = 'Internal';
        if (isset($_POST['ticket_reply_type'])) {
            $requested_type = ucfirst(strtolower(sanitizeInput($_POST['ticket_reply_type'])));
            if (in_array($requested_type, ['Internal', 'Public', 'Client'])) {
                $reply_type = $requested_type;
            }
        }

        // time is a TIME column; blank means no time logged, not zero worked
        $time_worked = '00:00:00';
        if (!empty($_POST['ticket_reply_time_worked'])) {
            $time_worked = sanitizeInput($_POST['ticket_reply_time_worked']);
        }

        // Attribution. 0 means the API key itself, not a tech.
        $reply_by = intval($_POST['ticket_reply_by'] ?? 0);

        $insert_sql = mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$reply', ticket_reply_type = '$reply_type', ticket_reply_time_worked = '$time_worked', ticket_reply_by = $reply_by, ticket_reply_ticket_id = $ticket_id");

        if ($insert_sql) {
            $insert_id = mysqli_insert_id($mysqli);

            mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

            // Only a Public reply is a response the contact can see, so an
            // Internal note must not satisfy the first-response clock.
            if (empty($ticket_first_response_at) && $reply_type == 'Public') {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");
            }

            // Targeted at the assignee only. appNotify() broadcasts to every
            // active agent, which is wrong for a single ticket reply.
            if (!empty($ticket_assigned_to)) {
                $notification = mysqli_real_escape_string($mysqli, "API replied to Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you");
                $notification_action = "/agent/ticket.php?ticket_id=$ticket_id&client_id=$client_id";
                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = '$notification', notification_action = '$notification_action', notification_client_id = $client_id, notification_entity_id = $ticket_id, notification_user_id = $ticket_assigned_to");
            }

            // Logging
            logAction("Ticket", "Reply", "$ticket_prefix$ticket_number ticket via API ($api_key_name) ($reply_type reply)", $client_id, $ticket_id);
            logAction("API", "Success", "Replied to ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
        }

        customAction('ticket_reply', $ticket_id);
    }
}

// Output
require_once '../create_output.php';
