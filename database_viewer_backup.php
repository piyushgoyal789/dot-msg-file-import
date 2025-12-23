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
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid #28a745;
        }
        .filter-form {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .base64-preview {
            background: #f1f3f4;
            padding: 8px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 12px;
            max-height: 100px;
            overflow-y: auto;
            word-break: break-all;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        .btn {
            padding: 8px 16px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        .bulk-actions {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .checkbox-column {
            width: 50px;
            text-align: center;
        }
        .attach-all-btn {
            background: #17a2b8;
            color: white;
            margin-left: 10px;
        }
        .attach-all-btn:hover {
            background: #138496;
        }
        .attach-all-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #1e7e34; }
    </style>
</head>
<body>
    <div class="container">
        <h1>MSG Database Viewer</h1>
        <div style="margin: 20px 0;">
            <a href="index.php" class="btn btn-secondary">← Back to QS Version</a>
            <a href="index_2.php" class="btn btn-primary" style="margin-left: 10px;">← Back to FC Version</a>
            <a href="clear_database.php" class="btn btn-danger" style="margin-left: 10px;" onclick="return confirm('Are you sure you want to clear all database records? This action cannot be undone.');">🗑️ Clear Database</a>
        </div>

<?php
require_once 'config.php';
require_once 'MsgDatabase.php';

// Check for message in query parameters
if (isset($_GET['msg']) && !empty($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
    $status = isset($_GET['status']) ? $_GET['status'] : 'success'; // Default to success
    
    if ($status === 'success') {
        // Green success message
        echo "<div style='margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;'>";
        echo "<strong>✅ Success!</strong> " . $message;
        echo "</div>";
    } elseif ($status === 'failed') {
        // Red error message
        echo "<div style='margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>";
        echo "<strong>❌ Error!</strong> " . $message;
        echo "</div>";
    }
}

// Check for clear database status messages
if (isset($_GET['status'])) {
    $status = $_GET['status'];
    
    if ($status === 'clear_success') {
        echo "<div style='margin: 20px 0; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;'>";
        echo "<strong>🎉 Database Cleared!</strong> All tables have been successfully cleared and reset.";
        echo "</div>";
    } elseif ($status === 'clear_failed') {
        $error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : 'Unknown error';
        echo "<div style='margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>";
        echo "<strong>❌ Failed to Clear Database!</strong> " . $error;
        echo "</div>";
    }
}

function formatBytes($size, $precision = 2) {
    if ($size <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

try {
    $database = new MsgDatabase();
    
    // Handle search/filter
    $searchPassenger = $_GET['passenger'] ?? '';
    
    echo "<div class='filter-form'>";
    echo "<h3>Search & Filter</h3>";
    echo "<form method='GET'>";
    echo "<input type='text' name='passenger' placeholder='Search passenger name...' value='" . htmlspecialchars($searchPassenger) . "' style='padding: 8px; margin-right: 10px; width: 300px;'>";
    echo "<input type='submit' value='Search' class='btn btn-primary'>";
    if ($searchPassenger) {
        echo "<a href='database_viewer.php' class='btn btn-secondary'>Clear</a>";
    }
    echo "</form>";
    echo "</div>";
    
    // Display statistics
    $stats = $database->getAttachmentStats();
    echo "<h2>Database Statistics</h2>";
    echo "<div class='stats-grid'>";
    echo "<div class='stat-card'>";
    echo "<h4>Total PDF Attachments</h4>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #007bff;'>" . ($stats['total_attachments'] ?? 0) . "</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<h4>Unique Passengers (PDF)</h4>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #28a745;'>" . ($stats['unique_passengers'] ?? 0) . "</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<h4>Unique Emails</h4>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #ffc107;'>" . ($stats['unique_emails'] ?? 0) . "</div>";
    echo "</div>";
    echo "<div class='stat-card'>";
    echo "<h4>Total Data Size</h4>";
    echo "<div style='font-size: 24px; font-weight: bold; color: #dc3545;'>" . formatBytes($stats['total_size'] ?? 0) . "</div>";
    echo "</div>";
    echo "</div>";
    
    // Get passenger attachments
    $attachments = $database->getPassengerAttachments($searchPassenger);
    
    if (!empty($attachments)) {
        echo "<h2>PDF Attachments";
        if ($searchPassenger) {
            echo " - Filtered by: " . htmlspecialchars($searchPassenger);
        }
        echo " (" . count($attachments) . ")</h2>";
        
        echo "<div class='bulk-actions'>";
        echo "<label><input type='checkbox' id='selectAllTop'> Select All</label>";
        echo "<button id='attachAllBtn' class='btn attach-all-btn' disabled>🔗 Attach All Selected</button>";
        echo "<span id='selectedCount' style='margin-left: 15px; color: #666;'>0 selected</span>";
        echo "</div>";
        
        echo "<table id='attachmentsTable'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th class='checkbox-column'><input type='checkbox' id='selectAll'></th>";
        echo "<th>Passenger Name</th>";
        echo "<th>Actions</th>";
        echo "<th>Created</th>";
        echo "<th>Attach</th>";
        echo "<th>Delete</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";
        
        foreach ($attachments as $attachment) {
            // Only show PDF attachments
            $fileExt = strtolower(pathinfo($attachment['attachment_name'], PATHINFO_EXTENSION));
            if ($fileExt !== 'pdf' && $attachment['attachment_type'] !== 'application/pdf') {
                continue; // Skip non-PDF files
            }
            
            echo "<tr>";
            echo "<td class='checkbox-column'>";
            // Only show checkbox for unattached items
            if ($attachment['is_attached'] != 1) {
                echo "<input type='checkbox' class='row-checkbox' data-id='" . $attachment['id'] . "' data-url='https://staff.gordonbooking.com/rests/attach_ticket_from_msg/?id=" . $attachment['id'] . "'>";
            }
            echo "</td>";
            echo "<td><strong>" . htmlspecialchars($attachment['passenger_name']) . "</strong></td>";
            echo "<td>";
            
            // Add download button for PDFs
            echo "<a href='download_attachment.php?id=" . $attachment['id'] . "' class='btn btn-success btn-sm'>";
            echo "📥 Download";
            echo "</a>";
            echo "</td>";
            echo "<td>" . date('Y-m-d H:i', strtotime($attachment['created_at'])) . "</td>";
            echo "<td>";
            
            // Check if already attached
            if ($attachment['is_attached'] == 1) {
                echo "<span style='color: #28a745; font-weight: bold;'>✓ Attached</span>";
            } else {
                // Add attach link to Gordon Booking system
                echo "<a href='https://staff.gordonbooking.com/rests/attach_ticket_from_msg/?id=" . $attachment['id'] . "' class='btn btn-primary btn-sm'>";
                echo "🔗 Attach";
                echo "</a>";
            }
            echo "</td>";
            echo "<td>";
            
            // Add delete button with confirmation
            echo "<a href='delete_attachment.php?id=" . $attachment['id'] . "' class='btn btn-danger btn-sm' onclick='return confirm(\"Are you sure you want to delete this attachment for " . htmlspecialchars($attachment['passenger_name'], ENT_QUOTES) . "?\");'>";
            echo "🗑️ Delete";
            echo "</a>";
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</tbody>";
        echo "</table>";
        
    } else {
        echo "<h2>No Data Found</h2>";
        if ($searchPassenger) {
            echo "<p>No attachments found for passenger: <strong>" . htmlspecialchars($searchPassenger) . "</strong></p>";
        } else {
            echo "<p>No passenger attachment data in database. Upload and process MSG files first.</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;'>";
    echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

    </div>

<script>
$(document).ready(function() {
    // Sync both select all checkboxes
    $('#selectAll, #selectAllTop').change(function() {
        var isChecked = $(this).prop('checked');
        $('#selectAll, #selectAllTop').prop('checked', isChecked);
        $('.row-checkbox').prop('checked', isChecked);
        updateSelectedCount();
        updateAttachButton();
    });
    
    // Handle individual checkbox changes
    $(document).on('change', '.row-checkbox', function() {
        updateSelectAllState();
        updateSelectedCount();
        updateAttachButton();
    });
    
    // Update select all state based on individual checkboxes
    function updateSelectAllState() {
        var totalCheckboxes = $('.row-checkbox').length;
        var checkedCheckboxes = $('.row-checkbox:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#selectAll, #selectAllTop').prop('indeterminate', false);
            $('#selectAll, #selectAllTop').prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#selectAll, #selectAllTop').prop('indeterminate', false);
            $('#selectAll, #selectAllTop').prop('checked', true);
        } else {
            $('#selectAll, #selectAllTop').prop('indeterminate', true);
        }
    }
    
    // Update selected count display
    function updateSelectedCount() {
        var count = $('.row-checkbox:checked').length;
        $('#selectedCount').text(count + ' selected');
    }
    
    // Update attach button state
    function updateAttachButton() {
        var hasSelected = $('.row-checkbox:checked').length > 0;
        $('#attachAllBtn').prop('disabled', !hasSelected);
    }
    
    // Handle attach all button click
    $('#attachAllBtn').click(function() {
        var selectedCheckboxes = $('.row-checkbox:checked');
        
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one attachment to attach.');
            return;
        }
        
        if (!confirm('Are you sure you want to attach ' + selectedCheckboxes.length + ' selected attachments?')) {
            return;
        }
        
        // Disable button during processing
        $(this).prop('disabled', true).text('🔄 Processing...');
        
        var totalRequests = selectedCheckboxes.length;
        var completedRequests = 0;
        var successfulAttachments = 0;
        var failedAttachments = [];
        
        // Process each selected attachment
        selectedCheckboxes.each(function() {
            var checkbox = $(this);
            var attachmentId = checkbox.data('id');
            var url = checkbox.data('url');
            var row = checkbox.closest('tr');
            
            $.ajax({
                url: url,
                method: 'GET',
                timeout: 30000,
                success: function(response) {
                    successfulAttachments++;
                    // Update the row to show as attached
                    row.find('.row-checkbox').remove();
                    row.find('td').eq(4).html('<span style="color: #28a745; font-weight: bold;">✓ Attached</span>');
                },
                error: function(xhr, status, error) {
                    failedAttachments.push({
                        id: attachmentId,
                        error: error || 'Unknown error'
                    });
                },
                complete: function() {
                    completedRequests++;
                    
                    // When all requests are complete
                    if (completedRequests === totalRequests) {
                        // Reset button
                        $('#attachAllBtn').prop('disabled', false).text('🔗 Attach All Selected');
                        
                        // Show results
                        var message = 'Bulk attach completed:\n\n';
                        message += 'Successful: ' + successfulAttachments + '\n';
                        
                        if (failedAttachments.length > 0) {
                            message += 'Failed: ' + failedAttachments.length + '\n\n';
                            message += 'Failed attachments:\n';
                            failedAttachments.forEach(function(failure) {
                                message += '- ID ' + failure.id + ': ' + failure.error + '\n';
                            });
                        }
                        
                        alert(message);
                        
                        // Update UI states
                        updateSelectedCount();
                        updateAttachButton();
                    }
                }
            });
        });
    });
});
</script>

</body>
</html>