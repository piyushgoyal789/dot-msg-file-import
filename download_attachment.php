<?php
require_once 'config.php';
require_once 'MsgDatabase.php';

// Check if ID parameter is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die('Invalid attachment ID');
}

$attachmentId = intval($_GET['id']);

try {
    $database = new MsgDatabase();
    
    // Get attachment data
    $attachment = $database->getAttachmentById($attachmentId);
    
    if (!$attachment) {
        http_response_code(404);
        die('Attachment not found');
    }
    
    // Decode the base64 data
    $fileData = base64_decode($attachment['attachment_data']);
    
    if ($fileData === false) {
        http_response_code(500);
        die('Failed to decode attachment data');
    }
    
    // Get file extension and set appropriate content type
    $fileName = $attachment['attachment_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $contentTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain'
    ];
    
    $contentType = isset($contentTypes[$fileExt]) ? $contentTypes[$fileExt] : 'application/octet-stream';
    
    // Set headers for file download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($fileData));
    header('Cache-Control: private');
    header('Pragma: private');
    header('Expires: 0');
    
    // Output the file data
    echo $fileData;
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error downloading attachment: ' . htmlspecialchars($e->getMessage()));
}
?>