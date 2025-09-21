<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$rid    = (int)($_POST['request_id'] ?? 0);

if (!$rid || !in_array($action, ['approve','reject'], true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Bad request']); exit;
}

$newStatus = $action === 'approve' ? 'approved' : 'rejected';
$stmt = $conn->prepare("UPDATE borrow_requests SET status=? WHERE request_id=? AND status='validated'");
$stmt->bind_param('si', $newStatus, $rid);
$ok = $stmt->execute();

echo json_encode(['ok'=>$ok]);
