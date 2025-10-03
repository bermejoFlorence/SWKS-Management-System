<?php
include 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---- Timezone: Asia/Manila (PHP + MySQL session) ---- */
date_default_timezone_set('Asia/Manila');
@$conn->query("SET time_zone = '+08:00'");

header('Content-Type: application/json');

$org_id  = $_SESSION['org_id']  ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;
if (!$org_id || !$user_id) {
  http_response_code(403);
  echo json_encode(['ok'=>false,'msg'=>'Unauthorized']);
  exit;
}

/* ---------- Inputs ---------- */
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

/* ---------- Helpers: parse ISO/local strings as Asia/Manila ---------- */
$TZ_PH = new DateTimeZone('Asia/Manila');

function parse_local_or_iso(?string $s, DateTimeZone $tz): ?DateTime {
  if (!$s) return null;

  // YYYY-MM-DD only (all-day)
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
    return DateTime::createFromFormat('Y-m-d H:i:s', $s.' 00:00:00', $tz);
  }

  // May explicit offset? (Z or +hh:mm / -hh:mm)
  $hasOffset = (bool)preg_match('/(Z|[+\-]\d{2}:\d{2})$/', $s);
  if ($hasOffset) {
    // Respect the embedded offset
    return new DateTime($s);
  }

  // Walang offset ⇒ interpret as Asia/Manila local
  return new DateTime($s, $tz);
}

/* ---------- Normalize datetimes to SQL (local) ---------- */
$startDTobj = parse_local_or_iso($startIso, $TZ_PH);
$endDTobj   = parse_local_or_iso($endIso,   $TZ_PH);

if ($allDay) {
  // For all-day events, store start at 00:00:00; ignore end
  $startDT = $startDTobj ? $startDTobj->format('Y-m-d 00:00:00') : null;
  $endDT   = null;
} else {
  $startDT = $startDTobj ? $startDTobj->format('Y-m-d H:i:s') : null;
  $endDT   = $endDTobj   ? $endDTobj->format('Y-m-d H:i:s')   : null;
}

/* Guard: end before start */
if ($endDT && $startDT && $endDT < $startDT) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'End must be after start']);
  exit;
}

/* Optional guard: end time provided but no start time component */
if (!$allDay && !$startDTobj) {
  http_response_code(422);
  echo json_encode(['ok'=>false,'msg'=>'Invalid start datetime']);
  exit;
}

/* Default color if empty */
if ($color === '') $color = '#198754';

/* ---------- Upsert ---------- */
if ($event_id) {
  // Update only if same org & created by this adviser (owner)
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
  // types: s s s i s s i i i
  $stmt->bind_param('sssissiii', $title, $startDT, $endDT, $allDay, $description, $color, $event_id, $org_id, $user_id);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    echo json_encode(['ok'=>true, 'id'=>$event_id]);
  } else {
    http_response_code(403);
    echo json_encode(['ok'=>false,'msg'=>'Not allowed to edit this event']);
  }
  $stmt->close();

} else {
  // Insert: owned by creating adviser
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
