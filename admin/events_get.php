<?php
// admin/events_get.php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo json_encode(['ok' => false, 'msg' => 'Invalid id']);
  exit;
}

$sql = "SELECT event_id, org_id, title, start_datetime, end_datetime, all_day, description, color, created_by
        FROM org_events
        WHERE event_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  echo json_encode(['ok' => false, 'msg' => 'Event not found']);
  exit;
}

// NOTE: ibalik ang oras na nasa DB *as-is* (local string "YYYY-MM-DD HH:MM:SS")
// para walang timezone shift sa toPH_HM().
echo json_encode([
  'ok'          => true,
  'id'          => (int)$row['event_id'],
  'org_id'      => $row['org_id'],
  'title'       => $row['title'] ?? '',
  'start'       => $row['start_datetime'] ?? '',
  'end'         => $row['end_datetime'] ?? '',
  'allDay'      => (int)$row['all_day'] === 1,
  'description' => $row['description'] ?? '',
  'color'       => $row['color'] ?? '#198754',
  'created_by'  => (int)$row['created_by'],
]);
