<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Clear tables in correct order (due to foreign key constraints)
    $tables = ['email_passenger_attachments', 'passenger_attachments', 'emails'];
    
    $db->beginTransaction();
    
    foreach ($tables as $table) {
        $sql = "DELETE FROM $table";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception("Failed to clear table $table");
        }
    }
    
    // Reset auto-increment counters
    foreach ($tables as $table) {
        $sql = "ALTER TABLE $table AUTO_INCREMENT = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute();
    }
    
    $db->commit();
    
    // Redirect with success message
    header('Location: database_viewer.php?status=clear_success');
    exit();
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    // Redirect with error message
    header('Location: database_viewer.php?status=clear_failed&error=' . urlencode($e->getMessage()));
    exit();
}
?>