<?php
// admin/events_feed.php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');
// hard no-cache (avoid stale JSON from browser/proxy)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
$userId = $_SESSION['user_id'] ?? 0;

$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;


/*
  Uses org_events (same as adviser).
  Label rules:
    - org_id IS NULL  -> 'ACA Coordinator'
    - org_id NOT NULL -> CONCAT(org_name, ' Adviser')
*/
$sql = "SELECT 
          e.event_id,
          e.title,
          e.start_datetime,
          e.end_datetime,
          e.all_day,
          e.description,
          e.color,
          e.org_id,
          e.created_by,   -- <--- ADD THIS
          CASE 
            WHEN e.org_id IS NULL THEN 'ACA Coordinator'
            ELSE CONCAT(COALESCE(o.org_name,'Organization'), ' Adviser')
          END AS org_label
        FROM org_events e
        LEFT JOIN organization o ON o.org_id = e.org_id
        WHERE 1=1";
$types  = '';
$params = [];
if ($start && $end) {
  // same overlap logic as adviser feed
  try {
    $endPhp   = (new DateTime($end))->format('Y-m-d H:i:s');
    $startPhp = (new DateTime($start))->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    $endPhp = $end; $startPhp = $start;
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

$out = [];
while ($row = $res->fetch_assoc()) {
  $isOwner = ((int)$row['created_by'] === (int)$userId);

  $out[] = [
    'id'    => (int)$row['event_id'],
    'title' => $row['title'],
    'start' => $row['start_datetime'] ? str_replace(' ', 'T', $row['start_datetime']) : null,
    'end'   => $row['end_datetime']   ? str_replace(' ', 'T', $row['end_datetime'])   : null,
    'allDay' => (bool)$row['all_day'],
    'backgroundColor' => $row['color'] ?: '#198754',
    'borderColor'     => $row['color'] ?: '#198754',

    // 🔒 per-event permissions for FullCalendar (drag/resize/edit UI)
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

$stmt->close();

echo json_encode($out);
