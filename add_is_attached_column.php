<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Check if is_attached column already exists
    $stmt = $db->query("SHOW COLUMNS FROM passenger_attachments LIKE 'is_attached'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "Adding is_attached column to passenger_attachments table...\n";
        $sql = "ALTER TABLE passenger_attachments ADD COLUMN is_attached TINYINT(1) DEFAULT 0 AFTER base64_preview";
        $db->exec($sql);
        echo "✓ Successfully added is_attached column\n";
    } else {
        echo "is_attached column already exists\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>