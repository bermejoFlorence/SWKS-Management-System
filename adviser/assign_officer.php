<?php
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

$org_id    = $_SESSION['org_id'] ?? 0;
$member_id = $_POST['member_id'] ?? 0;
$position  = trim($_POST['position'] ?? '');

if (!$org_id || !$member_id || $position === '') {
  echo json_encode(['ok' => false, 'msg' => 'Invalid input.']);
  exit;
}

try {
  // Check if position already exists
  $check = $conn->prepare("
    SELECT id FROM org_officers 
    WHERE org_id = ? AND position = ?
    LIMIT 1
  ");
  $check->bind_param('is', $org_id, $position);
  $check->execute();
  $check->store_result();

  if ($check->num_rows > 0) {
    echo json_encode(['ok' => false, 'msg' => 'This position is already assigned.']);
    exit;
  }
  $check->close();

  // Insert
  $stmt = $conn->prepare("
    INSERT INTO org_officers (org_id, member_id, position)
    VALUES (?, ?, ?)
  ");
  $stmt->bind_param('iis', $org_id, $member_id, $position);

  if ($stmt->execute()) {
    echo json_encode(['ok' => true, 'msg' => 'Officer assigned successfully.']);
  } else {
    echo json_encode(['ok' => false, 'msg' => 'Failed to assign officer.']);
  }

  $stmt->close();

} catch (Exception $e) {
  echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}