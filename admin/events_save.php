<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo json_encode(['ok'=>false,'msg'=>'Not authenticated']); exit; }

// Inputs
$event_id   = trim($_POST['event_id'] ?? '');
$title      = trim($_POST['title'] ?? '');
$startIn    = trim($_POST['start'] ?? ''); // "YYYY-MM-DD" or "YYYY-MM-DDTHH:MM"
$endIn      = trim($_POST['end'] ?? '');
$allDay     = isset($_POST['allDay']) ? (int)$_POST['allDay'] : 1;
$color      = trim($_POST['color'] ?? '#198754');
$desc       = trim($_POST['description'] ?? '');

// Basic validation
if ($title === '' || $startIn === '') {
  echo json_encode(['ok'=>false,'msg'=>'Title and start are required']); exit;
}

// Normalize datetime
function norm_dt($s) {
  if ($s === '' || $s === null) return null;
  $s = str_replace('T', ' ', $s);
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) $s .= ' 00:00:00';
  try { return (new DateTime($s))->format('Y-m-d H:i:s'); }
  catch (Throwable $e) { return null; }
}
$startDt = norm_dt($startIn);
$endDt   = norm_dt($endIn);
if ($startDt === null) { echo json_encode(['ok'=>false,'msg'=>'Invalid start datetime']); exit; }
if ($endDt !== null && $endDt < $startDt) $endDt = null;

// Admin creates global events => org_id = NULL (ACA Coordinator)
$orgId = null;

// INSERT vs UPDATE (owner-only on update)
if ($event_id === '') {
  // INSERT (created_by = current user; org_id NULL)
  $sql = "INSERT INTO org_events
            (title, start_datetime, end_datetime, all_day, description, color, org_id, created_by)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
  $stmt = $conn->prepare($sql);
  if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>'prepare failed','detail'=>$conn->error]); exit; }

  // bind: org_id is NULL -> use i with null via set null param
  // MySQLi tip: pass null with 'i' is okay; engine will store NULL if column is nullable.
  $stmt->bind_param(
    'sssissii',
    $title, $startDt, $endDt, $allDay, $desc, $color, $orgId, $userId
  );
  $ok = $stmt->execute();
  if (!$ok) { echo json_encode(['ok'=>false,'msg'=>'insert failed','detail'=>$stmt->error]); exit; }
  $stmt->close();
  echo json_encode(['ok'=>true, 'mode'=>'insert']); exit;

} else {
  // UPDATE — owner-only
  $sql = "UPDATE org_events
          SET title = ?, start_datetime = ?, end_datetime = ?, all_day = ?, description = ?, color = ?
          WHERE event_id = ? AND created_by = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>'prepare failed','detail'=>$conn->error]); exit; }
  $stmt->bind_param('sssissii', $title, $startDt, $endDt, $allDay, $desc, $color, $event_id, $userId);
  $ok = $stmt->execute();
  if (!$ok || $stmt->affected_rows === 0) {
    // either forbidden (not owner) or no changes
    echo json_encode(['ok'=>false,'msg'=>'forbidden or not found']); exit;
  }
  $stmt->close();
  echo json_encode(['ok'=>true, 'mode'=>'update']); exit;
}
