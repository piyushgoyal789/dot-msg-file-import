<?php
require_once 'config.php';

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: database_viewer.php?msg=Invalid attachment ID&status=failed');
    exit;
}

$attachmentId = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get attachment info before deletion for confirmation message
    $stmt = $db->prepare("SELECT passenger_name, attachment_name FROM passenger_attachments WHERE id = ?");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attachment) {
        header('Location: database_viewer.php?msg=Attachment not found&status=failed');
        exit;
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Delete from email_passenger_attachments table first (foreign key constraint)
    $stmt = $db->prepare("DELETE FROM email_passenger_attachments WHERE passenger_attachment_id = ?");
    $stmt->execute([$attachmentId]);
    
    // Delete from passenger_attachments table
    $stmt = $db->prepare("DELETE FROM passenger_attachments WHERE id = ?");
    $result = $stmt->execute([$attachmentId]);
    
    if ($result && $stmt->rowCount() > 0) {
        $db->commit();
        $passengerName = htmlspecialchars($attachment['passenger_name']);
        $fileName = htmlspecialchars($attachment['attachment_name']);
        header("Location: database_viewer.php?msg=Attachment deleted successfully for {$passengerName} ({$fileName})&status=success");
    } else {
        $db->rollback();
        header('Location: database_viewer.php?msg=Failed to delete attachment&status=failed');
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollback();
    }
    error_log("Delete attachment error: " . $e->getMessage());
    header('Location: database_viewer.php?msg=Database error occurred&status=failed');
}

exit;
?>