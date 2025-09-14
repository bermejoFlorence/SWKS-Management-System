<?php
include_once 'includes/auth_adviser.php';
include_once '../database/db_connection.php';

// --- MARK AS SEEN IF notif_id IS PRESENT ---
if (isset($_GET['notif_id'])) {
    $notif_id = intval($_GET['notif_id']);
    $user_id = $_SESSION['user_id'];
    $conn->query("UPDATE notification SET is_seen = 1 WHERE notification_id = $notif_id AND user_id = $user_id");
}
// Get the post ID from URL
$post_id = intval($_GET['post_id'] ?? 0);

// --- GET THE POST DETAILS ---
$post = null;
$stmt = $conn->prepare(
    "SELECT p.*, u.user_role, u.user_email,
     COALESCE(NULLIF(m.full_name,''), NULLIF(a.adviser_fname,''), NULLIF(c.coor_name,''), u.user_email) AS poster_name,
     COALESCE(
        NULLIF(m.profile_picture,''),
        NULLIF(a.profile_pic,''),
        NULLIF(c.profile_pic,''),
        'uploads/default.png'
     ) AS profile_pic
     FROM forum_post p
     JOIN user u ON u.user_id = p.user_id
     LEFT JOIN member_details m ON m.user_id = u.user_id
     LEFT JOIN adviser_details a ON a.user_id = u.user_id
     LEFT JOIN aca_coordinator_details c ON c.user_id = u.user_id
     WHERE p.post_id = ?"
);


$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $post = $result->fetch_assoc();
$stmt->close();

if (!$post) {
    echo "<div class='alert alert-danger m-5'>Post not found or no content.</div>";
    exit;
}

// --- GET COMMENTS FOR THIS POST ---
$comments = [];
$cRes = $conn->query("SELECT c.*, u.user_role,
    COALESCE(m.full_name, a.adviser_fname, coor.coor_name, u.user_email) AS commenter_name
    FROM forum_comment c
    JOIN user u ON u.user_id = c.user_id
    LEFT JOIN member_details m ON m.user_id = u.user_id
    LEFT JOIN adviser_details a ON a.user_id = u.user_id
    LEFT JOIN aca_coordinator_details coor ON coor.user_id = u.user_id
    WHERE c.post_id = $post_id ORDER BY c.created_at ASC");
while ($com = $cRes->fetch_assoc()) $comments[] = $com;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Adviser Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your custom style -->
    <link rel="stylesheet" href="styles/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

     
    <div class="main-content" style="padding-top: 70px;">
        <!-- Back Button, right-aligned -->
        <div class="d-flex justify-content-end mb-2" style="gap:8px;">
            <a href="forum.php" class="btn btn-outline-success d-inline-flex align-items-center"
               style="gap: 6px; border-radius: 15px;">
                <i class="bi bi-arrow-left"></i>
                <span>Back to Forum</span>
            </a>
        </div>

        <!-- Post Card (max width and style matched with forum.php) -->
        <div class="card mb-4 shadow-sm w-100" style="border-radius:18px;">
            <div class="card-body">
                <!-- User Info -->
                <div class="d-flex align-items-center mb-2">
                  <?php
$pic = $post['profile_pic'] ?? '';

if ($pic === '' || $pic === null) {
    $pic = 'uploads/default.png';
}

if (!preg_match('#^https?://#i', $pic)) {
    // If it's just a filename (no slash), put it under uploads/
    if (strpos($pic, '/') === false) {
        $pic = 'uploads/' . $pic;
    }
    // Ensure it starts with /swks/
    if (!preg_match('#^/swks/#', $pic)) {
        // Allow already-correct '/uploads/...'
        if (strpos($pic, '/uploads/') === 0) {
            $pic = '/swks' . $pic;
        } else {
            $pic = '/swks/' . ltrim($pic, '/');
        }
    }
}
?>
<img src="<?= htmlspecialchars($pic) ?>" alt="Avatar"
     class="rounded-circle border border-2" width="52" height="52" style="object-fit:cover;">

                    <div class="ms-3">
                        <span class="fw-semibold" style="font-size:1.17rem;"><?= htmlspecialchars($post['poster_name']) ?></span><br>
                        <span class="text-muted small text-capitalize"><?= htmlspecialchars($post['user_role']) ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars(timeAgo($post['created_at'])) ?></small>
                    </div>
                </div>
                <!-- Attachments -->
                <?php
                $attachments = json_decode($post['attachment'] ?? '[]', true);
                if ($attachments && is_array($attachments)) {
                    echo '<div class="row g-2 justify-content-center">';
                    foreach ($attachments as $file) {
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])) {
                            echo '<div class="col-6 col-md-3 d-flex justify-content-center">
                                    <a href="/swks/' . htmlspecialchars($file) . '" class="post-image-link" data-img="/swks/' . htmlspecialchars($file) . '">
                                        <img src="/swks/' . htmlspecialchars($file) . '" class="img-fluid rounded shadow-sm post-image-thumb" style="height:130px; object-fit:cover;">
                                    </a>
                                </div>';
                        } else {
                            echo '<div class="col-12 mb-1">
                                    <i class="bi bi-paperclip me-1"></i>
                                    <a href="/swks/' . htmlspecialchars($file) . '" target="_blank">' . basename($file) . '</a>
                                </div>';
                        }
                    }
                    echo '</div>';
                }
                ?>
                <!-- Post Title -->
                <?php if (!empty($post['title'])): ?>
                    <h5 class="fw-bold text-success mt-2 mb-1"><?= htmlspecialchars($post['title']) ?></h5>
                <?php endif; ?>
                <!-- Post Content -->
                <p class="mb-0" style="font-size:1.12rem;"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
            </div>
        </div>

        <!-- Comments section -->
        <div class="card shadow-sm mb-5 w-100" style="border-radius:18px;">
            <div class="card-body">
                <h6 class="fw-bold mb-3">Comments</h6>
                    <div class="mb-2" id="commentsList-<?= $post_id ?>">
                        <?php include 'includes/comments_list.php'; ?>
                    </div>
                <!-- Comment Form -->
                <form method="post" action="forum_comment_action.php" class="d-flex align-items-center gap-2 mt-2">
                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                     <input type="hidden" name="from_view" value="1">
                    <textarea name="comment_text" class="form-control" rows="1" placeholder="Write a comment..." required></textarea>
                    <button type="submit" class="btn btn-success">Comment</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Modal (reuse from forum.php if needed) -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
          <img id="modalImage" src="" class="w-100 rounded shadow" style="max-height:80vh;object-fit:contain;">
        </div>
      </div>
    </div>


    <script>
        // Sidebar toggle for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Sidebar auto-close on outside click (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger-btn');
            if(window.innerWidth <= 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && event.target !== hamburger) {
                    sidebar.classList.remove('show');
                }
            }
        });
        // Prevent closing on hamburger click
        document.querySelector('.hamburger-btn').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>
