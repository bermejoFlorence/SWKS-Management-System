<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
$event_id = (int)($_POST['event_id'] ?? 0);
if (!$userId || $event_id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Bad request']); exit; }

$stmt = $conn->prepare("DELETE FROM org_events WHERE event_id = ? AND created_by = ?");
if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>'prepare failed','detail'=>$conn->error]); exit; }
$stmt->bind_param('ii', $event_id, $userId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
  echo json_encode(['ok'=>false,'msg'=>'forbidden or not found']);
} else {
  echo json_encode(['ok'=>true]);
}
$stmt->close();
