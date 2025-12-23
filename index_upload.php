<?php
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'MsgDatabase.php';

use Opt\OLE\MsgParser;

// Function to read and parse MSG file
function readMsgFile($filePath) {
    try {
        if (!file_exists($filePath)) {
            throw new Exception("MSG file not found: " . $filePath);
        }

        $parser = new MsgParser($filePath);
        $msgData = $parser->parse();
        
        return $msgData;
        
    } catch (Exception $e) {
        return null;
    }
}

// Handle file upload and processing
if (isset($_POST['upload']) && isset($_FILES['msg_files'])) {
    $uploadedFiles = $_FILES['msg_files'];
    $totalFiles = count($uploadedFiles['name']);
    
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
            $errorCount++;
            continue;
        }
        
        $fileName = basename($uploadedFiles['name'][$i]);
        $targetPath = $uploadsDir . '/' . $fileName;
        
        if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $targetPath)) {
            try {
                // Read and process MSG file
                $msgData = readMsgFile($targetPath);
                
                if ($msgData) {
                    // Save to database
                    $database = new MsgDatabase();
                    $emailId = $database->processMsgFile($targetPath, $msgData);
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (Exception $e) {
                $errorCount++;
            }
            
            // Clean up uploaded file after processing
            unlink($targetPath);
        } else {
            $errorCount++;
        }
    }
    
    // Redirect to database viewer after processing
    if ($successCount > 0) {
        header('Location: database_viewer.php?msg=' . urlencode("{$successCount} file(s) processed successfully") . '&status=success');
        exit;
    } else {
        header('Location: database_viewer.php?msg=' . urlencode("Failed to process files") . '&status=error');
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSG File Upload</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        .upload-form { 
            background: #f9f9f9; 
            padding: 30px; 
            border-radius: 6px; 
            text-align: center; 
        }
        input[type="file"] { 
            margin: 20px 0; 
            padding: 10px; 
            border: 2px dashed #007cba; 
            border-radius: 4px; 
            width: 100%; 
            max-width: 400px; 
        }
        button { 
            background: #007cba; 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
        }
        button:hover { 
            background: #005a87; 
        }
        .nav-links { 
            margin-bottom: 20px; 
            text-align: center; 
        }
        .nav-links a { 
            background: #6c757d; 
            color: white; 
            padding: 10px 20px; 
            text-decoration: none; 
            border-radius: 4px; 
            margin: 0 5px; 
        }
        .nav-links a:hover { 
            background: #545b62; 
        }
        h1 { 
            text-align: center; 
            color: #333; 
            margin-bottom: 30px; 
        }
        .file-info { 
            font-size: 14px; 
            color: #666; 
            margin-top: 10px; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MSG File Upload</h1>
        
        <div class="nav-links">
            <a href="database_viewer.php">📊 View Database</a>
            <a href="index_2.php">FC Version</a>
        </div>
        
        <div class="upload-form">
            <h3>Select MSG Files to Upload</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="msg_files[]" accept=".msg" multiple required>
                <div class="file-info">You can select multiple MSG files at once</div>
                <br><br>
                <button type="submit" name="upload">Upload & Process Files</button>
            </form>
        </div>
    </div>
</body>
</html>