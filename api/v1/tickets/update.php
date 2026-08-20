<?php

// Update endpoint for tickets
// Stock ITFlow can create and resolve a ticket over the API but not edit one.
// Fields not supplied keep their current value (see ticket_model.php).

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$ticket_id = intval($_POST['ticket_id']);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = $client_id LIMIT 1"));

    if ($ticket_row) {

        // Override so things fail if the ticket ID is bad
        $ticket_id = intval($ticket_row['ticket_id']);
        $ticket_prefix = sanitizeInput($ticket_row['ticket_prefix']);
        $ticket_number = intval($ticket_row['ticket_number']);

        // Variable assignment from POST - assigning the current database value if a value is not provided
        require_once 'ticket_model.php';

        $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_subject = '$subject', ticket_priority = '$priority', ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_asset_id = $asset, ticket_billable = $billable, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

        // Check update
        if ($update_sql) {
            $update_count = mysqli_affected_rows($mysqli);

            // Logging
            logAction("Ticket", "Edit", "$ticket_prefix$ticket_number ticket via API ($api_key_name)", $client_id, $ticket_id);
            logAction("API", "Success", "Edited ticket $ticket_prefix$ticket_number via API ($api_key_name)", $client_id);
        }

        customAction('ticket_update', $ticket_id);
    }
}

// Output
require_once '../update_output.php';
