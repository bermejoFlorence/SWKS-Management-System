<?php
include 'includes/auth_admin.php';
include '../database/db_connection.php'; // adjust path as needed
include 'includes/functions.php';

// ---- SUNOD NA ANG DATABASE QUERIES at LOGIC ----
// 'SWKS' = All orgs
$orgFilter = $_GET['organization'] ?? 'SWKS';

// Get organizations for select (with selected state)
$selectedOrg = $orgFilter;
$orgOptions = '';
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

// ---- FETCH FORUM POSTS (dynamic WHERE) ----
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

// Add org filter only if not SWKS (All) and not empty
if ($orgFilter !== 'SWKS' && $orgFilter !== '') {
    $baseSql .= " WHERE p.org_id = ? ";
    $types .= "i";
    $params[] = (int)$orgFilter;
}

$baseSql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($baseSql);
if (!empty($params)) {
    // bind_param requires references
    $bindParams = array_merge([$types], $params);
    $tmp = [];
    foreach ($bindParams as $key => $value) {
        $tmp[$key] = &$bindParams[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}

$stmt->execute();
$result = $stmt->get_result();

// Prepare post IDs for JS AJAX comments loading
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
    <title>Aca Coordinator Forum</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles/style.css">
    <style>
        @media (min-width: 768px) {
            .container { padding-right: 36px !important; padding-left: 24px !important; }
        }
        .post-image-thumb { transition: transform 0.15s cubic-bezier(.4,2,.3,1); cursor: zoom-in; }
        .post-image-thumb:hover {
            transform: scale(1.06); z-index:2; box-shadow:0 2px 16px rgba(0,0,0,.15);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content" style="padding-top: 70px;">
        <div id="forumPostsContainer">
            <!-- Top Bar: Title + Filters -->
            <div class="row align-items-center mb-3">
                <div class="col-md-5 col-12">
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
                    <div class="align-items-center">
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars($forumTitle) ?></h4>
                    </div>
                </div>
          <div class="col-md-7 col-12 mt-2 mt-md-0 d-flex justify-content-md-end">
            <form method="get" class="d-flex flex-nowrap align-items-center" style="overflow-x:auto;">
                <select class="form-select me-2" name="organization" onchange="this.form.submit()" style="max-width: 210px;">
                    <option value="">Select Organization</option>
                    <option value="SWKS" <?= ($selectedOrg === 'SWKS' || $selectedOrg === '') ? 'selected' : '' ?>>SWKS</option>
                    <?= $orgOptions ?>
                </select>
                <!-- Month -->
                <select class="form-select me-2" name="month" style="max-width: 100px;">
                    <option>Month</option>
                    <?php
                    $months = ["January", "February", "March", "April", "May", "June",
                        "July", "August", "September", "October", "November", "December"];
                    foreach ($months as $m) echo "<option>$m</option>";
                    ?>
                </select>
                <!-- Year -->
                <select class="form-select me-2" name="year" style="max-width: 100px;">
                    <option>Year</option>
                    <?php
                    $yearNow = date("Y");
                    for ($y = $yearNow; $y >= $yearNow-5; $y--) echo "<option>$y</option>";
                    ?>
                </select>
                <!-- Search Bar -->
                <input type="text" id="forumSearch" class="form-control ms-2" placeholder="Search posts..." style="max-width: 220px;">
            </form>
        </div>

            </div>
            <!-- Post Form -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success mb-2">Your post has been published!</div>
                    <?php elseif (isset($_GET['error'])): ?>
                        <div class="alert alert-danger mb-2">There was an error posting. Please try again.</div>
                    <?php endif; ?>
                    <form id="forumPostForm" action="forum_post_action.php<?= isset($_GET['organization']) ? '?organization=' . urlencode($_GET['organization']) : ''; ?>" method="post" enctype="multipart/form-data" class="d-flex flex-column flex-md-row align-items-center gap-2" autocomplete="off">
                        <div class="w-100 mb-2 mb-md-0">
                            <input type="text" class="form-control mb-2" name="title" placeholder="Title (optional)">
                            <textarea class="form-control" name="post_content" rows="2" placeholder="Write something"></textarea>
                            <div id="attachment-preview" class="mt-1 ms-1"></div>
                        </div>
                        <label class="btn btn-outline-secondary mb-2 mb-md-0 d-flex align-items-center justify-content-center" style="max-width: 44px; height: 44px;" title="Attach file">
                            <i class="bi bi-paperclip"></i>
                            <input type="file" name="attachments[]" accept="image/*,.pdf,.doc,.docx" hidden multiple>
                        </label>
                        <button type="submit" class="btn btn-primary ms-md-2 px-4">Post</button>
                    </form>
                    <small class="text-muted d-block mt-2 ms-1">You may attach an image, PDF, or document.</small>
                </div>
            </div>
            <div id="noResultsMsg" class="alert alert-warning text-center mt-3" style="display: none;">
                No forum posts found matching your search.
            </div>
            <!-- Forum Post Cards -->
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
               <div class="card forum-post-card mb-4 shadow-sm w-100"
                    style="border-radius:18px;"
                    id="post-<?= $post_id ?>"
                    data-month="<?= date('F', strtotime($row['created_at'])) ?>"
                    data-year="<?= date('Y', strtotime($row['created_at'])) ?>"
                    data-org_id="<?= htmlspecialchars($row['org_id']) ?>">
                    <div class="card-body">
                        <!-- User Info -->
                  <div class="d-flex align-items-center mb-2">
    <?php
$profile_pic = $row['member_pic'] ?: $row['adviser_pic'] ?: $row['coor_pic'] ?: 'uploads/default.jpg';

if ($profile_pic && !preg_match('#^https?://#', $profile_pic)) {
    // Kung filename lang (walang "uploads/")
    if (!str_starts_with($profile_pic, 'uploads/')) {
        $profile_pic = "uploads/" . ltrim($profile_pic, '/');
    }
    // Final path with /swks/
    $profile_pic = "/swks/" . ltrim($profile_pic, '/');
}
?>
<img src="<?= htmlspecialchars($profile_pic) ?>"
     alt="Avatar" class="rounded-circle border border-2" width="52" height="52" style="object-fit:cover;">

    <div class="ms-3">
        <?php $posterLabel = formatPosterLabel($row['user_role'], $row['poster_org'], $row['poster_name']); ?>
            <span class="fw-semibold" style="font-size:1.07rem;">
                <?= htmlspecialchars($posterLabel) ?>
            </span><br>

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
            <small class="text-muted"><?= htmlspecialchars($displayTime) ?></small>
        </small>
    </div>
</div>
                        <!-- Attachments -->
                        <?php
                        $attachments = json_decode($row['attachment'] ?? '[]', true);
                        if ($attachments && is_array($attachments)) {
                            echo '<div class="row g-2 justify-content-center">';
                            foreach ($attachments as $file) {
                                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'jfif'])) {
                                    echo '<div class="col-6 col-md-3 d-flex justify-content-center">
                                            <a href="/swks/'.htmlspecialchars($file).'" class="post-image-link" data-img="/swks/'.htmlspecialchars($file).'">
                                                <img src="/swks/'.htmlspecialchars($file).'" class="img-fluid rounded shadow-sm post-image-thumb" style="height:130px; object-fit:cover;">
                                            </a>
                                        </div>';
                                } else {
                                    echo '<div class="col-12 mb-1">
                                            <i class="bi bi-paperclip me-1"></i> 
                                            <a href="'.htmlspecialchars($file).'" target="_blank">'.basename($file).'</a>
                                        </div>';
                                }
                            }
                            echo '</div>';
                        }
                        ?>
                        <!-- Post Title (optional) -->
                        <?php if (!empty($row['title'])): ?>
                            <h5 class="fw-bold text-success mt-2 mb-1">
                                <a href="view_forum_post.php?post_id=<?= $row['post_id'] ?>" 
                                class="text-success text-decoration-none"
                                style="cursor:pointer;">
                                    <?= htmlspecialchars($row['title']) ?>
                                </a>
                            </h5>
                        <?php endif; ?>
                        <!-- Post Content -->
                        <p class="mb-0" style="font-size:1.12rem;"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                        <!-- Comment icon and count -->
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <span class="me-2 comment-toggle" style="cursor:pointer;" data-post-id="<?= $post_id ?>">
                                <i class="bi bi-chat-left-text fs-5"></i>
                                <span class="ms-1" id="comment-count-<?= $post_id ?>"><?= $commentCount ?></span>
                            </span>
                        </div>
                        <!-- Comments AJAX loader & Form -->
                        <div class="collapse mt-3" id="comments-<?= $post_id ?>">
                            <div class="mb-2" id="commentsList-<?= $post_id ?>">
                                <div class="text-muted">Loading comments...</div>
                            </div>
                            <form method="post" action="forum_comment_action.php" class="commentForm d-flex align-items-center gap-2">
                                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                <textarea name="comment_text" class="form-control" rows="1" placeholder="Write a comment..." required></textarea>
                                <button type="submit" class="btn btn-sm btn-success">Comment</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
          <img id="modalImage" src="" class="w-100 rounded shadow" style="max-height:80vh;object-fit:contain;">
        </div>
      </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
            // AJAX post submit (Forum)
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
                $btn.prop('disabled', false).html('Post');
                form.reset();
                $('#attachment-preview').html('');
                $('#forumPostsContainer').load(location.href + ' #forumPostsContainer > *');
            },
            error: function() {
                $btn.prop('disabled', false).html('Post');
                alert('Failed to post! Try again.');
            }
        });
    });
    </script>
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

        
    document.addEventListener('DOMContentLoaded', function() {
        // Your other DOM JS here...
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
                            img.style.maxWidth = "90px";
                            img.style.maxHeight = "90px";
                            img.className = "me-2 mb-1 border rounded";
                            preview.appendChild(img);
                        }
                        const icon = fileType.startsWith('image/')
                            ? '<i class="bi bi-image me-1"></i>'
                            : '<i class="bi bi-paperclip me-1"></i>';
                        const span = document.createElement('span');
                        span.className = "small me-2";
                        span.innerHTML = icon + fileName;
                        preview.appendChild(span);
                    });
                }
            });
        }
    });

$(document).on('click', '.comment-toggle', function() {
    let postId = $(this).data('post-id');
    let el = $('#comments-' + postId);

    el.toggleClass('show');
    if (el.hasClass('show')) {
        // Opened: start polling!
        $('#commentsList-' + postId).load('includes/comments_list.php?post_id=' + postId);
        startCommentPolling(postId);
    } else {
        // Closed: stop polling!
        stopCommentPolling(postId);
    }
});


    // AJAX comment submit
    $(document).on('submit', '.commentForm', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        $btn.prop('disabled', true).text('Posting...');
        var formData = $form.serialize();
        var post_id = $form.find('input[name="post_id"]').val();

        $.ajax({
            url: $form.attr('action'),
            type: "POST",
            data: formData,
            success: function(response) {
            $btn.prop('disabled', false).text('Comment');
                $form[0].reset();
                // 1. Refresh comments for that post only
                $('#commentsList-' + post_id).load('includes/comments_list.php?post_id=' + post_id);

                // 2. Refresh the comment count in the UI (real-time update)
                $.get('includes/get_comment_count.php', { post_id: post_id }, function(data) {
                    $('#comment-count-' + post_id).text(data.count);
                }, 'json');
        },
            error: function() {
                $btn.prop('disabled', false).text('Comment');
                alert('Failed to comment! Try again.');
            }
        });
    });

    // Auto-load comments for all posts on page load
    $(document).ready(function() {
        <?php foreach ($postIds as $pid): ?>
            console.log("Auto-loading comments for post_id:", <?= $pid ?>);
            $('#commentsList-<?= $pid ?>').load('includes/comments_list.php?post_id=<?= $pid ?>');
        <?php endforeach; ?>
    });

    // Image modal
    $(document).on('click', '.post-image-link', function(e) {
        e.preventDefault();
        var src = $(this).data('img');
        $('#modalImage').attr('src', src);
        $('#imageModal').modal('show');
    });

    document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('forumSearch');
    searchInput.addEventListener('input', function() {
        const filter = searchInput.value.toLowerCase();
        // Hanapin lahat ng forum post cards (assume may class 'forum-post-card')
        document.querySelectorAll('.forum-post-card').forEach(card => {
            const text = card.innerText.toLowerCase();
            card.style.display = text.includes(filter) ? '' : 'none';
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // Get filter inputs
    const searchInput = document.getElementById('forumSearch');
    const monthSelect = document.querySelector('select[name="month"]');
    const yearSelect = document.querySelector('select[name="year"]');
    const orgSelect = document.querySelector('select[name="organization"]');

    // Listen to all filter inputs
    [searchInput, monthSelect, yearSelect, orgSelect].forEach(el => {
        if(el) el.addEventListener('input', filterPosts);
        if(el && el.tagName === "SELECT") el.addEventListener('change', filterPosts);
    });

    function filterPosts() {
        const searchVal = searchInput.value.trim().toLowerCase();
        const monthVal = monthSelect.value;
        const yearVal = yearSelect.value;
        const orgVal = orgSelect.value;

        let visibleCount = 0; // Make sure to declare this before the loop!

        document.querySelectorAll('.forum-post-card').forEach(card => {
            const text = card.innerText.toLowerCase();
            const cardMonth = card.getAttribute('data-month');
            const cardYear  = card.getAttribute('data-year');
            const cardOrg   = card.getAttribute('data-org_id');

            let show = true;
            if(searchVal && !text.includes(searchVal)) show = false;
            if(monthVal && monthVal !== "Month" && cardMonth !== monthVal) show = false;
            if(yearVal && yearVal !== "Year" && cardYear !== yearVal) show = false;
            if(orgVal && cardOrg !== orgVal && orgVal !== "SWKS") show = false;

            card.style.display = show ? "" : "none";
            if(show) visibleCount++;
        });

        // Show/hide "no results" message
        document.getElementById('noResultsMsg').style.display = (visibleCount === 0) ? "" : "none";
    }
});
// Poll comment counts for all posts every 3 seconds
setInterval(function() {
    <?php foreach ($postIds as $pid): ?>
        $.get('includes/get_comment_count.php', { post_id: <?= $pid ?> }, function(data) {
            $('#comment-count-<?= $pid ?>').text(data.count);
        }, 'json');
    <?php endforeach; ?>
}, 3000);

    </script>

    <script>
let latestPostId = 0; // Track the latest post ID on display

// Helper: get the topmost post_id currently shown
function getCurrentLatestPostId() {
    const firstPost = document.querySelector('.forum-post-card');
    return firstPost ? parseInt(firstPost.getAttribute('id').replace('post-', '')) : 0;
}

// Helper: render new post card (basic template, adjust to match your PHP output)
function renderForumPost(post) {
    function timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffSec = Math.floor(diffMs / 1000);
        if (diffSec < 60) return "just now";
        if (diffSec < 3600) return Math.floor(diffSec / 60) + " minutes ago";
        if (diffSec < 86400) return Math.floor(diffSec / 3600) + " hours ago";
        return date.toLocaleString();
    }
    // Profile pic fallback logic
    let profilePic = post.member_pic || post.adviser_pic || post.coor_pic || 'uploads/default.jpg';
    if (profilePic && !profilePic.startsWith('http')) profilePic = '/swks/' + profilePic;

    // Attachments rendering
    let attachmentHtml = "";
    try {
        const attachments = JSON.parse(post.attachment || "[]");
        if (attachments.length) {
            attachmentHtml += '<div class="row g-2 justify-content-center">';
            attachments.forEach(file => {
                const ext = file.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) {
                    attachmentHtml += `
                    <div class="col-6 col-md-3 d-flex justify-content-center">
                        <a href="/swks/${escapeHtml(file)}" class="post-image-link" data-img="/swks/${escapeHtml(file)}">
                            <img src="/swks/${escapeHtml(file)}" class="img-fluid rounded shadow-sm post-image-thumb" style="height:130px; object-fit:cover;">
                        </a>
                    </div>
                    `;
                } else {
                    attachmentHtml += `
                    <div class="col-12 mb-1">
                        <i class="bi bi-paperclip me-1"></i>
                        <a href="/swks/${escapeHtml(file)}" target="_blank">${file.split('/').pop()}</a>
                    </div>
                    `;
                }
            });
            attachmentHtml += '</div>';
        }
    } catch(e){}

    // Compose poster label (role, org, etc.)
    let posterRole = post.user_role ? post.user_role.charAt(0).toUpperCase() + post.user_role.slice(1) : '';
    let posterOrg = post.poster_org ? `, ${escapeHtml(post.poster_org)}` : '';
    let posterLabel = `${escapeHtml(post.poster_name)} (${posterRole}${posterOrg})`;

    return `
<div class="card forum-post-card mb-4 shadow-sm w-100"
    style="border-radius:18px;"
    id="post-${post.post_id}"
    data-month="${new Date(post.created_at).toLocaleString('default',{month:'long'})}"
    data-year="${new Date(post.created_at).getFullYear()}"
    data-org_id="${escapeHtml(post.org_id)}">

    <div class="card-body">
        <div class="d-flex align-items-center mb-2">
            <img src="${profilePic}" alt="Avatar" class="rounded-circle border border-2" width="52" height="52" style="object-fit:cover;">
            <div class="ms-3">
                <span class="fw-semibold" style="font-size:1.07rem;">
                    ${posterLabel}
                </span><br>
                <small class="text-muted">${timeAgo(post.created_at)}</small>
            </div>
        </div>
        ${attachmentHtml}
        ${post.title ? `<h5 class="fw-bold text-success mt-2 mb-1">${escapeHtml(post.title)}</h5>` : ''}
        <p class="mb-0" style="font-size:1.12rem;">${escapeHtml(post.content)}</p>
        <div class="d-flex align-items-center gap-3 mt-2">
            <span class="me-2 comment-toggle" style="cursor:pointer;" data-post-id="${post.post_id}">
                <i class="bi bi-chat-left-text fs-5"></i>
                <span class="ms-1">${post.comment_count ?? 0}</span>
            </span>
            <span class="me-2">
                <i class="bi bi-clock"></i>
                <span class="ms-1">${timeAgo(post.created_at)}</span>
            </span>
        </div>
    </div>
</div>
`;
}

// Helper to safely escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Helper to safely escape HTML
function escapeHtml(text) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// 1. On initial page load, set the latestPostId
document.addEventListener('DOMContentLoaded', function() {
    latestPostId = getCurrentLatestPostId();

    // 2. Start polling every 3 seconds
    setInterval(fetchNewPosts, 3000);
});

function fetchNewPosts() {
    fetch(`/swks/includes/fetch_forum_posts.php?since_id=${latestPostId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && Array.isArray(data.posts) && data.posts.length > 0) {
                // Insert new posts at the TOP (newest first)
               const container = document.getElementById('forumPostsList');
                let html = '';
                data.posts.forEach(post => {
                    // Only add if not already present!
                    if (!document.getElementById('post-' + post.post_id)) {
                        html += renderForumPost(post);
                        latestPostId = Math.max(latestPostId, post.post_id);
                    }
                });
                // Insert BEFORE current posts
                container.insertAdjacentHTML('afterbegin', html);
                // (Optional) Notification: highlight new post, play sound, etc.
            }
        })
        .catch(err => {
            if (err && err.status === 401) {
                alert("Session expired. Please log in again.");
            }
            // You can also show errors in the UI if you wish
        });
}

// Track timers per post
const commentTimers = {};

// Poll comments if expanded
function startCommentPolling(postId) {
    if (commentTimers[postId]) return; // Already polling

    commentTimers[postId] = setInterval(function() {
        // 1. Reload comments
        $('#commentsList-' + postId).load('includes/comments_list.php?post_id=' + postId);

        // 2. Refresh comment count
        $.get('includes/get_comment_count.php', { post_id: postId }, function(data) {
            $('#comment-count-' + postId).text(data.count);
        }, 'json');

    }, 3000);
}

function stopCommentPolling(postId) {
    if (commentTimers[postId]) {
        clearInterval(commentTimers[postId]);
        delete commentTimers[postId];
    }
}

</script>

</body>
</html>
