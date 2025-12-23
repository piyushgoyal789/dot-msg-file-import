<?php
require_once 'config.php';

echo "=== MANUAL PASSENGER NAME UPDATE ===\n\n";

// Get all current attachments
$db = getDB();
$stmt = $db->query("SELECT id, passenger_name, attachment_name, attachment_type FROM passenger_attachments ORDER BY id");

echo "Current attachments:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Current Name: '{$row['passenger_name']}' | File: {$row['attachment_name']}\n";
}

echo "\nWhich attachment should be 'Rosental Meir Mr'? Enter the ID number: ";
$handle = fopen("php://stdin", "r");
$attachmentId = trim(fgets($handle));
fclose($handle);

if (is_numeric($attachmentId)) {
    $updateStmt = $db->prepare("UPDATE passenger_attachments SET passenger_name = ? WHERE id = ?");
    $result = $updateStmt->execute(['Rosental Meir Mr', $attachmentId]);
    
    if ($result) {
        echo "\n✓ Successfully updated attachment ID $attachmentId to 'Rosental Meir Mr'\n";
    } else {
        echo "\n❌ Failed to update attachment ID $attachmentId\n";
    }
} else {
    echo "\nInvalid ID. Exiting.\n";
}
?>