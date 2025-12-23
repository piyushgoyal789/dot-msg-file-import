<?php
require_once 'config.php';

$db = getDB();
$stmt = $db->prepare('UPDATE passenger_attachments SET passenger_name = ? WHERE id = ?');
$result = $stmt->execute(['Rosental Meir Mr', 10]);

echo $result ? "Successfully updated passenger name for ID 10 to: Rosental Meir Mr\n" : "Failed to update\n";
?>