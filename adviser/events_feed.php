<?php
// admin/events_feed.php
include_once 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();

/* ---- Timezone setup ---- */
date_default_timezone_set('Asia/Manila');
@$conn->query("SET time_zone = '+08:00'");

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$userId = (int)($_SESSION['user_id'] ?? 0);
$TZ_PH  = new DateTimeZone('Asia/Manila');

$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

/* ---------- Query (with label rules) ---------- */
$sql = "SELECT 
          e.event_id,
          e.title,
          e.start_datetime,
          e.end_datetime,
          e.all_day,
          e.description,
          e.color,
          e.org_id,
          e.created_by,
          CASE 
            WHEN e.org_id IS NULL THEN 'ACA Coordinator'
            ELSE CONCAT(COALESCE(o.org_name,'Organization'), ' Adviser')
          END AS org_label
        FROM org_events e
        LEFT JOIN organization o ON o.org_id = e.org_id
        WHERE 1=1";

$types  = '';
$params = [];

/* Window overlap: convert GET params to local SQL DATETIME */
if ($start && $end) {
  try {
    $startPhp = (new DateTime($start))->setTimezone($TZ_PH)->format('Y-m-d H:i:s');
    $endPhp   = (new DateTime($end))->setTimezone($TZ_PH)->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    // fallback if parsing fails
    $startPhp = $start;
    $endPhp   = $end;
  }
  $sql   .= " AND (e.start_datetime < ? AND COALESCE(e.end_datetime, e.start_datetime) >= ?)";
  $types .= 'ss';
  $params[] = $endPhp;
  $params[] = $startPhp;
}

$sql .= " ORDER BY e.start_datetime ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error'=>true,'message'=>'SQL prepare failed','detail'=>$conn->error]);
  exit;
}
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

/* ---------- Helpers ---------- */
function iso_with_offset(?string $sqlDatetime, DateTimeZone $tz): ?string {
  if (!$sqlDatetime) return null;
  $dt = new DateTime($sqlDatetime, $tz);   // interpret stored DATETIME as Manila local
  return $dt->format('Y-m-d\TH:i:sP');     // e.g. 2025-10-03T18:16:00+08:00
}

/* ---------- Emit ---------- */
$out = [];
while ($row = $res->fetch_assoc()) {
  $isOwner = ((int)$row['created_by'] === (int)$userId);
  $allDay  = (bool)$row['all_day'];

  if ($allDay) {
    // For all-day, use date-only start
    $out[] = [
      'id'    => (int)$row['event_id'],
      'title' => $row['title'],
      'start' => substr($row['start_datetime'], 0, 10), // YYYY-MM-DD
      'allDay' => true,
      'backgroundColor' => $row['color'] ?: '#198754',
      'borderColor'     => $row['color'] ?: '#198754',
      'editable'         => $isOwner,
      'startEditable'    => $isOwner,
      'durationEditable' => $isOwner,
      'extendedProps' => [
        'description' => $row['description'] ?: null,
        'org_name'    => $row['org_label'] ?: 'ACA Coordinator',
        'owner_id'    => (int)$row['created_by'],
        'owned'       => $isOwner
      ],
    ];
  } else {
    $out[] = [
      'id'    => (int)$row['event_id'],
      'title' => $row['title'],
      'start' => iso_with_offset($row['start_datetime'], $TZ_PH), // ← +08:00
      'end'   => iso_with_offset($row['end_datetime']   ?: null,  $TZ_PH),
      'allDay' => false,
      'backgroundColor' => $row['color'] ?: '#198754',
      'borderColor'     => $row['color'] ?: '#198754',
      'editable'         => $isOwner,
      'startEditable'    => $isOwner,
      'durationEditable' => $isOwner,
      'extendedProps' => [
        'description' => $row['description'] ?: null,
        'org_name'    => $row['org_label'] ?: 'ACA Coordinator',
        'owner_id'    => (int)$row['created_by'],
        'owned'       => $isOwner
      ],
    ];
  }
}

$stmt->close();
echo json_encode($out);
