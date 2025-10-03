<?php
// member/events_feed.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../database/db_connection.php';

// Kukunin ng FullCalendar ang range via GET ?start=...&end=...
$start = $_GET['start'] ?? null;  // ISO 8601
$end   = $_GET['end']   ?? null;

function toMysqlDt($iso) {
  if (!$iso) return null;
  try {
    $dt = new DateTime($iso);
    return $dt->format('Y-m-d H:i:s');
  } catch (Throwable $e) { return null; }
}

$rangeStart = toMysqlDt($start);
$rangeEnd   = toMysqlDt($end);

// Fallback kung walang naipasa (para sa manual testing)
if (!$rangeStart) $rangeStart = date('Y-m-d 00:00:00', strtotime('-60 days'));
if (!$rangeEnd)   $rangeEnd   = date('Y-m-d 23:59:59', strtotime('+120 days'));

// org ng member mula session
$org_id = (int)($_SESSION['org_id'] ?? 0);

// Ipakita:
//  - events ng org ng member (e.org_id = $_SESSION['org_id'])
//  - optional: global events kung may ganun (e.org_id IS NULL o 0)
$sql = "
  SELECT e.event_id, e.org_id, e.title,
         e.start_datetime, e.end_datetime, e.all_day,
         e.description, e.color,
         o.org_name
  FROM org_events e
  LEFT JOIN organization o ON o.org_id = e.org_id
  WHERE
    (
      (? > 0 AND e.org_id = ?)
      OR e.org_id IS NULL
      OR e.org_id = 0
    )
    AND
    (
      COALESCE(e.end_datetime, e.start_datetime) >= ?
      AND e.start_datetime <= ?
    )
  ORDER BY e.start_datetime ASC
";

$events = [];
if ($stmt = $conn->prepare($sql)) {
  $stmt->bind_param('iiss', $org_id, $org_id, $rangeStart, $rangeEnd);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $allDay = (int)$row['all_day'] === 1;

    // Para sa allDay, ibigay ang YYYY-MM-DD; para sa timed, full datetime
    $startOut = $row['start_datetime'];
    $endOut   = $row['end_datetime'];

    if ($allDay) {
      $startOut = substr($startOut ?? '', 0, 10);
      $endOut   = $endOut ? substr($endOut, 0, 10) : null;
    }

    $events[] = [
      'id'    => (string)$row['event_id'],
      'title' => $row['title'] ?? '',
      'start' => $startOut,
      'end'   => $endOut,
      'allDay'=> $allDay,
      'backgroundColor' => $row['color'] ?: '#198754',
      'borderColor'     => $row['color'] ?: '#198754',
      'extendedProps'   => [
        'description' => $row['description'] ?? '',
        'org_name'    => $row['org_name'] ?: 'Organization',
      ],
    ];
  }
  $stmt->close();
}

echo json_encode($events, JSON_UNESCAPED_UNICODE);
