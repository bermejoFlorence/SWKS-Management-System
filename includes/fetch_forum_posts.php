<?php
include 'auth_user.php'; // Or your universal session check
include '../database/db_connection.php';

header('Content-Type: application/json');

$since_id = isset($_GET['since_id']) ? intval($_GET['since_id']) : 0;

// Always SELECT user, member, adviser, coordinator details
$sql = "SELECT 
    p.*,
    u.user_role, 
    COALESCE(m.full_name, a.adviser_fname, c.coor_name, u.user_email) AS poster_name,
    m.profile_picture AS member_pic,
    a.profile_pic AS adviser_pic,
    c.profile_pic AS coor_pic,
    org.org_name AS poster_org
    FROM forum_post p
    JOIN user u ON u.user_id = p.user_id
    LEFT JOIN member_details m ON m.user_id = u.user_id
    LEFT JOIN adviser_details a ON a.user_id = u.user_id
    LEFT JOIN aca_coordinator_details c ON c.user_id = u.user_id
    LEFT JOIN organization org ON org.org_id = u.org_id
    " . ($since_id > 0 ? "WHERE p.post_id > ? " : "") . "
    ORDER BY p.post_id ASC";

$stmt = $since_id > 0 ? $conn->prepare($sql) : $conn->prepare(str_replace('WHERE p.post_id > ? ', '', $sql) . 'LIMIT 10');
if ($since_id > 0) $stmt->bind_param("i", $since_id);

$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    // Normalize all fields for frontend
    $posts[] = [
        'post_id'      => $row['post_id'],
        'org_id'       => $row['org_id'],
        'user_id'      => $row['user_id'],
        'title'        => $row['title'],
        'content'      => $row['content'],
        'attachment'   => $row['attachment'],
        'created_at'   => $row['created_at'],
        // Poster details for rendering:
        'poster_name'  => $row['poster_name'],
        'user_role'    => $row['user_role'],
        'poster_org'   => $row['poster_org'],
        'member_pic'   => $row['member_pic'],
        'adviser_pic'  => $row['adviser_pic'],
        'coor_pic'     => $row['coor_pic']
    ];
}

echo json_encode([
    'success'    => true,
    'posts'      => $posts,
    'latest_id'  => count($posts) ? $posts[array_key_last($posts)]['post_id'] : $since_id
]);
?>
