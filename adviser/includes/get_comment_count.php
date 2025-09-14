<?php
// includes/get_comment_count.php
include_once '../../database/db_connection.php';
$post_id = intval($_GET['post_id'] ?? 0);
$count = 0;
if ($post_id) {
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM forum_comment WHERE post_id = $post_id");
    if ($res && $row = $res->fetch_assoc()) $count = (int)$row['cnt'];
}
header('Content-Type: application/json');
echo json_encode(['count' => $count]);
