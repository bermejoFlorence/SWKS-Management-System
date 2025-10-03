<?php
require_once __DIR__ . 'database/db_connection.php';
header('Content-Type: application/json');

$email = trim($_GET['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>false, 'exists'=>false]); exit;
}

$stmt = $conn->prepare("SELECT user_id, user_role FROM user WHERE user_email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) { echo json_encode(['ok'=>true,'exists'=>false]); exit; }

$user_id = (int)$row['user_id'];
$role    = (string)$row['user_role'];

$deactivated = false;
if ($role === 'member') {
  // kung hindi pa linked ang user_id sa member_details, gumamit ng email fallback
  $q = $conn->prepare("
    SELECT status 
    FROM member_details 
    WHERE user_id = ? OR email = ?
    ORDER BY date_submitted DESC 
    LIMIT 1
  ");
  $q->bind_param('is', $user_id, $email);
  $q->execute();
  $r = $q->get_result()->fetch_assoc();
  $q->close();

  $status = strtolower($r['status'] ?? '');
  if (in_array($status, ['deactivated','inactive','disabled'])) $deactivated = true;
}

echo json_encode([
  'ok'          => true,
  'exists'      => true,
  'role'        => $role,
  'deactivated' => $deactivated
]);
