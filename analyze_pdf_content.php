<?php
require_once 'config.php';

// Get the attachment with ID 10
$db = getDB();
$stmt = $db->prepare("SELECT attachment_data FROM passenger_attachments WHERE id = ?");
$stmt->execute([10]);
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($attachment && $attachment['attachment_data']) {
    $decodedContent = base64_decode($attachment['attachment_data']);
    
    echo "Searching for passenger name patterns in PDF content...\n\n";
    
    // Look for specific patterns that might contain "Rosental Meir"
    $searchPatterns = [
        '/ROSENTAL.*?MEIR.*?MR/i',
        '/MEIR.*?ROSENTAL.*?MR/i',
        '/ROSENTAL\s+MEIR/i',
        '/MEIR\s+ROSENTAL/i',
        '/MR.*?ROSENTAL/i',
        '/MR.*?MEIR/i',
        '/ROSENTAL/i',
        '/MEIR/i'
    ];
    
    foreach ($searchPatterns as $pattern) {
        if (preg_match_all($pattern, $decodedContent, $matches, PREG_OFFSET_CAPTURE)) {
            echo "Pattern: $pattern\n";
            foreach ($matches[0] as $match) {
                $text = $match[0];
                $position = $match[1];
                // Get surrounding context
                $start = max(0, $position - 50);
                $length = min(100, strlen($decodedContent) - $start);
                $context = substr($decodedContent, $start, $length);
                
                echo "  Found: '$text' at position $position\n";
                echo "  Context: " . addslashes($context) . "\n\n";
            }
        }
    }
    
    // Also search for any capitalized words that might be names
    if (preg_match_all('/\b[A-Z]{3,}\s+[A-Z]{3,}\s+[A-Z]{2,}\b/', $decodedContent, $matches)) {
        echo "Potential name patterns (WORD WORD TITLE):\n";
        foreach (array_unique($matches[0]) as $match) {
            echo "  - $match\n";
        }
    }
    
} else {
    echo "Attachment not found or no data\n";
}
?>