<?php
// admin/admin_request_action.php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
header('Content-Type: application/json; charset=utf-8');

$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
$rid    = (int)($_POST['request_id'] ?? 0);

if (!$rid || !in_array($action, ['approve','reject','return'], true)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Bad request']);
  exit;
}

/*
  Rule:
  - approve:  from validated -> approved
  - reject:   from validated -> rejected
  - return:   from approved  -> returned
*/
$map = [
  'approve' => ['to' => 'approved', 'from' => 'validated'],
  'reject'  => ['to' => 'rejected', 'from' => 'validated'],
  'return'  => ['to' => 'returned', 'from' => 'approved'],
];

$to   = $map[$action]['to'];
$from = $map[$action]['from'];

$sql = "UPDATE borrow_requests SET status=? WHERE request_id=? AND status=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sis', $to, $rid, $from);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => (bool)$ok, 'new_status' => $to]);
