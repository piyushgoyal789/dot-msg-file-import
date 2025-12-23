<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'MsgDatabase.php';

use Opt\OLE\MsgParser;

function formatBytes($size, $precision = 2) {
    if ($size <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

function readMsgFile($filePath) {
    try {
        $parser = new MsgParser($filePath);
        $msg = $parser->parse();
        
        if (!$msg) {
            return null;
        }
        
        // Create standardized array structure
        $msgData = [
            'subject' => $msg->headers['Subject'] ?? 'No Subject',
            'from' => $msg->headers['From'] ?? '',
            'to' => $msg->headers['To'] ?? '',
            'cc' => $msg->headers['Cc'] ?? '',
            'bcc' => $msg->headers['Bcc'] ?? '',
            'date' => $msg->headers['Date'] ?? null,
            'received' => $msg->headers['Received'] ?? null,
            'body' => $msg->body ?? '',
            'bodyHTML' => $msg->bodyHTML ?? '',
            'attachments' => []
        ];
        
        // Process attachments
        if (isset($msg->attachments) && is_array($msg->attachments)) {
            foreach ($msg->attachments as $attachment) {
                $attachmentData = [
                    'name' => $attachment['filename'] ?? 'unknown',
                    'type' => $attachment['mimeType'] ?? 'unknown',
                    'size' => isset($attachment['data']) ? strlen(base64_decode($attachment['data'])) : 0,
                    'data' => $attachment['data'] ?? ''
                ];
                $msgData['attachments'][] = $attachmentData;
            }
        }
        
        return $msgData;
        
    } catch (Exception $e) {
        echo "Error reading MSG file: " . $e->getMessage() . "\n";
        return null;
    }
}

echo "=== Reprocessing MSG File with Improved Passenger Name Extraction ===\n\n";

try {
    $database = new MsgDatabase();
    $db = getDB();
    
    // Clear existing data
    echo "Clearing existing data...\n";
    $db->exec("DELETE FROM email_passenger_attachments");
    $db->exec("DELETE FROM passenger_attachments");
    $db->exec("DELETE FROM emails");
    $db->exec("ALTER TABLE emails AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE passenger_attachments AUTO_INCREMENT = 1");
    $db->exec("ALTER TABLE email_passenger_attachments AUTO_INCREMENT = 1");
    echo "Existing data cleared.\n\n";
    
    // Reprocess the MSG file
    $specificMsgPath = __DIR__ . '/Your Electronic Ticket-EMD Receipt.msg';
    
    if (file_exists($specificMsgPath)) {
        echo "Processing MSG file: " . basename($specificMsgPath) . "\n";
        
        $msgData = readMsgFile($specificMsgPath);
        
        if ($msgData) {
            echo "MSG file parsed successfully.\n";
            echo "Subject: " . $msgData['subject'] . "\n";
            echo "From: " . $msgData['from'] . "\n";
            echo "Attachments found: " . count($msgData['attachments']) . "\n\n";
            
            // Process each attachment and show extracted names
            foreach ($msgData['attachments'] as $index => $attachment) {
                echo "Attachment " . ($index + 1) . ":\n";
                echo "  Filename: " . $attachment['name'] . "\n";
                echo "  Type: " . $attachment['type'] . "\n";
                echo "  Size: " . formatBytes($attachment['size']) . "\n";
                
                // Test name extraction
                $extractedName = $database->extractPassengerName($attachment['name'], $attachment['data']);
                echo "  Extracted Passenger Name: " . $extractedName . "\n\n";
            }
            
            // Save to database
            $emailId = $database->processMsgFile($specificMsgPath, $msgData);
            echo "Data saved to database successfully. Email ID: " . $emailId . "\n\n";
            
            // Show updated statistics
            $stats = $database->getAttachmentStats();
            echo "=== Updated Database Statistics ===\n";
            echo "Total Attachments: " . ($stats['total_attachments'] ?? 0) . "\n";
            echo "Unique Passengers: " . ($stats['unique_passengers'] ?? 0) . "\n";
            echo "Unique Emails: " . ($stats['unique_emails'] ?? 0) . "\n";
            echo "Total Size: " . formatBytes($stats['total_size'] ?? 0) . "\n\n";
            
            // Show passenger names
            $passengers = $database->getPassengerNames();
            echo "=== Extracted Passenger Names ===\n";
            foreach ($passengers as $passenger) {
                echo "- " . $passenger . "\n";
            }
            
        } else {
            echo "Failed to parse MSG file.\n";
        }
        
    } else {
        echo "MSG file not found: " . $specificMsgPath . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>