<?php

// Update endpoint for tickets
// Send a POST here with a ticket & client id, along with any fields you want to change
// Fields that aren't provided keep their current value (see ticket_model.php)

require_once '../validate_api_key.php';

require_once '../require_post_method.php';

// Parse ID
$ticket_id = intval($_POST['ticket_id']);

// Default
$update_count = false;

if (!empty($ticket_id)) {

    $ticket_row = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT * FROM tickets WHERE ticket_id = '$ticket_id' AND ticket_client_id = $client_id LIMIT 1"));

    // Variable assignment from POST - assigning the current database value if a value is not provided
    require_once 'ticket_model.php';

    $update_sql = mysqli_query($mysqli, "UPDATE tickets SET ticket_subject = '$subject', ticket_details = '$details', ticket_priority = '$priority', ticket_billable = $billable, ticket_vendor_ticket_number = '$vendor_ticket_number', ticket_vendor_id = $vendor_id, ticket_assigned_to = $assigned_to, ticket_contact_id = $contact, ticket_asset_id = $asset, ticket_updated_at = NOW() WHERE ticket_id = $ticket_id AND ticket_client_id = $client_id LIMIT 1");

    // Check update & log
    if ($update_sql) {
        $update_count = mysqli_affected_rows($mysqli);

        // Logging
        logAction("Ticket", "Edit", "$subject via API ($api_key_name)", $client_id, $ticket_id);
        logAction("API", "Success", "Edited ticket $subject via API ($api_key_name)", $client_id);
    }
}

// Output
require_once '../update_output.php';
