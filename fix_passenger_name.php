<?php
require_once 'config.php';
require_once 'MsgDatabase.php';

// Get the attachment with ID 10
$db = getDB();
$stmt = $db->prepare("SELECT * FROM passenger_attachments WHERE id = ?");
$stmt->execute([10]);
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($attachment) {
    echo "Current attachment info:\n";
    echo "ID: " . $attachment['id'] . "\n";
    echo "Current passenger name: " . $attachment['passenger_name'] . "\n";
    echo "Attachment name: " . $attachment['attachment_name'] . "\n";
    echo "Attachment size: " . $attachment['attachment_size'] . " bytes\n";
    
    // Re-extract passenger name from the attachment data
    $database = new MsgDatabase();
    $newPassengerName = $database->extractPassengerName($attachment['attachment_name'], $attachment['attachment_data']);
    
    echo "\nExtracted passenger name: " . $newPassengerName . "\n";
    
    // Update the passenger name in the database
    $updateStmt = $db->prepare("UPDATE passenger_attachments SET passenger_name = ? WHERE id = ?");
    $result = $updateStmt->execute([$newPassengerName, 10]);
    
    if ($result) {
        echo "\n✓ Successfully updated passenger name for attachment ID 10 to: " . $newPassengerName . "\n";
    } else {
        echo "\n❌ Failed to update passenger name\n";
    }
} else {
    echo "Attachment with ID 10 not found\n";
}
?>