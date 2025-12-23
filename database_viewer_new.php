<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Passenger</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .nav-links { margin-bottom: 20px; text-align: center; }
        .nav-links a { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 0 5px; }
        .nav-links a:hover { background: #545b62; }
        .filter-form { margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 6px; }
        .filter-form input[type="text"] { padding: 8px; margin-right: 10px; width: 300px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { padding: 8px 16px; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; margin-left: 10px; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <div class="container">
        <h1>View Passenger</h1>
        
        <div class="nav-links">
            <a href="index.php">QS Upload</a>
            <a href="index_2.php">FC Upload</a>
            <a href="clear_database.php" onclick="return confirm('Are you sure you want to clear all database records? This action cannot be undone.');">🗑️ Clear Database</a>
        </div>

<?php
require_once 'config.php';

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

// Check for message in query parameters
if (isset($_GET['msg']) && !empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $status = isset($_GET['status']) ? $_GET['status'] : 'success';
    
    if ($status === 'success') {
        echo "<div style='margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;'>";
        echo "<strong>✅ Success!</strong> " . $message;
        echo "</div>";
    } else {
        echo "<div style='margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>";
        echo "<strong>❌ Error!</strong> " . $message;
        echo "</div>";
    }
}

try {
    $database = new $databaseClass();
    
    // Handle search/filter
    $searchPassenger = $_GET['passenger'] ?? '';
    
    echo "<div class='filter-form'>";
    echo "<h3>Search Passengers</h3>";
    echo "<form method='GET'>";
    echo "<input type='text' name='passenger' placeholder='Search passenger name...' value='" . htmlspecialchars($searchPassenger) . "'>";
    echo "<input type='submit' value='Search' class='btn btn-primary'>";
    if ($searchPassenger) {
        echo "<a href='database_viewer.php' class='btn btn-secondary'>Clear</a>";
    }
    echo "</form>";
    echo "</div>";
    
    // Get passenger attachments
    $attachments = $database->getPassengerAttachments($searchPassenger);
    
    if (!empty($attachments)) {
        echo "<h2>Passenger Attachments</h2>";
        echo "<table>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>Passenger Name</th>";
        echo "<th>Download</th>";
        echo "<th>Date Created</th>";
        echo "<th>Actions</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($attachments as $attachment) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($attachment['passenger_name']) . "</strong></td>";
            echo "<td>";
            echo "<a href='download_attachment.php?id=" . $attachment['id'] . "' class='btn btn-success btn-sm'>";
            echo "📥 Download";
            echo "</a>";
            echo "</td>";
            echo "<td>" . date('Y-m-d H:i', strtotime($attachment['created_at'])) . "</td>";
            echo "<td>";
            echo "<a href='delete_attachment.php?id=" . $attachment['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this attachment for " . htmlspecialchars($attachment['passenger_name'], ENT_QUOTES) . "?\");'>";
            echo "🗑️ Delete";
            echo "</a>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
    } else {
        echo "<h2>No Passenger Data Found</h2>";
        if ($searchPassenger) {
            echo "<p>No attachments found for passenger: <strong>" . htmlspecialchars($searchPassenger) . "</strong></p>";
        } else {
            echo "<p>No passenger attachment data in database. Upload and process MSG files first.</p>";
        }
        echo "<div style='text-align: center; margin: 30px 0;'>";
        echo "<a href='index.php' class='btn btn-primary' style='margin: 5px;'>Upload MSG Files (QS)</a>";
        echo "<a href='index_2.php' class='btn btn-primary' style='margin: 5px;'>Upload MSG Files (FC)</a>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>";
    echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

    </div>
</body>
</html>