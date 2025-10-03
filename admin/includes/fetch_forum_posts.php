<?php
include_once __DIR__ . '/../database/db_connection.php';

$sinceId    = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
$orgFilter  = $_GET['organization'] ?? 'SWKS';

$sql = "SELECT p.*, u.user_role, 
               COALESCE(m.full_name, a.adviser_fname, c.coor_name, u.user_email) AS poster_name,
               m.profile_picture AS member_pic, a.profile_pic AS adviser_pic, c.profile_pic AS coor_pic,
               u.org_id AS poster_user_org_id, p.org_id AS org_id,  /* keep p.org_id in result */
               org.org_name AS poster_org
        FROM forum_post p
        JOIN user u ON u.user_id = p.user_id
        LEFT JOIN member_details m ON m.user_id = u.user_id
        LEFT JOIN adviser_details a ON a.user_id = u.user_id
        LEFT JOIN aca_coordinator_details c ON c.user_id = u.user_id
        LEFT JOIN organization org ON org.org_id = u.org_id
        WHERE 1";

$params = [];
$types  = "";

// limit to new posts only
if ($sinceId > 0) {
    $sql   .= " AND p.post_id > ?";
    $types .= "i";
    $params[] = $sinceId;
}

// apply org filter kapag hindi SWKS (All)
if ($orgFilter !== 'SWKS' && $orgFilter !== '') {
    $sql   .= " AND p.org_id = ?";
    $types .= "i";
    $params[] = (int)$orgFilter;
}

$sql .= " ORDER BY p.post_id DESC LIMIT 50";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $bind = array_merge([$types], $params);
    $refs = [];
    foreach ($bind as $k => $v) $refs[$k] = &$bind[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
$stmt->execute();
$res = $stmt->get_result();

$posts = [];
while ($row = $res->fetch_assoc()) $posts[] = $row;

header('Content-Type: application/json');
echo json_encode(['success' => true, 'posts' => $posts]);
