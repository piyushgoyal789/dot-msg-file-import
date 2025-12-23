<?php
require_once 'config.php';

try {
    $db = getDB();
    
    echo "=== Passenger Attachments ===\n";
    $stmt = $db->prepare('SELECT * FROM passenger_attachments ORDER BY created_at DESC');
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}\n";
        echo "Passenger: {$row['passenger_name']}\n";
        echo "Attachment Name: {$row['attachment_name']}\n";
        echo "Attachment Type: {$row['attachment_type']}\n";
        echo "Size: {$row['attachment_size']} bytes\n";
        echo "Base64 Length: " . strlen($row['attachment_data']) . " characters\n";
        echo "Created: {$row['created_at']}\n";
        echo "---\n";
    }
    
    echo "\n=== Emails ===\n";
    $stmt = $db->prepare('SELECT * FROM emails');
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}\n";
        echo "Subject: {$row['subject']}\n";
        echo "Sender: {$row['sender']}\n";
        echo "Recipients: {$row['recipients']}\n";
        echo "Sent Date: {$row['sent_date']}\n";
        echo "Body Length: " . strlen($row['body_plain']) . " characters\n";
        echo "Created: {$row['created_at']}\n";
        echo "---\n";
    }
    
    echo "\n=== Email-Passenger Attachments Links ===\n";
    $stmt = $db->prepare('SELECT * FROM email_passenger_attachments');
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Email ID: {$row['email_id']}\n";
        echo "Passenger Attachment ID: {$row['passenger_attachment_id']}\n";
        echo "Created: {$row['created_at']}\n";
        echo "---\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>