<?php
include_once __DIR__ . '/../../database/db_connection.php';
include_once __DIR__ . '/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$userId = $_SESSION['user_id'] ?? 0;
$notifications = [];

// Helper to format name + role + org

if ($userId) {
  $notifSql = "SELECT n.*, fp.title, 
    u.user_role AS poster_role,
    u.user_id   AS post_author_id,  -- << ADD THIS
    COALESCE(m.full_name, a.adviser_fname, c.coor_name, u.user_email) AS poster_name,
    org_u.org_name AS poster_org,

    cu.user_role AS commenter_role,
    COALESCE(cm.full_name, ca.adviser_fname, cc.coor_name, cu.user_email) AS commenter_name,
    org_cu.org_name AS commenter_org
FROM notification n
LEFT JOIN forum_post fp ON fp.post_id = n.post_id
LEFT JOIN user u ON fp.user_id = u.user_id
LEFT JOIN member_details m ON m.user_id = u.user_id
LEFT JOIN adviser_details a ON a.user_id = u.user_id
LEFT JOIN aca_coordinator_details c ON c.user_id = u.user_id
LEFT JOIN organization org_u ON org_u.org_id = u.org_id
LEFT JOIN forum_comment fc ON fc.comment_id = n.comment_id
LEFT JOIN user cu ON fc.user_id = cu.user_id
LEFT JOIN member_details cm ON cm.user_id = cu.user_id
LEFT JOIN adviser_details ca ON ca.user_id = cu.user_id
LEFT JOIN aca_coordinator_details cc ON cc.user_id = cu.user_id
LEFT JOIN organization org_cu ON org_cu.org_id = cu.org_id
WHERE n.user_id = ?
ORDER BY n.is_seen ASC, n.created_at DESC
LIMIT 10";


    $notifStmt = $conn->prepare($notifSql);
    $notifStmt->bind_param("i", $userId);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    while ($row = $notifResult->fetch_assoc()) $notifications[] = $row;
    $notifStmt->close();
}
?>

<li class="px-3 py-2 border-bottom bg-light fw-bold" style="font-size:1rem;">
    Notifications
</li>
<?php if (count($notifications)): ?>
    <?php foreach ($notifications as $notif): ?>
        <?php
        $notif_class = !$notif['is_seen'] ? 'fw-bold bg-light' : 'text-muted';
        $notifTime = timeAgo($notif['created_at']);

        // Pick proper name/role/org
        if ($notif['type'] === 'forum_comment') {
    $actorName    = formatNotifActor($notif['commenter_role'], $notif['commenter_org'], $notif['commenter_name']);
    $recipientId  = (int)$userId;
    $postAuthorId = (int)($notif['post_author_id'] ?? 0);
    $postAuthorLbl= formatPosterLabel($notif['poster_role'], $notif['poster_org'], $notif['poster_name']);

    if ($recipientId === $postAuthorId) {
        $notifMsg = "<b>" . htmlspecialchars($actorName) . "</b> commented on your post: "
                  . "<span class='text-success'>" . htmlspecialchars($notif['title']) . "</span>";
    } else {
        $notifMsg = "<b>" . htmlspecialchars($actorName) . "</b> commented on "
                  . "<b>" . htmlspecialchars($postAuthorLbl) . "</b>'s post: "
                  . "<span class='text-success'>" . htmlspecialchars($notif['title']) . "</span>";
    }
}
 elseif ($notif['type'] === 'forum_post') {
            $actorName = formatPosterLabel($notif['poster_role'], $notif['poster_org'], $notif['poster_name']);
            $notifMsg = "<b>" . htmlspecialchars($actorName) . "</b> posted: <span class='text-success'>" . htmlspecialchars($notif['title']) . "</span>";
        } elseif ($notif['type'] === 'announcement') {
            $notifMsg = "New announcement: <span class='text-success'>" . htmlspecialchars($notif['title']) . "</span>";
        } elseif ($notif['type'] === 'approval') {
            $notifMsg = "Your membership was approved!";
        } else {
            $notifMsg = htmlspecialchars($notif['message']);
        }
        ?>
        <li>
            <a href="view_forum_post.php?post_id=<?= (int)$notif['post_id'] ?>&notif_id=<?= (int)$notif['notification_id'] ?>"
                class="dropdown-item d-flex justify-content-between align-items-start <?= $notif_class ?>"
                style="white-space:normal;">
                <div>
                    <div><?= $notifMsg ?></div>
                    <small class="text-muted"><?= $notifTime ?></small>
                </div>
                <?php if (!$notif['is_seen']): ?>
                    <span class="badge bg-success ms-2 align-self-center" style="font-size:0.75em;">new</span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
<?php else: ?>
    <li>
        <span class="dropdown-item text-muted small">No notifications yet.</span>
    </li>
<?php endif; ?>

