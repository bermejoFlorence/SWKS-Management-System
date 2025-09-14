<?php
include 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$org_id   = $_SESSION['org_id']  ?? 0;
$user_id  = $_SESSION['user_id'] ?? 0;
$event_id = (int)($_POST['event_id'] ?? 0);

if (!$org_id || !$user_id || !$event_id) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'msg'=>'Bad request']);
  exit;
}

$stmt = $conn->prepare("
  DELETE FROM org_events
   WHERE event_id = ?
     AND org_id   = ?
     AND created_by = ?
");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'msg'=>'Prepare failed']);
  exit;
}

$stmt->bind_param('iii', $event_id, $org_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
  echo json_encode(['ok'=>true]);
} else {
  // either not found, ibang org, o hindi ikaw ang owner
  http_response_code(403);
  echo json_encode(['ok'=>false, 'msg'=>'Not allowed to delete this event']);
}
$stmt->close();
