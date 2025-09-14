<?php
session_start();
include_once '../../database/db_connection.php'; // adjust if needed
$userId = $_SESSION['user_id'] ?? 0;
$count = 0;
if ($userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE user_id = ? AND is_seen = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
}
echo $count;
?>
