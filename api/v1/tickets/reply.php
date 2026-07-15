<?php

// Reply endpoint for tickets
// Send a POST here with a ticket & client id and a reply, and we add it to the ticket thread
// Mirrors the reply handling in agent/post/ticket.php, minus the e-mail/signature handling
// (API writes don't e-mail, consistent with tickets/create.php)

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse Info
$ticket_id = intval($_POST['ticket_id']);

// Reply body - SQL escaped only, not sanitized, as replies hold HTML (see agent/post/ticket.php)
if (isset($_POST['ticket_reply'])) {
    $ticket_reply = mysqli_escape_string($mysqli, $_POST['ticket_reply']);
} else {
    $ticket_reply = '';
}

// Reply type - Public (visible to the contact) or Internal (default, agent-only)
if (isset($_POST['ticket_reply_type']) && strtolower($_POST['ticket_reply_type']) == 'public') {
    $ticket_reply_type = 'Public';
} else {
    $ticket_reply_type = 'Internal';
}

// Time worked - HH:MM:SS, defaults to none so it doesn't count against tech time reporting
if (isset($_POST['ticket_reply_time_worked']) && preg_match('/^\d{1,3}:[0-5]\d:[0-5]\d$/', $_POST['ticket_reply_time_worked'])) {
    $ticket_reply_time_worked = sanitizeInput($_POST['ticket_reply_time_worked']);
} else {
    $ticket_reply_time_worked = '00:00:00';
}

// Attribution - the API has no session user, so default to 0 like tickets/create.php
// Set ticket_reply_by to a user ID to attribute the reply to a specific tech
if (isset($_POST['ticket_reply_by'])) {
    $ticket_reply_by = intval($_POST['ticket_reply_by']);
} else {
    $ticket_reply_by = 0;
}

// Default
$insert_id = false;

if (!empty($ticket_id) && !empty($ticket_reply)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = $client_id LIMIT 1"));

    if ($ticket_row) {

        // Grab what we need, not using the model
        $ticket_id = intval($ticket_row['ticket_id']); // Override so things fail if this is bad
        $ticket_prefix = sanitizeInput($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);
        $ticket_subject = sanitizeInput($ticket_row['ticket_subject']);
        $ticket_first_response_at = sanitizeInput($ticket_row['ticket_first_response_at']);
        $ticket_assigned_to = intval($ticket_row['ticket_assigned_to']);

        // Add reply
        $insert_sql = mysqli_query($mysqli, "INSERT INTO ticket_replies SET ticket_reply = '$ticket_reply', ticket_reply_type = '$ticket_reply_type', ticket_reply_time_worked = '$ticket_reply_time_worked', ticket_reply_by = $ticket_reply_by, ticket_reply_ticket_id = $ticket_id");

        // Check insert & get insert ID
        if ($insert_sql) {
            $insert_id = mysqli_insert_id($mysqli);

            // Touch the ticket
            mysqli_query($mysqli, "UPDATE tickets SET ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

            // Mark FR (if not) - only public replies count as a response to the contact
            if (empty($ticket_first_response_at) && $ticket_reply_type == 'Public') {
                mysqli_query($mysqli, "UPDATE tickets SET ticket_first_response_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");
            }

            // Notify the assigned tech
            if (!empty($ticket_assigned_to)) {
                if ($client_id) {
                    $client_uri = "&client_id=$client_id";
                } else {
                    $client_uri = '';
                }

                mysqli_query($mysqli, "INSERT INTO notifications SET notification_type = 'Ticket', notification = 'API ($api_key_name) replied to Ticket $ticket_prefix$ticket_number - Subject: $ticket_subject that is assigned to you', notification_action = '/agent/ticket.php?ticket_id=$ticket_id$client_uri', notification_client_id = $client_id, notification_user_id = $ticket_assigned_to");
            }

            // Logging
            logAction("Ticket", "Reply", "$ticket_prefix$ticket_number ticket via API ($api_key_name) and was a $ticket_reply_type reply", $client_id, $ticket_id);
            logAction("API", "Success", "Replied to ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
        }
    }
}

// Output
require_once '../create_output.php';
