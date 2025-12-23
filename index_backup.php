<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'MsgDatabase.php';

use Opt\OLE\MsgParser;

// Function to read and parse MSG file
function readMsgFile($filePath) {
    try {
        // Check if file exists
        if (!file_exists($filePath)) {
            throw new Exception("MSG file not found: " . $filePath);
        }

        // Create parser instance
        $parser = new MsgParser($filePath);
        
        // Parse the MSG file
        $msgData = $parser->parse();
        
        return $msgData;
        
    } catch (Exception $e) {
        echo "Error reading MSG file: " . $e->getMessage() . "\n";
        return null;
    }
}
            echo "<strong>" . htmlspecialchars($key) . ":</strong> " . htmlspecialchars($value) . "<br>\n";
        }
    } else {
        echo "<em>No headers found</em><br>\n";
    }
    echo "</div>\n";
    
    // Display message body
    if (isset($msgData->body)) {
        echo "<div style='border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;'>\n";
        echo "<h3>Message Body</h3>\n";
        echo "<div style='background: #f9f9f9; padding: 10px; white-space: pre-wrap; max-height: 400px; overflow-y: auto;'>\n";
        echo htmlspecialchars($msgData->body);
        echo "</div>\n";
        echo "</div>\n";
    }
    
    // Display alternative body (RTF) if available
    if (isset($msgData->alternativeBody)) {
        echo "<div style='border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;'>\n";
        echo "<h3>Alternative Body (RTF)</h3>\n";
        echo "<div style='background: #f9f9f9; padding: 10px; white-space: pre-wrap; max-height: 300px; overflow-y: auto;'>\n";
        echo htmlspecialchars($msgData->alternativeBody);
        echo "</div>\n";
        echo "</div>\n";
    }
    
    // Display attachments
    if (isset($msgData->attachments) && !empty($msgData->attachments)) {
        echo "<div style='border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;'>\n";
        echo "<h3>Attachments (" . count($msgData->attachments) . ")</h3>\n";
        
        // First, show a summary of attachment types
        $attachmentTypes = [];
        $totalSize = 0;
        foreach ($msgData->attachments as $attachment) {
            $filename = $attachment['filename'] ?? 'Unknown filename';
            $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $dataSize = isset($attachment['data']) ? strlen(base64_decode($attachment['data'])) : 0;
            $totalSize += $dataSize;
            
            if (!isset($attachmentTypes[$fileExt])) {
                $attachmentTypes[$fileExt] = ['count' => 0, 'size' => 0];
            }
            $attachmentTypes[$fileExt]['count']++;
            $attachmentTypes[$fileExt]['size'] += $dataSize;
        }
        
        echo "<div style='background: #e8f4fd; padding: 10px; border-radius: 4px; margin-bottom: 15px;'>\n";
        echo "<h4 style='margin: 0 0 10px 0;'>📊 Attachment Summary</h4>\n";
        echo "<strong>Total:</strong> " . count($msgData->attachments) . " files, " . number_format($totalSize) . " bytes total<br>\n";
        echo "<strong>File Types:</strong> ";
        $typeStrings = [];
        foreach ($attachmentTypes as $ext => $info) {
            $typeStrings[] = strtoupper($ext ? $ext : 'Unknown') . " ({$info['count']})";
        }
        echo implode(', ', $typeStrings) . "<br>\n";
        echo "</div>\n";
        
        foreach ($msgData->attachments as $index => $attachment) {
            $filename = $attachment['filename'] ?? 'Unknown filename';
            $mimeType = $attachment['mimeType'] ?? 'Unknown type';
            $dataSize = isset($attachment['data']) ? strlen(base64_decode($attachment['data'])) : 0;
            $size = number_format($dataSize) . ' bytes';
            
            // Determine file type from extension or MIME type
            $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $isPDF = ($fileExt === 'pdf' || strpos(strtolower($mimeType), 'pdf') !== false);
            $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'bmp']) || strpos(strtolower($mimeType), 'image') !== false;
            $isDocument = in_array($fileExt, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt']);
            
            // Choose icon/indicator based on file type
            $fileIcon = '📎'; // Default attachment icon
            if ($isPDF) $fileIcon = '📄';
            elseif ($isImage) $fileIcon = '🖼️';
            elseif ($isDocument) $fileIcon = '📋';
            
            echo "<div style='background: #f0f0f0; padding: 12px; margin: 8px 0; border-radius: 6px; border-left: 4px solid " . ($isPDF ? '#d32f2f' : '#007cba') . ";'>\n";
            echo "<div style='display: flex; justify-content: space-between; align-items: flex-start;'>\n";
            echo "<div style='flex: 1;'>\n";
            echo "<strong>{$fileIcon} Attachment " . ($index + 1) . ":</strong> " . htmlspecialchars($filename) . "<br>\n";
            echo "<strong>Type:</strong> " . htmlspecialchars($mimeType) . " (" . strtoupper($fileExt) . ")<br>\n";
            echo "<strong>Size:</strong> {$size}<br>\n";
            
            // Show additional info for PDFs
            if ($isPDF) {
                echo "<span style='color: #d32f2f; font-weight: bold;'>📄 PDF Document</span><br>\n";
            }
            
            // Show base64 preview for small files or first part for large files
            if (isset($attachment['data'])) {
                $base64Data = $attachment['data'];
                $previewLength = min(100, strlen($base64Data));
                echo "<strong>Base64 Preview:</strong><br>\n";
                echo "<code style='background: #e8e8e8; padding: 4px; border-radius: 3px; font-size: 11px; display: block; word-break: break-all; max-width: 100%;'>";
                echo htmlspecialchars(substr($base64Data, 0, $previewLength));
                if (strlen($base64Data) > $previewLength) {
                    echo "... <span style='color: #666;'>[+" . number_format(strlen($base64Data) - $previewLength) . " more chars]</span>";
                }
                echo "</code>\n";
            }
            echo "</div>\n";
            
            echo "<div style='margin-left: 15px;'>\n";
            // Add download link for attachment
            if (isset($attachment['data'])) {
                $encodedData = $attachment['data'];
                $encodedFilename = urlencode($filename);
                echo "<a href='data:{$mimeType};base64,{$encodedData}' download='{$encodedFilename}' style='background: " . ($isPDF ? '#d32f2f' : '#007cba') . "; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; font-size: 14px;'>📥 Download</a>\n";
                
                // Add view link for PDFs and images
                if ($isPDF || $isImage) {
                    echo "<br><a href='data:{$mimeType};base64,{$encodedData}' target='_blank' style='background: #28a745; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; font-size: 14px;'>👁️ View</a>\n";
                }
            }
            echo "</div>\n";
            echo "</div>\n";
            echo "</div>\n";
        }
        echo "</div>\n";
    } else {
        echo "<div style='border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;'>\n";
        echo "<h3>Attachments</h3>\n";
        echo "<p><em>No attachments found in this MSG file.</em></p>\n";
        echo "</div>\n";
    }
    
    // Display raw data structure for debugging
    echo "<div style='border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;'>\n";
    echo "<h3>Raw Data Structure (for debugging)</h3>\n";
    echo "<details style='margin: 10px 0;'>\n";
    echo "<summary style='cursor: pointer; font-weight: bold;'>Click to view raw data</summary>\n";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto;'>\n";
    echo htmlspecialchars(print_r($msgData, true));
    echo "</pre>\n";
    echo "</details>\n";
    echo "</div>\n";
    
    echo "</div>\n";
}

// Main execution
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSG File Reader (QS Version)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .upload-form { background: #f9f9f9; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .error { color: red; padding: 10px; background: #ffe6e6; border: 1px solid #ff9999; border-radius: 4px; margin: 10px 0; }
        .success { color: green; padding: 10px; background: #e6ffe6; border: 1px solid #99ff99; border-radius: 4px; margin: 10px 0; }
        input[type="file"] { margin: 10px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MSG File Parser & Database (QS Version)</h1>
        
        <div style="margin: 20px 0;">
            <a href="database_viewer.php" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">📊 View Database Contents</a>
        </div>
        
        <div class="upload-form">
            <h3>Directory Contents</h3>
            <?php
            // Show current directory contents and highlight MSG files
            $currentDir = __DIR__;
            $files = scandir($currentDir);
            $msgFiles = array_filter($files, function($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'msg';
            });
            
            if (!empty($msgFiles)) {
                echo "<div style='background: #e6ffe6; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
                echo "<strong>MSG files found in directory:</strong><br>";
                foreach ($msgFiles as $msgFile) {
                    echo "📧 " . htmlspecialchars($msgFile) . "<br>";
                }
                echo "</div>";
            } else {
                echo "<div style='background: #ffe6e6; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
                echo "<strong>No MSG files found in current directory</strong>";
                echo "</div>";
            }
            ?>
        </div>

        <div class="upload-form">
            <h3>Upload MSG Files</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="msg_files[]" accept=".msg" multiple required>
                <br>
                <p style="font-size: 12px; color: #666; margin: 5px 0;">You can select multiple MSG files at once</p>
                <button type="submit" name="upload">Process MSG Files</button>
            </form>
        </div>

<?php
// Handle file upload and processing
if (isset($_POST['upload']) && isset($_FILES['msg_files'])) {
    $uploadedFiles = $_FILES['msg_files'];
    $totalFiles = count($uploadedFiles['name']);
    
    echo "<h2>Processing {$totalFiles} MSG file(s)</h2>";
    
    // Create uploads directory if it doesn't exist
    $uploadsDir = 'uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    for ($i = 0; $i < $totalFiles; $i++) {
        // Check for upload errors for this file
        if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
            echo "<div class='error'>Upload failed for file " . ($i + 1) . " with error code: " . $uploadedFiles['error'][$i] . "</div>";
            $errorCount++;
            continue;
        }
        
        $fileName = basename($uploadedFiles['name'][$i]);
        $targetPath = $uploadsDir . '/' . $fileName;
        
        if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $targetPath)) {
            echo "<div class='success'>File " . ($i + 1) . " uploaded successfully: " . htmlspecialchars($fileName) . "</div>";
            
            try {
                // Read and display MSG file contents
                $msgData = readMsgFile($targetPath);
                
                if ($msgData) {
                    // Save to database
                    $database = new MsgDatabase();
                    $emailId = $database->processMsgFile($targetPath, $msgData);
                    
                    echo "<div style='margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;'>";
                    echo "<strong>Success!</strong> MSG file processed and saved to database. Email ID: " . $emailId . " - File: " . htmlspecialchars($fileName);
                    echo "</div>";
                    
                    // Only display contents for the first file to avoid overwhelming output
                    if ($i === 0) {
                        displayMsgContents($msgData);
                        if ($totalFiles > 1) {
                            echo "<div style='background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0;'>";
                            echo "<strong>Note:</strong> Contents shown for first file only. All files are being processed and saved to database.";
                            echo "</div>";
                        }
                    }
                    
                    $successCount++;
                } else {
                    echo "<div class='error'>Failed to parse MSG file: " . htmlspecialchars($fileName) . "</div>";
                    $errorCount++;
                }
            } catch (Exception $e) {
                echo "<div class='error'>Error processing MSG file " . htmlspecialchars($fileName) . ": " . htmlspecialchars($e->getMessage()) . "</div>";
                $errorCount++;
            }
            
            // Clean up uploaded file after processing
            unlink($targetPath);
            
        } else {
            echo "<div class='error'>Failed to move uploaded file: " . htmlspecialchars($fileName) . "</div>";
            $errorCount++;
        }
    }
    
    // Show summary
    echo "<div style='margin: 20px 0; padding: 15px; background: #e9ecef; border: 1px solid #dee2e6; border-radius: 4px;'>";
    echo "<h3>Processing Summary</h3>";
    echo "<strong>Total Files:</strong> {$totalFiles}<br>";
    echo "<strong>Successfully Processed:</strong> <span style='color: green;'>{$successCount}</span><br>";
    echo "<strong>Errors:</strong> <span style='color: red;'>{$errorCount}</span>";
    echo "</div>";
}

// Display database management interface
echo "<hr style='margin: 40px 0;'>";
echo "<h2>Database Management</h2>";

try {
    $database = new MsgDatabase();
    
    // Display statistics
    $stats = $database->getAttachmentStats();
    echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;'>";
    echo "<h3>Database Statistics</h3>";
    echo "<ul>";
    echo "<li><strong>Total Attachments:</strong> " . ($stats['total_attachments'] ?? 0) . "</li>";
    echo "<li><strong>Unique Passengers:</strong> " . ($stats['unique_passengers'] ?? 0) . "</li>";
    echo "<li><strong>Unique Emails:</strong> " . ($stats['unique_emails'] ?? 0) . "</li>";
    echo "<li><strong>Total Size:</strong> " . formatBytes($stats['total_size'] ?? 0) . "</li>";
    echo "<li><strong>Average Size:</strong> " . formatBytes($stats['avg_size'] ?? 0) . "</li>";
    echo "</ul>";
    echo "</div>";
    
    // Display passenger list
    $passengers = $database->getPassengerNames();
    if (!empty($passengers)) {
        echo "<div style='background: #fff; padding: 20px; border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 20px;'>";
        echo "<h3>Passengers in Database</h3>";
        echo "<ul>";
        foreach ($passengers as $passenger) {
            echo "<li>" . htmlspecialchars($passenger) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

        <div style="margin-top: 30px; padding: 15px; background: #e6f3ff; border-radius: 6px;">
            <h3>Usage Instructions:</h3>
            <ol>
                <li>Use the upload form above to select and upload a .msg file</li>
                <li>The script will parse and display the email contents including:
                    <ul>
                        <li>Subject, sender, recipients, dates</li>
                        <li>Message body (both plain text and HTML)</li>
                        <li>List of attachments with sizes</li>
                    </ul>
                </li>
                <li>You can also modify the code to read specific MSG files from the server</li>
            </ol>
            
            <h4>Supported Features:</h4>
            <ul>
                <li>Reading email properties (subject, sender, recipients, dates)</li>
                <li>Extracting plain text and HTML message bodies</li>
                <li>Listing attachments with metadata</li>
                <li>Safe HTML output with proper escaping</li>
            </ul>
        </div>
    </div>
</body>
</html>