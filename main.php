<?php
require_once 'vendor/autoload.php';
require_once 'config.php';

use Opt\OLE\MsgParser;

// Auto-detect appropriate database class
$databaseClass = 'MsgDatabase';
if (file_exists('MsgDatabase_v3.php')) {
    require_once 'MsgDatabase_v3.php';
    $databaseClass = 'MsgDatabase_v3';
} elseif (file_exists('MsgDatabase_v2.php')) {
    require_once 'MsgDatabase_v2.php';
    $databaseClass = 'MsgDatabase_v2';
} else {
    require_once 'MsgDatabase.php';
}

// Function to read and parse MSG file
function readMsgFile($filePath) {
    try {
        if (!file_exists($filePath)) {
            throw new Exception("MSG file not found: " . $filePath);
        }
        $parser = new MsgParser($filePath);
        return $parser->parse();
    } catch (Exception $e) {
        return null;
    }
}

$message = '';
$messageType = '';
$uploadDetails = '';

// Handle QS Version upload
if (isset($_POST['upload_qs']) && isset($_FILES['msg_files_qs'])) {
    $uploadedFiles = $_FILES['msg_files_qs'];
    $totalFiles = count($uploadedFiles['name']);
    
    $uploadsDir = 'uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errorDetails = [];
    
    for ($i = 0; $i < $totalFiles; $i++) {
        $fileName = basename($uploadedFiles['name'][$i]);
        
        if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
            $errorCount++;
            $errorDetails[] = "Upload failed for {$fileName} (Error code: {$uploadedFiles['error'][$i]})";
            continue;
        }
        
        $targetPath = $uploadsDir . '/' . $fileName;
        
        if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $targetPath)) {
            try {
                $msgData = readMsgFile($targetPath);
                if ($msgData) {
                    require_once 'MsgDatabase.php';
                    $database = new MsgDatabase();
                    $database->processMsgFile($targetPath, $msgData);
                    $successCount++;
                } else {
                    $errorCount++;
                    $errorDetails[] = "Failed to parse {$fileName}";
                }
            } catch (Exception $e) {
                $errorCount++;
                $errorDetails[] = "Processing error for {$fileName}: " . $e->getMessage();
            }
            unlink($targetPath);
        } else {
            $errorCount++;
            $errorDetails[] = "Failed to move uploaded file {$fileName}";
        }
    }
    
    if ($successCount > 0) {
        $message = "QS Version: {$successCount} of {$totalFiles} file(s) processed successfully";
        $messageType = 'success';
    } else {
        $message = "QS Version: No files processed successfully";
        $messageType = 'error';
    }
    
    // Add detailed breakdown
    $uploadDetails = "<strong>Upload Summary:</strong><br>";
    $uploadDetails .= "✅ Successful: {$successCount}<br>";
    $uploadDetails .= "❌ Errors: {$errorCount}<br>";
    if (!empty($errorDetails)) {
        $uploadDetails .= "<br><strong>Error Details:</strong><br>";
        foreach ($errorDetails as $error) {
            $uploadDetails .= "• " . htmlspecialchars($error) . "<br>";
        }
    }
}

// Handle FC Version upload
if (isset($_POST['upload_fc']) && isset($_FILES['msg_files_fc'])) {
    $uploadedFiles = $_FILES['msg_files_fc'];
    $totalFiles = count($uploadedFiles['name']);
    
    $uploadsDir = 'uploads';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    $successCount = 0;
    $errorCount = 0;
    $errorDetails = [];
    
    for ($i = 0; $i < $totalFiles; $i++) {
        $fileName = basename($uploadedFiles['name'][$i]);
        
        if ($uploadedFiles['error'][$i] !== UPLOAD_ERR_OK) {
            $errorCount++;
            $errorDetails[] = "Upload failed for {$fileName} (Error code: {$uploadedFiles['error'][$i]})";
            continue;
        }
        
        $targetPath = $uploadsDir . '/' . $fileName;
        
        if (move_uploaded_file($uploadedFiles['tmp_name'][$i], $targetPath)) {
            try {
                $msgData = readMsgFile($targetPath);
                if ($msgData) {
                    if (class_exists('MsgDatabase_v2')) {
                        require_once 'MsgDatabase_v2.php';
                        $database = new MsgDatabase_v2();
                    } else {
                        require_once 'MsgDatabase.php';
                        $database = new MsgDatabase();
                    }
                    $database->processMsgFile($targetPath, $msgData);
                    $successCount++;
                } else {
                    $errorCount++;
                    $errorDetails[] = "Failed to parse {$fileName}";
                }
            } catch (Exception $e) {
                $errorCount++;
                $errorDetails[] = "Processing error for {$fileName}: " . $e->getMessage();
            }
            unlink($targetPath);
        } else {
            $errorCount++;
            $errorDetails[] = "Failed to move uploaded file {$fileName}";
        }
    }
    
    if ($successCount > 0) {
        $message = "FC Version: {$successCount} of {$totalFiles} file(s) processed successfully";
        $messageType = 'success';
    } else {
        $message = "FC Version: No files processed successfully";
        $messageType = 'error';
    }
    
    // Add detailed breakdown
    $uploadDetails = "<strong>Upload Summary:</strong><br>";
    $uploadDetails .= "✅ Successful: {$successCount}<br>";
    $uploadDetails .= "❌ Errors: {$errorCount}<br>";
    if (!empty($errorDetails)) {
        $uploadDetails .= "<br><strong>Error Details:</strong><br>";
        foreach ($errorDetails as $error) {
            $uploadDetails .= "• " . htmlspecialchars($error) . "<br>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSG File Processor</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background-color: #f5f5f5; 
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        h1 { 
            text-align: center; 
            color: #333; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #007bff; 
            padding-bottom: 10px; 
        }
        .upload-section { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 30px; 
            margin-bottom: 40px; 
        }
        .upload-form { 
            padding: 25px; 
            border-radius: 8px; 
            text-align: center; 
            border: 2px solid #ddd;
        }
        .upload-form.qs { 
            background: #f0f8ff; 
            border-color: #007cba; 
        }
        .upload-form.fc { 
            background: #f0fff0; 
            border-color: #28a745; 
        }
        .upload-form h3 { 
            margin-top: 0; 
            margin-bottom: 15px; 
        }
        .upload-form.qs h3 { 
            color: #007cba; 
        }
        .upload-form.fc h3 { 
            color: #28a745; 
        }
        input[type="file"] { 
            margin: 15px 0; 
            padding: 10px; 
            border: 2px dashed #ccc; 
            border-radius: 4px; 
            width: 100%; 
            max-width: 300px; 
        }
        .upload-form.qs input[type="file"] { 
            border-color: #007cba; 
        }
        .upload-form.fc input[type="file"] { 
            border-color: #28a745; 
        }
        button { 
            padding: 12px 25px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px; 
            font-weight: bold;
        }
        .btn-qs { 
            background: #007cba; 
            color: white; 
        }
        .btn-qs:hover { 
            background: #005a87; 
        }
        .btn-fc { 
            background: #28a745; 
            color: white; 
        }
        .btn-fc:hover { 
            background: #1e7e34; 
        }
        .file-info { 
            font-size: 12px; 
            color: #666; 
            margin-top: 8px; 
        }
        .message { 
            padding: 15px; 
            border-radius: 4px; 
            margin-bottom: 20px; 
            text-align: center;
        }
        .message.success { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
        }
        .message.error { 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24; 
        }
        .passenger-section { 
            border-top: 2px solid #ddd; 
            padding-top: 30px; 
        }
        .passenger-section h2 { 
            color: #333; 
            margin-bottom: 20px; 
        }
        .search-form { 
            margin-bottom: 20px; 
            padding: 15px; 
            background: #f8f9fa; 
            border-radius: 6px; 
        }
        .search-form input[type="text"] { 
            padding: 8px; 
            margin-right: 10px; 
            width: 300px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        .btn { 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 4px; 
            border: none; 
            cursor: pointer; 
            font-size: 12px;
        }
        .btn-primary { 
            background: #007bff; 
            color: white; 
        }
        .btn-secondary { 
            background: #6c757d; 
            color: white; 
            margin-left: 10px; 
        }
        .btn-success { 
            background: #28a745; 
            color: white; 
        }
        .btn-danger { 
            background: #dc3545; 
            color: white; 
        }
        .btn-sm { 
            padding: 5px 10px; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0; 
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: bold; 
        }
        tr:hover { 
            background-color: #f5f5f5; 
        }
        .no-data { 
            text-align: center; 
            color: #666; 
            font-style: italic; 
            padding: 40px; 
        }
        .admin-links { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .admin-links a { 
            background: #6c757d; 
            color: white; 
            padding: 8px 16px; 
            text-decoration: none; 
            border-radius: 4px; 
            margin: 0 5px; 
            font-size: 12px;
        }
        .admin-links a:hover { 
            background: #545b62; 
        }
        .admin-links a.danger { 
            background: #dc3545; 
        }
        .admin-links a.danger:hover { 
            background: #c82333; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MSG File Processor</h1>
        


        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <strong><?php echo $messageType === 'success' ? '✅ Success!' : '❌ Error!'; ?></strong> 
            <?php echo htmlspecialchars($message); ?>
            <?php if ($uploadDetails): ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1);">
                    <?php echo $uploadDetails; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="upload-section">
            <div class="upload-form qs">
                <h3>QS Version Upload</h3>
                <p style="margin: 10px 0; color: #666; font-size: 13px;">Standard MSG processing</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="msg_files_qs[]" accept=".msg" multiple required>
                    <div class="file-info">Select multiple MSG files</div>
                    <br><br>
                    <button type="submit" name="upload_qs" class="btn-qs">Process with QS</button>
                </form>
            </div>

            <div class="upload-form fc">
                <h3>FC Version Upload</h3>
                <p style="margin: 10px 0; color: #666; font-size: 13px;">Enhanced passenger name extraction</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="msg_files_fc[]" accept=".msg" multiple required>
                    <div class="file-info">Select multiple MSG files</div>
                    <br><br>
                    <button type="submit" name="upload_fc" class="btn-fc">Process with FC</button>
                </form>
            </div>
        </div>

        <div class="passenger-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0;">Passenger List</h2>
                <a href="clear_main.php" class="btn btn-danger" onclick="return confirm('Are you sure you want to empty all tables? This action cannot be undone.');" style="padding: 10px 20px;">Empty Table</a>
            </div>
            
            <?php
            $searchPassenger = $_GET['passenger'] ?? '';
            ?>
            
            <div class="search-form">
                <form method="GET">
                    <input type="text" name="passenger" placeholder="Search passenger name..." value="<?php echo htmlspecialchars($searchPassenger); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if ($searchPassenger): ?>
                        <a href="?" class="btn btn-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php
            try {
                $database = new $databaseClass();
                $attachments = $database->getPassengerAttachments($searchPassenger);
                
                if (!empty($attachments)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Passenger Name</th>
                                <th>Download</th>
                                <th>Attach</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachments as $attachment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($attachment['passenger_name']); ?></strong></td>
                                <td>
                                    <a href="download_attachment.php?id=<?php echo $attachment['id']; ?>" class="btn btn-success btn-sm">
                                        📥 Download
                                    </a>
                                </td>
                                <td>
                                    <?php if (isset($attachment['is_attached']) && $attachment['is_attached'] == 1): ?>
                                        <span style='color: #28a745; font-weight: bold;'>✓ Attached</span>
                                    <?php else: ?>
                                        <a href="https://staff.gordonbooking.com/rests/attach_ticket_from_msg/?id=<?php echo $attachment['id']; ?>" class="btn btn-primary btn-sm">
                                            🔗 Attach
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($attachment['created_at'])); ?></td>
                                <td>
                                    <a href="delete_attachment.php?id=<?php echo $attachment['id']; ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('Are you sure you want to delete this attachment for <?php echo htmlspecialchars($attachment['passenger_name'], ENT_QUOTES); ?>?');">
                                        🗑️ Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <?php if ($searchPassenger): ?>
                            <p>No attachments found for passenger: <strong><?php echo htmlspecialchars($searchPassenger); ?></strong></p>
                        <?php else: ?>
                            <p>No passenger data in database yet.</p>
                            <p>Use the upload forms above to process MSG files.</p>
                        <?php endif; ?>
                    </div>
                <?php endif;
            } catch (Exception $e) {
                echo "<div style='color: red; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>";
                echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
                echo "</div>";
            }
            ?>
        </div>
    </div>
</body>
</html>