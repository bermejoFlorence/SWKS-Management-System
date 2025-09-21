<?php
include 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

$org_id  = $_SESSION['org_id']  ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
if (!$org_id || !$user_id) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
  exit;
}

$event_id    = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
$title       = trim($_POST['title'] ?? '');
$startIso    = $_POST['start'] ?? '';
$endIso      = $_POST['end'] ?? '';
$allDay      = isset($_POST['allDay']) && $_POST['allDay'] == '1' ? 1 : 0;
$color       = trim($_POST['color'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '' || $startIso === '') {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Title and start are required']);
  exit;
}

$startDT = date('Y-m-d H:i:s', strtotime($startIso));
$endDT   = $endIso ? date('Y-m-d H:i:s', strtotime($endIso)) : null;
if ($endDT && $endDT < $startDT) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'End must be after start']);
  exit;
}

if ($event_id) {
  // ✅ Only allow update if the event belongs to the same org AND was created by this adviser
  $stmt = $conn->prepare("
    UPDATE org_events
       SET title = ?, start_datetime = ?, end_datetime = ?, all_day = ?, description = ?, color = ?, updated_at = NOW()
     WHERE event_id = ? AND org_id = ? AND created_by = ?
  ");
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Prepare failed']);
    exit;
  }
  // types: sssissiii  (title s, start s, end s, all_day i, desc s, color s, event_id i, org_id i, created_by i)
  $stmt->bind_param('sssissiii', $title, $startDT, $endDT, $allDay, $description, $color, $event_id, $org_id, $user_id);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    echo json_encode(['ok'=>true, 'id'=>$event_id]);
  } else {
    // either not found, not your org, or not the owner
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Not allowed to edit this event']);
  }
  $stmt->close();

} else {
  // Insert: event is owned by the adviser creating it
  $stmt = $conn->prepare("
    INSERT INTO org_events
      (org_id, title, start_datetime, end_datetime, all_day, description, color, created_by)
    VALUES (?,?,?,?,?,?,?,?)
  ");
  if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Prepare failed']);
    exit;
  }
  // types: i s s s i s s i
  $stmt->bind_param('isssissi', $org_id, $title, $startDT, $endDT, $allDay, $description, $color, $user_id);
  $ok = $stmt->execute();
  if ($ok) {
    echo json_encode(['ok'=>true, 'id'=>$conn->insert_id]);
  } else {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Insert failed']);
  }
  $stmt->close();
}
