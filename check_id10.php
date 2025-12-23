<?php
require_once 'config.php';

$db = getDB();
$stmt = $db->prepare('SELECT id, passenger_name, attachment_name FROM passenger_attachments WHERE id = 10');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "ID: " . $row['id'] . " | Passenger: " . $row['passenger_name'] . " | File: " . $row['attachment_name'] . "\n";
} else {
    echo "No record found\n";
}
?>