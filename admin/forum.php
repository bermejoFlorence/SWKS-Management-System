<?php
include 'includes/auth_admin.php';
include '../database/db_connection.php';
include 'includes/functions.php';

// Filter logic
$orgFilter = $_GET['organization'] ?? 'SWKS';
$selectedOrg = $orgFilter;

// Get organizations for select
$orgOptions = '<option value="">Select Organization</option>';
$orgOptions .= '<option value="SWKS"' . ($selectedOrg === 'SWKS' || $selectedOrg === '' ? ' selected' : '') . '>SWKS (All)</option>';

$sql = "SELECT org_id, org_name FROM organization ORDER BY org_name ASC";
$result_org = $conn->query($sql);
if ($result_org && $result_org->num_rows > 0) {
    while ($row = $result_org->fetch_assoc()) {
        $sel = ($selectedOrg === (string)$row['org_id']) ? 'selected' : '';
        $orgOptions .= '<option value="' . $row['org_id'] . '" ' . $sel . '>' . htmlspecialchars($row['org_name']) . '</option>';
    }
} else {
    $orgOptions = '<option disabled>No organizations found</option>';
}

// Fetch forum posts
$baseSql = "SELECT 
    p.*, 
    u.user_role, 
    u.user_email, 
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
LEFT JOIN organization org ON org.org_id = u.org_id";

$params = [];
$types  = "";

if ($orgFilter !== 'SWKS' && $orgFilter !== '') {
    $baseSql .= " WHERE p.org_id = ? ";
    $types .= "i";
    $params[] = (int)$orgFilter;
}

$baseSql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($baseSql);
if (!empty($params)) {
    $bindParams = array_merge([$types], $params);
    $tmp = [];
    foreach ($bindParams as $key => $value) {
        $tmp[$key] = &$bindParams[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}

$stmt->execute();
$result = $stmt->get_result();

$postIds = [];
$posts = [];
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
    $postIds[] = $row['post_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ACA Coordinator Forum</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        .main-content {
            padding: 90px 22px 40px;
            background: var(--swks-green-pale);
            min-height: 100vh;
            transition: margin-left 0.25s;
        }

        /* ========== FORUM HEADER ========== */
        .forum-header {
            background: white;
            border-radius: 24px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(76, 175, 80, 0.06);
            border: 1px solid var(--swks-green-light);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .forum-header:hover {
            box-shadow: 0 8px 30px rgba(76, 175, 80, 0.1);
        }

        .forum-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--swks-green);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.5px;
        }

        .forum-title i {
            font-size: 1.6rem;
        }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }

        .filter-bar .form-select,
        .filter-bar .form-control {
            border-radius: 50px !important;
            padding: 0.6rem 1.2rem !important;
            border: 2px solid var(--swks-green-light) !important;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .filter-bar .form-select:focus,
        .filter-bar .form-control:focus {
            border-color: var(--swks-green) !important;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1) !important;
        }

        /* ========== POST FORM ========== */
        .post-form-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(76, 175, 80, 0.06);
            border: 1px solid var(--swks-green-light);
            padding: 2rem;
            margin-bottom: 2.5rem;
            transition: all 0.3s ease;
        }

        .post-form-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(76, 175, 80, 0.1);
        }

        .post-form-title {
            font-weight: 700;
            color: var(--swks-green-dark);
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }

        #forumPostForm {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        #forumPostForm .form-control {
            border-radius: 14px !important;
            padding: 0.8rem 1rem !important;
            border: 2px solid #e0e0e0 !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }

        #forumPostForm .form-control:focus {
            border-color: var(--swks-green) !important;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1) !important;
        }

        .attachment-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .attachment-preview img {
            max-width: 80px;
            max-height: 80px;
            border-radius: 8px;
            border: 2px solid var(--swks-green-light);
            transition: all 0.3s ease;
        }

        .attachment-preview img:hover {
            transform: scale(1.1);
            border-color: var(--swks-green);
        }

        .btn-attach {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--swks-green-light);
            color: var(--swks-green);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .btn-attach:hover {
            background: var(--swks-green);
            color: white;
            transform: translateY(-2px);
        }

        .btn-post {
            background: var(--swks-green-accent);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.7rem 2rem;
            font-weight: 700;
            font-size: 1.05rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(129, 199, 132, 0.3);
        }

        .btn-post:hover {
            background: var(--swks-green);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        }

        /* ========== POST CARD ========== */
        .forum-post-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 4px 20px rgba(76, 175, 80, 0.06);
            border: 1px solid var(--swks-green-light);
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .forum-post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(76, 175, 80, 0.1);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .user-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--swks-green-light);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            border-color: var(--swks-green);
        }

        .user-details h5 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--swks-green-dark);
            margin-bottom: 0.2rem;
        }

        .user-details .text-muted {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        /* Attachments */
        .attachments-container {
            margin: 1.5rem 0;
            border-radius: 16px;
            overflow: hidden;
        }

        .attachments-container .row {
            margin: 0;
        }

        .attachments-container img {
            transition: all 0.3s ease;
            cursor: zoom-in;
            border-radius: 12px;
        }

        .attachments-container img:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        /* Post Content */
        .post-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--swks-green);
            margin: 1rem 0 0.5rem;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .post-title:hover {
            color: var(--swks-green-dark);
        }

        .post-content {
            font-size: 1.05rem;
            line-height: 1.7;
            color: var(--text-dark);
            margin: 1rem 0;
        }

        /* Actions */
        .post-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--swks-green-light);
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: none;
            border: none;
            color: var(--swks-green);
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        .action-btn:hover {
            background: var(--swks-green-light);
            transform: translateX(4px);
        }

        .action-btn i {
            font-size: 1.2rem;
        }

        /* Comments Section */
        .comments-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--swks-green-light);
        }

        .comments-section .form-control {
            border-radius: 50px !important;
            padding: 0.8rem 1.2rem !important;
            border: 2px solid var(--swks-green-light) !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }

        .comments-section .form-control:focus {
            border-color: var(--swks-green) !important;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1) !important;
        }

        .btn-comment {
            background: var(--swks-green);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .btn-comment:hover {
            background: var(--swks-green-dark);
            transform: translateY(-2px);
        }

        /* No Results */
        #noResultsMsg {
            background: white;
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            font-size: 1.2rem;
            color: var(--text-muted);
            box-shadow: 0 4px 20px rgba(76, 175, 80, 0.06);
            border: 2px dashed var(--swks-green-light);
            margin: 3rem 0;
        }

        /* Image Modal */
        #imageModal .modal-content {
            border-radius: 24px !important;
            box-shadow: 0 16px 48px rgba(0,0,0,0.2) !important;
        }

        #modalImage {
            border-radius: 18px;
            max-height: 80vh;
            object-fit: contain;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 80px 16px 30px;
            }
            .forum-header {
                padding: 1.2rem;
            }
            .forum-title {
                font-size: 1.5rem;
            }
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-bar .form-select,
            .filter-bar .form-control {
                width: 100% !important;
                max-width: 100% !important;
            }
            .post-form-card {
                padding: 1.5rem;
            }
            .forum-post-card {
                padding: 1.5rem;
            }
            .user-avatar {
                width: 48px;
                height: 48px;
            }
            .user-details h5 {
                font-size: 1rem;
            }
            .post-title {
                font-size: 1.2rem;
            }
            .post-actions {
                flex-wrap: wrap;
                gap: 1rem;
            }
            .action-btn {
                font-size: 0.95rem;
                padding: 0.4rem 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .forum-title {
                font-size: 1.3rem;
            }
            .btn-post {
                width: 100%;
                font-size: 1rem;
                padding: 0.6rem 1.5rem;
            }
            .btn-attach {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container">
            <!-- Forum Header -->
            <div class="forum-header">
                <?php
                $forumTitle = "SWKS FORUM";
                if (isset($_GET['organization']) && $_GET['organization'] != "") {
                    if ($_GET['organization'] == "SWKS") {
                        $forumTitle = "SWKS FORUM";
                    } else {
                        $org_id_sel = $_GET['organization'];
                        $org_name = "";
                        $org_query = $conn->prepare("SELECT org_name FROM organization WHERE org_id=? LIMIT 1");
                        $org_query->bind_param("s", $org_id_sel);
                        $org_query->execute();
                        $org_query->bind_result($org_name);
                        if ($org_query->fetch()) {
                            $forumTitle = strtoupper($org_name) . " FORUM";
                        }
                        $org_query->close();
                    }
                }
                ?>
                <h1 class="forum-title">
                    <i class="bi bi-chat-square-text"></i>
                    <?= htmlspecialchars($forumTitle) ?>
                </h1>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="get" class="d-flex flex-wrap gap-2 align-items-center w-100">
                        <select class="form-select" name="organization" onchange="this.form.submit()" style="flex: 1; min-width: 200px;">
                            <?= $orgOptions ?>
                        </select>
                        <select class="form-select" name="month" style="max-width: 140px;">
                            <option>Month</option>
                            <?php
                            $months = ["January", "February", "March", "April", "May", "June",
                                "July", "August", "September", "October", "November", "December"];
                            foreach ($months as $m) echo "<option>$m</option>";
                            ?>
                        </select>
                        <select class="form-select" name="year" style="max-width: 100px;">
                            <option>Year</option>
                            <?php
                            $yearNow = date("Y");
                            for ($y = $yearNow; $y >= $yearNow-5; $y--) echo "<option>$y</option>";
                            ?>
                        </select>
                        <input type="text" id="forumSearch" class="form-control" placeholder="Search posts..." style="flex: 1; min-width: 200px;">
                    </form>
                </div>
            </div>

            <!-- Post Form -->
            <div class="post-form-card">
                <h3 class="post-form-title">Share Something with the Community</h3>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        Your post has been published!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        There was an error posting. Please try again.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="forumPostForm" action="forum_post_action.php<?= isset($_GET['organization']) ? '?organization=' . urlencode($_GET['organization']) : ''; ?>" method="post" enctype="multipart/form-data" autocomplete="off">
                    <input type="text" class="form-control" name="title" placeholder="Title (optional)">
                    <textarea class="form-control" name="post_content" rows="3" placeholder="Write something meaningful..." required></textarea>
                    <div class="attachment-preview" id="attachment-preview"></div>

                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                        <label class="btn-attach" title="Attach file">
                            <i class="bi bi-paperclip"></i>
                            <input type="file" name="attachments[]" accept="image/*,.pdf,.doc,.docx" hidden multiple>
                        </label>
                        <button type="submit" class="btn btn-post">Post to Forum</button>
                    </div>
                    <small class="text-muted d-block mt-2">You may attach images, PDFs, or documents.</small>
                </form>
            </div>

            <!-- No Results Message -->
            <div id="noResultsMsg" class="text-center" style="display: none;">
                <i class="bi bi-search fs-1 mb-3 text-muted"></i>
                <h4>No forum posts found</h4>
                <p class="text-muted">Try adjusting your filters or search terms.</p>
            </div>

            <!-- Forum Posts -->
            <div id="forumPostsList">
                <?php foreach ($posts as $row): ?>
                    <?php
                    $post_id = $row['post_id'];
                    $commentCount = 0;
                    $cResult = $conn->query("SELECT COUNT(*) AS cnt FROM forum_comment WHERE post_id = $post_id");
                    if ($cResult && $cResult->num_rows) {
                        $commentCount = $cResult->fetch_assoc()['cnt'];
                    }
                    ?>
                    <div class="forum-post-card" id="post-<?= $post_id ?>" data-month="<?= date('F', strtotime($row['created_at'])) ?>" data-year="<?= date('Y', strtotime($row['created_at'])) ?>" data-org_id="<?= htmlspecialchars($row['org_id']) ?>">
                        <!-- User Info -->
                        <div class="user-info">
                            <?php
                            $profile_pic = $row['member_pic'] ?: $row['adviser_pic'] ?: $row['coor_pic'] ?: 'uploads/default.jpg';
                            if ($profile_pic && !preg_match('#^https?://#', $profile_pic)) {
                                if (!str_starts_with($profile_pic, 'uploads/')) {
                                    $profile_pic = "uploads/" . ltrim($profile_pic, '/');
                                }
                                $profile_pic = "/swks/" . ltrim($profile_pic, '/');
                            }
                            ?>
                            <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Avatar" class="user-avatar">
                            <div class="user-details">
                                <?php $posterLabel = formatPosterLabel($row['user_role'], $row['poster_org'], $row['poster_name']); ?>
                                <h5><?= htmlspecialchars($posterLabel) ?></h5>
                                <small class="text-muted">
                                    <?php
                                    $created_at = $row['created_at'];
                                    $now = new DateTime();
                                    $created = new DateTime($created_at);
                                    $interval = $now->diff($created);
                                    if ($interval->days > 7) {
                                        $displayTime = date("F j, Y h:iA", strtotime($created_at));
                                    } else {
                                        $displayTime = timeAgo($created_at);
                                    }
                                    ?>
                                    <?= htmlspecialchars($displayTime) ?>
                                </small>
                            </div>
                        </div>

                        <!-- Attachments -->
                        <?php
                        $attachments = json_decode($row['attachment'] ?? '[]', true);
                        if ($attachments && is_array($attachments)): ?>
                            <div class="attachments-container">
                                <div class="row g-2">
                                    <?php foreach ($attachments as $file):
                                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'jfif'])): ?>
                                            <div class="col-6 col-md-3">
                                                <a href="/swks/<?= htmlspecialchars($file) ?>" class="post-image-link" data-img="/swks/<?= htmlspecialchars($file) ?>">
                                                    <img src="/swks/<?= htmlspecialchars($file) ?>" class="img-fluid" style="height: 140px; object-fit: cover;">
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <i class="bi bi-paperclip me-2"></i>
                                                <a href="/swks/<?= htmlspecialchars($file) ?>" target="_blank" class="text-decoration-none"><?= basename($file) ?></a>
                                            </div>
                                        <?php endif;
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Post Title & Content -->
                        <?php if (!empty($row['title'])): ?>
                            <a href="view_forum_post.php?post_id=<?= $row['post_id'] ?>" class="post-title text-decoration-none">
                                <?= htmlspecialchars($row['title']) ?>
                            </a>
                        <?php endif; ?>
                        <div class="post-content">
                            <?= nl2br(htmlspecialchars($row['content'])) ?>
                        </div>

                        <!-- Actions -->
                        <div class="post-actions">
                            <button class="action-btn comment-toggle" data-post-id="<?= $post_id ?>">
                                <i class="bi bi-chat-left-text"></i>
                                <span id="comment-count-<?= $post_id ?>"><?= $commentCount ?></span> Comments
                            </button>
                        </div>

                        <!-- Comments Section -->
                        <div class="collapse comments-section" id="comments-<?= $post_id ?>">
                            <div id="commentsList-<?= $post_id ?>">
                                <div class="text-muted">Loading comments...</div>
                            </div>
                            <form method="post" action="forum_comment_action.php" class="commentForm d-flex gap-2 mt-3">
                                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                <textarea name="comment_text" class="form-control" rows="1" placeholder="Write a thoughtful comment..." required></textarea>
                                <button type="submit" class="btn btn-comment">Comment</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <img id="modalImage" src="" class="w-100 rounded">
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Forum Post Submit
        $('#forumPostForm').on('submit', function(e) {
            e.preventDefault();
            var form = this;
            var $btn = $(form).find('button[type="submit"]');
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Posting...');
            var formData = new FormData(form);

            $.ajax({
                url: $(form).attr('action'),
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    $btn.prop('disabled', false).html('Post to Forum');
                    form.reset();
                    $('#attachment-preview').html('');
                    location.reload();
                },
                error: function() {
                    $btn.prop('disabled', false).html('Post to Forum');
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to post',
                        text: 'Please try again.',
                        confirmButtonColor: '#4caf50'
                    });
                }
            });
        });

        // File Preview
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.querySelector('input[name="attachments[]"]');
            const preview = document.getElementById('attachment-preview');
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    preview.innerHTML = "";
                    if (fileInput.files && fileInput.files.length > 0) {
                        Array.from(fileInput.files).forEach(file => {
                            const fileType = file.type;
                            const fileName = file.name;
                            if (fileType.startsWith('image/')) {
                                const img = document.createElement('img');
                                img.src = URL.createObjectURL(file);
                                img.style.maxWidth = "80px";
                                img.style.maxHeight = "80px";
                                img.className = "rounded border";
                                preview.appendChild(img);
                            }
                            const icon = fileType.startsWith('image/') ? '<i class="bi bi-image"></i>' : '<i class="bi bi-paperclip"></i>';
                            const span = document.createElement('span');
                            span.className = "small ms-2";
                            span.innerHTML = icon + ' ' + fileName;
                            preview.appendChild(span);
                        });
                    }
                });
            }
        });

        // Comment Toggle
        $(document).on('click', '.comment-toggle', function() {
            let postId = $(this).data('post-id');
            let el = $('#comments-' + postId);
            el.toggleClass('show');
            
            if (el.hasClass('show')) {
                $('#commentsList-' + postId).load('includes/comments_list.php?post_id=' + postId);
            }
        });

        // Comment Submit
        $(document).on('submit', '.commentForm', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $form.find('button[type="submit"]');
            var post_id = $form.find('input[name="post_id"]').val();

            $btn.prop('disabled', true).text('Posting...');
            $.ajax({
                url: $form.attr('action'),
                type: "POST",
                data: $form.serialize(),
                success: function(response) {
                    $btn.prop('disabled', false).text('Comment');
                    $form[0].reset();
                    $('#commentsList-' + post_id).load('includes/comments_list.php?post_id=' + post_id);
                    $.get('includes/get_comment_count.php', { post_id: post_id }, function(data) {
                        $('#comment-count-' + post_id).text(data.count);
                    }, 'json');
                },
                error: function() {
                    $btn.prop('disabled', false).text('Comment');
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed to comment',
                        text: 'Please try again.',
                        confirmButtonColor: '#4caf50'
                    });
                }
            });
        });

        // Live Search & Filters
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('forumSearch');
            const monthSelect = document.querySelector('select[name="month"]');
            const yearSelect = document.querySelector('select[name="year"]');
            const orgSelect = document.querySelector('select[name="organization"]');

            function filterPosts() {
                const searchVal = searchInput.value.trim().toLowerCase();
                const monthVal = monthSelect.value;
                const yearVal = yearSelect.value;
                const orgVal = orgSelect.value;

                let visibleCount = 0;
                document.querySelectorAll('.forum-post-card').forEach(card => {
                    const text = card.innerText.toLowerCase();
                    const cardMonth = card.getAttribute('data-month');
                    const cardYear = card.getAttribute('data-year');
                    const cardOrg = card.getAttribute('data-org_id');

                    let show = true;
                    if (searchVal && !text.includes(searchVal)) show = false;
                    if (monthVal && monthVal !== "Month" && cardMonth !== monthVal) show = false;
                    if (yearVal && yearVal !== "Year" && cardYear !== yearVal) show = false;
                    if (orgVal && cardOrg !== orgVal && orgVal !== "SWKS") show = false;

                    card.style.display = show ? "" : "none";
                    if (show) visibleCount++;
                });

                document.getElementById('noResultsMsg').style.display = (visibleCount === 0) ? "block" : "none";
            }

            [searchInput, monthSelect, yearSelect, orgSelect].forEach(el => {
                if(el) {
                    if (el.tagName === "SELECT") {
                        el.addEventListener('change', filterPosts);
                    } else {
                        el.addEventListener('input', filterPosts);
                    }
                }
            });
        });

        // Image Modal
        $(document).on('click', '.post-image-link', function(e) {
            e.preventDefault();
            $('#modalImage').attr('src', $(this).data('img'));
            $('#imageModal').modal('show');
        });

        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const hamburger = document.querySelector('.hamburger-btn');
            if(window.innerWidth <= 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(event.target) && event.target !== hamburger) {
                    sidebar.classList.remove('show');
                }
            }
        });

        document.querySelector('.hamburger-btn')?.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>