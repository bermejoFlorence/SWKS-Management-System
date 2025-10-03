<?php
// admin/events_delete.php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'msg' => 'POST required']);
  exit;
}

$event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
if ($event_id <= 0) {
  echo json_encode(['ok' => false, 'msg' => 'Invalid event id']);
  exit;
}

// Optional guard: i-delete lang kung ikaw ang gumawa
// $user_id = $_SESSION['user_id'] ?? 0;
// $stmt = $conn->prepare("DELETE FROM org_events WHERE event_id=? AND created_by=?");
// $stmt->bind_param('ii', $event_id, $user_id);

$stmt = $conn->prepare("DELETE FROM org_events WHERE event_id=?");
$stmt->bind_param('i', $event_id);
$ok = $stmt->execute();
$stmt->close();

echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Deleted' : 'Delete failed']);
