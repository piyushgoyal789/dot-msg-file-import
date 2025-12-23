<?php
require_once 'config.php';

// Get the latest attachment (should be ID 2 based on the viewer output)
$db = getDB();
$stmt = $db->prepare("SELECT attachment_data, attachment_name FROM passenger_attachments ORDER BY created_at DESC LIMIT 1");
$stmt->execute();
$attachment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($attachment && $attachment['attachment_data']) {
    $decodedContent = base64_decode($attachment['attachment_data']);
    
    echo "=== PDF CONTENT ANALYSIS ===\n";
    echo "File: " . $attachment['attachment_name'] . "\n";
    echo "Content length: " . strlen($decodedContent) . " bytes\n\n";
    
    // Save the raw content to a temporary file for better analysis
    file_put_contents('temp_pdf_content.txt', $decodedContent);
    
    // Look for any occurrence of "ROSENTAL", "MEIR", "MR" or similar patterns
    $searchTerms = [
        'ROSENTAL',
        'MEIR', 
        'MR',
        'PASSENGER',
        'PAX',
        'NAME',
        'KEREN',
        'PNINA'
    ];
    
    echo "=== SEARCHING FOR TERMS ===\n";
    foreach ($searchTerms as $term) {
        $count = substr_count(strtoupper($decodedContent), $term);
        if ($count > 0) {
            echo "Found '$term': $count times\n";
            
            // Get context around each occurrence
            $pos = 0;
            $occurrences = 0;
            while (($pos = stripos($decodedContent, $term, $pos)) !== false && $occurrences < 3) {
                $start = max(0, $pos - 30);
                $length = min(60, strlen($decodedContent) - $start);
                $context = substr($decodedContent, $start, $length);
                $cleanContext = preg_replace('/[^\x20-\x7E]/', '·', $context); // Replace non-printable with ·
                echo "  Context: " . $cleanContext . "\n";
                $pos++;
                $occurrences++;
            }
        }
    }
    
    // Try to extract any readable text patterns
    echo "\n=== EXTRACTING READABLE TEXT PATTERNS ===\n";
    if (preg_match_all('/[A-Z]{2,}(?:\s+[A-Z]{2,}){1,3}/', $decodedContent, $matches)) {
        $uniqueMatches = array_unique($matches[0]);
        echo "Found uppercase word patterns:\n";
        foreach ($uniqueMatches as $match) {
            if (strlen(trim($match)) > 5) { // Only show meaningful patterns
                echo "  - " . trim($match) . "\n";
            }
        }
    }
    
    // Look for any pattern that contains both names together
    echo "\n=== LOOKING FOR COMBINED NAME PATTERNS ===\n";
    if (preg_match_all('/[A-Z]{3,}[^A-Z]{0,10}[A-Z]{3,}[^A-Z]{0,10}(?:MR|MRS|MS)/i', $decodedContent, $matches)) {
        echo "Found potential name+title patterns:\n";
        foreach (array_unique($matches[0]) as $match) {
            echo "  - " . $match . "\n";
        }
    }
    
} else {
    echo "No attachment found or no data\n";
}
?>