<?php
include 'includes/auth_adviser.php';
include_once '../database/db_connection.php';
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? 0;
// FullCalendar passes ?start=&end=
$start = $_GET['start'] ?? null;
$end   = $_GET['end']   ?? null;

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
        WHERE 1=1"; // ← no org filter: show ALL orgs + admin

$types  = '';
$params = [];

if ($start && $end) {
  $sql   .= " AND (e.start_datetime < ? AND COALESCE(e.end_datetime, e.start_datetime) >= ?)";
  $types .= 'ss';
  $params[] = date('Y-m-d H:i:s', strtotime($end));
  $params[] = date('Y-m-d H:i:s', strtotime($start));
}

$sql .= " ORDER BY e.start_datetime ASC";
$stmt = $conn->prepare($sql);
if (!$stmt) { echo json_encode([]); exit; }
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
  $owned = ((int)$row['created_by'] === (int)$user_id);

  $out[] = [
    'id'              => (int)$row['event_id'],
    'title'           => $row['title'],
    'start'           => $row['start_datetime'] ? str_replace(' ', 'T', $row['start_datetime']) : null,
    'end'             => $row['end_datetime']   ? str_replace(' ', 'T', $row['end_datetime'])   : null,
    'allDay'          => (bool)$row['all_day'],
    'backgroundColor' => $row['color'] ?: '#198754',
    'borderColor'     => $row['color'] ?: '#198754',
    // lock drag/resize for non-owners
    'editable'        => $owned,
    'startEditable'   => $owned,
    'durationEditable'=> $owned,
    'extendedProps'   => [
      'description' => $row['description'] ?: null,
      'org_name'    => $row['org_label'] ?: 'ACA Coordinator',
      'owned'       => $owned,
    ],
  ];
}
$stmt->close();

echo json_encode($out);
