<?php
require_once 'config.php';

// Get all attachments to see what we have
$db = getDB();
$stmt = $db->query("SELECT id, passenger_name, attachment_name, attachment_type, attachment_size FROM passenger_attachments ORDER BY id DESC");

echo "=== ALL ATTACHMENTS ===\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Name: {$row['passenger_name']} | File: {$row['attachment_name']} | Type: {$row['attachment_type']} | Size: {$row['attachment_size']} bytes\n";
}

// Now get the PDF specifically
$pdfStmt = $db->prepare("SELECT attachment_data FROM passenger_attachments WHERE attachment_name LIKE '%.pdf' ORDER BY id DESC LIMIT 1");
$pdfStmt->execute();
$pdfAttachment = $pdfStmt->fetch(PDO::FETCH_ASSOC);

if ($pdfAttachment && $pdfAttachment['attachment_data']) {
    $decodedContent = base64_decode($pdfAttachment['attachment_data']);
    
    echo "\n=== PDF CONTENT ANALYSIS ===\n";
    echo "PDF Content length: " . strlen($decodedContent) . " bytes\n\n";
    
    // Look for any occurrence of "ROSENTAL", "MEIR", "MR" or similar patterns
    $searchTerms = [
        'ROSENTAL',
        'MEIR', 
        'MR',
        'PASSENGER',
        'PAX',
        'NAME'
    ];
    
    echo "=== SEARCHING FOR TERMS IN PDF ===\n";
    foreach ($searchTerms as $term) {
        $count = substr_count(strtoupper($decodedContent), $term);
        if ($count > 0) {
            echo "Found '$term': $count times\n";
            
            // Get context around the first few occurrences
            $pos = 0;
            $occurrences = 0;
            while (($pos = stripos($decodedContent, $term, $pos)) !== false && $occurrences < 2) {
                $start = max(0, $pos - 50);
                $length = min(100, strlen($decodedContent) - $start);
                $context = substr($decodedContent, $start, $length);
                $cleanContext = preg_replace('/[^\x20-\x7E]/', '·', $context); // Replace non-printable with ·
                echo "  Context: " . $cleanContext . "\n";
                $pos++;
                $occurrences++;
            }
            echo "\n";
        }
    }
    
    // Look for all-caps words that might be names
    echo "=== LOOKING FOR UPPERCASE WORDS ===\n";
    if (preg_match_all('/\b[A-Z]{3,}\b/', $decodedContent, $matches)) {
        $uniqueWords = array_unique($matches[0]);
        $potentialNames = [];
        foreach ($uniqueWords as $word) {
            if (strlen($word) >= 4 && !in_array($word, ['TYPE', 'PAGE', 'FONT', 'NULL', 'TRUE', 'FALSE', 'STREAM', 'ENDOBJ', 'XREF'])) {
                $potentialNames[] = $word;
            }
        }
        if (!empty($potentialNames)) {
            echo "Potential name words found:\n";
            foreach ($potentialNames as $name) {
                echo "  - $name\n";
            }
        } else {
            echo "No potential name words found\n";
        }
    }
} else {
    echo "\nNo PDF attachment found\n";
}
?>