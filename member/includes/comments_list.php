<?php
include_once __DIR__ . '/../../database/db_connection.php'; // Always use __DIR__ for safe includes
include_once __DIR__ . '/functions.php';

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
$comments = [];

if ($post_id > 0) {
    $sql = "SELECT c.*, 
                u.user_role,
                u.user_email,
                COALESCE(m.full_name, a.adviser_fname, coor.coor_name, u.user_email) AS commenter_name,
                m.profile_picture AS member_pic,
                a.profile_pic AS adviser_pic,
                coor.profile_pic AS coor_pic,
                org.org_name
            FROM forum_comment c
            JOIN user u ON u.user_id = c.user_id
            LEFT JOIN member_details m ON m.user_id = u.user_id
            LEFT JOIN adviser_details a ON a.user_id = u.user_id
            LEFT JOIN aca_coordinator_details coor ON coor.user_id = u.user_id
            LEFT JOIN organization org ON org.org_id = u.org_id
            WHERE c.post_id = ?
            ORDER BY c.created_at ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($com = $result->fetch_assoc()) $comments[] = $com;
    $stmt->close();
}
?>

<div class="mb-2">
<?php if (count($comments)): ?>
    <?php foreach ($comments as $com): ?>
        <?php
            // Proper fallback for profile pic
             $profile_pic = $com['member_pic'] ?: $com['adviser_pic'] ?: $com['coor_pic'];
if (!$profile_pic || trim($profile_pic) === "") {
    $profile_pic = "/swks/uploads/default.jpg"; // fallback local default
} else if (filter_var($profile_pic, FILTER_VALIDATE_URL)) {
    // External URL, use as is
} else {
    // CHECK: if profile_pic already starts with 'uploads/', prepend /swks/
    if (strpos($profile_pic, 'uploads/') === 0) {
        $profile_pic = "/swks/" . $profile_pic;
    } else {
        // If not, prepend /swks/uploads/
        $profile_pic = "/swks/uploads/" . $profile_pic;
    }
}

            // Build the label: org + role
            if ($com['user_role'] == 'member') {
                $roleLabel = ($com['org_name'] ? $com['org_name'] . " " : "") . "Member";
            } elseif ($com['user_role'] == 'adviser') {
                $roleLabel = ($com['org_name'] ? $com['org_name'] . " " : "") . "Adviser";
            } elseif ($com['user_role'] == 'admin') {
                $roleLabel = "Aca Coordinator";
            } else {
                $roleLabel = ucfirst($com['user_role']);
            }
        ?>
        <div class="mb-2 px-3 py-2 bg-light rounded d-flex align-items-start gap-2">
             <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Avatar" width="36" height="36" class="rounded-circle border" style="object-fit:cover;">
            <div>
                <span class="fw-semibold">
                    <?= htmlspecialchars($com['commenter_name']) ?>
                    <small class="text-muted">(<?= htmlspecialchars($roleLabel) ?>)</small>:
                </span>
                <span><?= nl2br(htmlspecialchars($com['comment_text'])) ?></span>
                <?php
                $comment_created = $com['created_at'];
                $now = new DateTime();
                $comment_dt = new DateTime($comment_created);
                $comment_diff = $now->diff($comment_dt);
                if ($comment_diff->days > 7) {
                    $comment_display_time = date("F j, Y h:iA", strtotime($comment_created));
                } else {
                    $comment_display_time = timeAgo($comment_created);
                }
                ?>
                <div class="text-muted small"><?= htmlspecialchars($comment_display_time) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="text-muted mb-2">No comments yet.</div>
<?php endif; ?>
</div>


