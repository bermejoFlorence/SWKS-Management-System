<?php
session_start();
include_once '../../database/db_connection.php';
$userId = $_SESSION['user_id'] ?? 0;
$notifId = intval($_GET['id'] ?? 0);
if ($userId && $notifId) {
    $stmt = $conn->prepare("UPDATE notification SET is_seen = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notifId, $userId);
    $stmt->execute();
    $stmt->close();
}
echo "ok";
?>
