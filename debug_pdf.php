<?php
require_once 'vendor/autoload.php';
use Opt\OLE\MsgParser;

$specificMsgPath = __DIR__ . '/Your Electronic Ticket-EMD Receipt.msg';

try {
    $parser = new MsgParser($specificMsgPath);
    $msg = $parser->parse();
    
    echo "=== Debug: MSG File Content Analysis ===\n\n";
    
    if ($msg->attachments && count($msg->attachments) > 0) {
        foreach ($msg->attachments as $index => $attachment) {
            echo "Attachment " . ($index + 1) . ":\n";
            echo "Filename: " . ($attachment['filename'] ?? 'unknown') . "\n";
            echo "MIME Type: " . ($attachment['mimeType'] ?? 'unknown') . "\n";
            
            if (isset($attachment['data'])) {
                $decodedData = base64_decode($attachment['data']);
                echo "Decoded size: " . strlen($decodedData) . " bytes\n";
                
                // If it's a PDF, try to extract text content
                if (strpos($attachment['mimeType'] ?? '', 'pdf') !== false || 
                    strtolower(pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION)) === 'pdf') {
                    
                    echo "\n--- PDF Content Analysis ---\n";
                    
                    // Look for passenger name patterns in raw PDF data
                    $patterns = [
                        '/Passenger[:\s]*([A-Z\/\s]+(?:MR|MRS|MS|MISS)\.?)/i',
                        '/PAX[:\s]*([A-Z\/\s]+)/i',
                        '/PNINA/i',
                        '/KEREN/i',
                        '/MRS/i',
                        '/([A-Z]{3,}\/[A-Z]{3,})/i',
                        '/([A-Z]{3,}\s+[A-Z]{3,}\s+MRS?)/i'
                    ];
                    
                    foreach ($patterns as $pattern) {
                        if (preg_match_all($pattern, $decodedData, $matches)) {
                            echo "Pattern '$pattern' found:\n";
                            foreach ($matches[0] as $match) {
                                echo "  - " . trim($match) . "\n";
                            }
                        }
                    }
                    
                    // Show first 1000 characters of decoded content for manual inspection
                    echo "\n--- First 1000 characters of PDF content ---\n";
                    $preview = substr($decodedData, 0, 1000);
                    // Replace non-printable characters with dots for readability
                    $preview = preg_replace('/[^\x20-\x7E\n\r\t]/', '.', $preview);
                    echo $preview . "\n";
                    
                    echo "\n--- Looking for 'PNINA' or 'KEREN' anywhere in the content ---\n";
                    if (strpos($decodedData, 'PNINA') !== false) {
                        echo "Found 'PNINA' in the PDF!\n";
                        // Get context around PNINA
                        $pos = strpos($decodedData, 'PNINA');
                        $context = substr($decodedData, max(0, $pos - 50), 100);
                        $context = preg_replace('/[^\x20-\x7E\n\r\t]/', '.', $context);
                        echo "Context: " . $context . "\n";
                    }
                    
                    if (strpos($decodedData, 'KEREN') !== false) {
                        echo "Found 'KEREN' in the PDF!\n";
                        $pos = strpos($decodedData, 'KEREN');
                        $context = substr($decodedData, max(0, $pos - 50), 100);
                        $context = preg_replace('/[^\x20-\x7E\n\r\t]/', '.', $context);
                        echo "Context: " . $context . "\n";
                    }
                    
                    if (stripos($decodedData, 'passenger') !== false) {
                        echo "Found 'passenger' in the PDF!\n";
                        $pos = stripos($decodedData, 'passenger');
                        $context = substr($decodedData, max(0, $pos - 50), 100);
                        $context = preg_replace('/[^\x20-\x7E\n\r\t]/', '.', $context);
                        echo "Context: " . $context . "\n";
                    }
                }
            }
            
            echo "\n" . str_repeat("-", 50) . "\n\n";
        }
    } else {
        echo "No attachments found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>