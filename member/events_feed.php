<?php
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

$org_id = $_SESSION['org_id'] ?? 0;
if (!$org_id) { http_response_code(403); echo '[]'; exit; }

// FullCalendar passes ?start=&end=
$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

$sql = "SELECT e.event_id, e.title, e.start_datetime, e.end_datetime, e.all_day,
               e.description, e.color, o.org_name
        FROM org_events e
        JOIN organization o ON o.org_id = e.org_id
        WHERE e.org_id = ?";
$types  = 'i';
$params = [$org_id];

if ($start && $end) {
  $sql   .= " AND (e.start_datetime < ? AND COALESCE(e.end_datetime, e.start_datetime) >= ?)";
  $types .= 'ss';
  $params[] = date('Y-m-d H:i:s', strtotime($end));
  $params[] = date('Y-m-d H:i:s', strtotime($start));
}
$sql .= " ORDER BY e.start_datetime ASC";

$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode([]); exit; }
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $out[] = [
    'id'          => (int)$row['event_id'],
    'title'       => $row['title'],
    'start'       => str_replace(' ', 'T', $row['start_datetime']),
    'end'         => $row['end_datetime'] ? str_replace(' ', 'T', $row['end_datetime']) : null,
    'allDay'      => (bool)$row['all_day'],
    'color'       => $row['color'] ?: null,
    'description' => $row['description'] ?: null,
    'org_name'    => $row['org_name'] ?: 'Organization',
  ];
}
$stmt->close();

echo json_encode($out);
