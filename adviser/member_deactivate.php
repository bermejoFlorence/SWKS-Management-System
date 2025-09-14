<?php
require_once 'includes/auth_adviser.php';
require_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$org_id    = $_SESSION['org_id'] ?? 0;
$member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;

/* !!!! IMPORTANT: palitan ito para tumugma sa ENUM mo !!!! */
$DEACTIVATED_STATUS = 'deactivated';   // <-- kung ENUM mo ay 'deactivate', palitan dito

if (!$org_id || !$member_id) {
  echo json_encode(['ok' => false, 'msg' => 'Invalid request.']); exit;
}

/* verify member belongs to this org and currently approved */
$stmt = $conn->prepare("
  SELECT member_id FROM member_details
  WHERE member_id = ? AND preferred_org = ? AND status = 'approved'
  LIMIT 1
");
$stmt->bind_param('ii', $member_id, $org_id);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

if ($res->num_rows === 0) {
  echo json_encode(['ok' => false, 'msg' => 'Member not found or not eligible.']); exit;
}

/* update to DEACTIVATED status */
$stmt = $conn->prepare("UPDATE member_details SET status = ? WHERE member_id = ?");
$stmt->bind_param('si', $DEACTIVATED_STATUS, $member_id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode($ok
  ? ['ok' => true, 'msg' => 'Member has been deactivated.']
  : ['ok' => false, 'msg' => 'Database error while deactivating.']
);
