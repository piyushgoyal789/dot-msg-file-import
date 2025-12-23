<?php
require_once 'config.php';

echo "=== MANUAL PASSENGER NAME UPDATE ===\n\n";

$id = $_GET['id'];

// Get all current attachments
$db = getDB();
$stmt = $db->query("SELECT & FROM passenger_attachments where id = ".$id);


?>