<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ob_start();
include 'includes/auth_member.php';
include '../database/db_connection.php';
include 'includes/functions.php';

// -------------------- Resolve member's org --------------------
$user_id = $_SESSION['user_id'] ?? 0;
$org_id = 0; $org_name = '';

$stmt = $conn->prepare("
    SELECT o.org_id, o.org_name
    FROM member_details md
    JOIN user u ON md.user_id = u.user_id
    JOIN organization o ON md.preferred_org = o.org_id
    WHERE md.user_id = ?
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($org_id, $org_name);
$has = $stmt->fetch();
$stmt->close();

if (!$has || !$org_id) {
    // fallback
    $org_id = 11;
    $org_name = "SWKS";
}

const SWKS_ORG_ID = 11; // << adjust if your SWKS org_id differs

// -------------------- Fetch posts (own org + SWKS/admin only) --------------------
$sql = "SELECT 
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
        LEFT JOIN organization org ON org.org_id = u.org_id
        WHERE 
            p.org_id = ? 
            OR (p.org_id = ? AND u.user_role = 'admin')
        ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);       // no named arg
$swks = (int)SWKS_ORG_ID;           // put constant into a variable
$stmt->bind_param('ii', $org_id, $swks);
$stmt->execute();
$result = $stmt->get_result();

// Prepare post IDs for comment polling
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
    <title>Member Forum</title>
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
        .post-image-thumb { transition: transform .15s cubic-bezier(.4,2,.3,1); cursor: zoom-in; }
        .post-image-thumb:hover { transform: scale(1.06); z-index:2; box-shadow:0 2px 16px rgba(0,0,0,.15); }
      .commentForm{ margin-left:72px; max-width:900px; }
  .commentForm textarea.form-control{ flex:0 0 65%; max-width:650px; min-width:320px; }
  @media (max-width:576px){
    .commentForm{ margin-left:0; max-width:none; }
    .commentForm textarea.form-control{ flex:1 1 auto; max-width:none; }
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
        <h4 class="fw-bold mb-0"><?= htmlspecialchars(strtoupper($org_name) . " FORUM") ?></h4>
      </div>
      <div class="col-md-7 col-12 mt-2 mt-md-0 d-flex justify-content-md-end">
        <form class="d-flex flex-nowrap align-items-center" style="overflow-x:auto;">
          <select class="form-select me-2" name="month" style="max-width: 100px;">
            <option>Month</option>
            <?php
              $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
              foreach ($months as $m) echo "<option>$m</option>";
            ?>
          </select>
          <select class="form-select me-2" name="year" style="max-width: 100px;">
            <option>Year</option>
            <?php
              $yearNow = date("Y");
              for ($y = $yearNow; $y >= $yearNow-5; $y--) echo "<option>$y</option>";
            ?>
          </select>
          <input type="text" id="forumSearch" class="form-control ms-2" placeholder="Search posts..." style="max-width: 220px;">
        </form>
      </div>
    </div>

    <!-- NOTE: Removed the post form (members cannot create posts) -->

    <div id="noResultsMsg" class="alert alert-warning text-center mt-3" style="display:none;">
      No forum posts found matching your search.
    </div>

    <!-- Forum Post Cards -->
    <div id="forumPostsList">
      <?php foreach ($posts as $row): ?>
        <?php
          $post_id = (int)$row['post_id'];
          $commentCount = 0;
          if ($cStmt = $conn->prepare("SELECT COUNT(*) FROM forum_comment WHERE post_id=?")) {
              $cStmt->bind_param('i', $post_id);
              $cStmt->execute();
              $cStmt->bind_result($commentCount);
              $cStmt->fetch();
              $cStmt->close();
          }
          $profile_pic = $row['member_pic'] ?: $row['adviser_pic'] ?: $row['coor_pic'] ?: 'uploads/default.jpg';
          if ($profile_pic && !preg_match('#^https?://#', $profile_pic)) {
              if (!str_starts_with($profile_pic, 'uploads/')) $profile_pic = "uploads/" . ltrim($profile_pic, '/');
              $profile_pic = "/swks/" . ltrim($profile_pic, '/');
          }
          $created_at = $row['created_at'];
          $now = new DateTime();
          $created = new DateTime($created_at);
          $interval = $now->diff($created);
          $displayTime = ($interval->days > 7) ? date("F j, Y h:iA", strtotime($created_at)) : timeAgo($created_at);
        ?>
        <div class="card forum-post-card mb-4 shadow-sm w-100"
             style="border-radius:18px;"
             id="post-<?= $post_id ?>"
             data-month="<?= date('F', strtotime($row['created_at'])) ?>"
             data-year="<?= date('Y', strtotime($row['created_at'])) ?>"
             data-org_id="<?= htmlspecialchars($row['org_id']) ?>">
          <div class="card-body">
            <!-- User info -->
            <div class="d-flex align-items-center mb-2">
              <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Avatar"
                   class="rounded-circle border border-2" width="52" height="52" style="object-fit:cover;">
              <div class="ms-3">
                <?php $posterLabel = formatPosterLabel($row['user_role'], $row['poster_org'], $row['poster_name']); ?>
                <span class="fw-semibold" style="font-size:1.07rem;"><?= htmlspecialchars($posterLabel) ?></span><br>
                <small class="text-muted"><?= htmlspecialchars($displayTime) ?></small>
              </div>
            </div>

            <!-- Attachments -->
            <?php
              $attachments = json_decode($row['attachment'] ?? '[]', true);
              if ($attachments && is_array($attachments)) {
                  echo '<div class="row g-2 justify-content-center">';
                  foreach ($attachments as $file) {
                      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                      if (in_array($ext, ['jpg','jpeg','png','gif','bmp','webp','jfif','tiff','svg'])) {
                          $src = '/swks/' . ltrim($file, '/');
                          echo '<div class="col-6 col-md-3 d-flex justify-content-center">
                                  <a href="'.htmlspecialchars($src).'" class="post-image-link" data-img="'.htmlspecialchars($src).'">
                                    <img src="'.htmlspecialchars($src).'" class="img-fluid rounded shadow-sm post-image-thumb" style="height:130px;object-fit:cover;">
                                  </a>
                                </div>';
                      } else {
                          $href = '/swks/' . ltrim($file, '/');
                          echo '<div class="col-12 mb-1">
                                  <i class="bi bi-paperclip me-1"></i>
                                  <a href="'.htmlspecialchars($href).'" target="_blank">'.htmlspecialchars(basename($file)).'</a>
                                </div>';
                      }
                  }
                  echo '</div>';
              }
            ?>

            <?php if (!empty($row['title'])): ?>
              <h5 class="fw-bold text-success mt-2 mb-1">
                <a href="view_forum_post.php?post_id=<?= $post_id ?>" class="text-success text-decoration-none">
                  <?= htmlspecialchars($row['title']) ?>
                </a>
              </h5>
            <?php endif; ?>

            <p class="mb-0" style="font-size:1.12rem;"><?= nl2br(htmlspecialchars($row['content'])) ?></p>

            <!-- Comment count + toggle -->
            <div class="d-flex align-items-center gap-3 mt-2">
              <span class="me-2 comment-toggle" style="cursor:pointer;" data-post-id="<?= $post_id ?>">
                <i class="bi bi-chat-left-text fs-5"></i>
                <span class="ms-1" id="comment-count-<?= $post_id ?>"><?= (int)$commentCount ?></span>
              </span>
            </div>

            <!-- Comments Area -->
            <div class="collapse mt-3" id="comments-<?= $post_id ?>">
              <div class="mb-2" id="commentsList-<?= $post_id ?>">
                <div class="text-muted">Loading comments...</div>
              </div>
              <form method="post" action="forum_comment_action.php" 
      class="commentForm d-flex align-items-center gap-2 ms-4"
      style="margin-left:72px; max-width:900px;">
  <input type="hidden" name="post_id" value="<?= $post_id ?>">
  <textarea name="comment_text"
            class="form-control flex-grow-0"
            rows="1"
            placeholder="Write a comment..."
            required
            style="flex:0 0 65%; max-width:650px; min-width:320px;"></textarea>
  <button type="submit" class="btn btn-sm btn-success">Comment</button>
</form>

            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div><!-- /forumPostsList -->
  </div><!-- /forumPostsContainer -->
</div><!-- /main-content -->

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
// open/close comments + polling per post
$(document).on('click', '.comment-toggle', function() {
  let postId = $(this).data('post-id');
  let el = $('#comments-' + postId);
  el.toggleClass('show');
  if (el.hasClass('show')) {
    $('#commentsList-' + postId).load('includes/comments_list.php?post_id=' + postId);
    startCommentPolling(postId);
  } else {
    stopCommentPolling(postId);
  }
});

// submit comment
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
    success: function() {
      $btn.prop('disabled', false).text('Comment');
      $form[0].reset();
      $('#commentsList-' + post_id).load('includes/comments_list.php?post_id=' + post_id);
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

// image modal
$(document).on('click', '.post-image-link', function(e) {
  e.preventDefault();
  var src = $(this).data('img');
  $('#modalImage').attr('src', src);
  $('#imageModal').modal('show');
});

// ---------- Filters (Month/Year/Search) ----------
function initFilters(){
  const searchInput = document.getElementById('forumSearch');
  const monthSelect = document.querySelector('select[name="month"]');
  const yearSelect  = document.querySelector('select[name="year"]');

  const onFilter = () => {
    const searchVal = (searchInput?.value || '').trim().toLowerCase();
    const monthVal  = monthSelect?.value || '';
    const yearVal   = yearSelect?.value || '';
    let visibleCount = 0;

    document.querySelectorAll('.forum-post-card').forEach(card => {
      const text      = card.innerText.toLowerCase();
      const cardMonth = card.getAttribute('data-month');
      const cardYear  = card.getAttribute('data-year');

      let show = true;
      if (searchVal && !text.includes(searchVal)) show = false;
      if (monthVal && monthVal !== 'Month' && cardMonth !== monthVal) show = false;
      if (yearVal  && yearVal  !== 'Year'  && cardYear  !== yearVal ) show = false;

      card.style.display = show ? '' : 'none';
      if (show) visibleCount++;
    });

    const noRes = document.getElementById('noResultsMsg');
    if (noRes) noRes.style.display = (visibleCount === 0) ? '' : 'none';
  };

  [searchInput, monthSelect, yearSelect].forEach(el => {
    if (!el) return;
    el.removeEventListener('input', onFilter);
    el.removeEventListener('change', onFilter);
    el.addEventListener('input', onFilter);
    if (el.tagName === 'SELECT') el.addEventListener('change', onFilter);
  });

  onFilter();
}
document.addEventListener('DOMContentLoaded', initFilters);

// ---------- Comment count polling ----------
<?php foreach ($postIds as $pid): ?>
setInterval(function() {
  $.get('includes/get_comment_count.php', { post_id: <?= (int)$pid ?> }, function(data) {
    $('#comment-count-<?= (int)$pid ?>').text(data.count);
  }, 'json');
}, 3000);
<?php endforeach; ?>

// ---------- Live new-post polling (guarded for member org only) ----------
let latestPostId = (function(){
  const first = document.querySelector('.forum-post-card');
  return first ? parseInt(first.id.replace('post-','')) : 0;
})();
const MEMBER_ORG_ID = <?= (int)$org_id ?>;
const SWKS_ORG_ID   = <?= (int)SWKS_ORG_ID ?>;

function renderForumPost(post){
  // (same renderer as before, shortened for brevity)
  function escapeHtml(t){ if(!t) return ''; return String(t).replace(/[&<>"']/g, s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s])); }
  let profilePic = post.member_pic || post.adviser_pic || post.coor_pic || 'uploads/default.jpg';
  if (profilePic && !profilePic.startsWith('http')) profilePic = '/swks/' + profilePic;
  let attachmentHtml = '';
  try {
    const atts = JSON.parse(post.attachment || "[]");
    if (atts.length) {
      attachmentHtml += '<div class="row g-2 justify-content-center">';
      atts.forEach(file=>{
        const ext = file.split('.').pop().toLowerCase();
        if (['jpg','jpeg','png','gif','bmp','webp','jfif','tiff','svg'].includes(ext)){
          attachmentHtml += `<div class="col-6 col-md-3 d-flex justify-content-center">
            <a href="/swks/${escapeHtml(file)}" class="post-image-link" data-img="/swks/${escapeHtml(file)}">
              <img src="/swks/${escapeHtml(file)}" class="img-fluid rounded shadow-sm post-image-thumb" style="height:130px;object-fit:cover;">
            </a></div>`;
        } else {
          attachmentHtml += `<div class="col-12 mb-1"><i class="bi bi-paperclip me-1"></i>
            <a href="/swks/${escapeHtml(file)}" target="_blank">${file.split('/').pop()}</a></div>`;
        }
      });
      attachmentHtml += '</div>';
    }
  } catch(e){}

  const posterRole = post.user_role ? post.user_role.charAt(0).toUpperCase() + post.user_role.slice(1) : '';
  const posterOrg  = post.poster_org ? `, ${escapeHtml(post.poster_org)}` : '';
  const posterLbl  = `${escapeHtml(post.poster_name)} (${posterRole}${posterOrg})`;

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
        <span class="fw-semibold" style="font-size:1.07rem;">${posterLbl}</span><br>
        <small class="text-muted">${new Date(post.created_at).toLocaleString()}</small>
      </div>
    </div>
    ${attachmentHtml}
    ${post.title ? `<h5 class="fw-bold text-success mt-2 mb-1">
       <a href="view_forum_post.php?post_id=${post.post_id}" class="text-success text-decoration-none">${escapeHtml(post.title)}</a>
     </h5>` : ''}
    <p class="mb-0" style="font-size:1.12rem;">${escapeHtml(post.content)}</p>
    <div class="d-flex align-items-center gap-3 mt-2">
      <span class="me-2 comment-toggle" style="cursor:pointer;" data-post-id="${post.post_id}">
        <i class="bi bi-chat-left-text fs-5"></i><span class="ms-1">${post.comment_count ?? 0}</span>
      </span>
    </div>
  </div>
</div>`;
}

function fetchNewPosts(){
  fetch(`/swks/includes/fetch_forum_posts.php?since_id=${latestPostId}&organization=${encodeURIComponent(MEMBER_ORG_ID)}`)
    .then(r=>r.json())
    .then(data=>{
      if (!data.success || !Array.isArray(data.posts)) return;
      const container = document.getElementById('forumPostsList');
      let html = '';
      data.posts.forEach(post=>{
        // guard: show only (member org) OR (SWKS + admin)
        const isOwnOrg = String(post.org_id) === String(MEMBER_ORG_ID);
        const isSwksAdmin = (String(post.org_id) === String(SWKS_ORG_ID) && String(post.user_role) === 'admin');
        if (!(isOwnOrg || isSwksAdmin)) return;

        if (!document.getElementById('post-' + post.post_id)) {
          html += renderForumPost(post);
          latestPostId = Math.max(latestPostId, post.post_id);
        }
      });
      if (html) container.insertAdjacentHTML('afterbegin', html);
    })
    .catch(()=>{});
}
setInterval(fetchNewPosts, 3000);

// comments live polling per expanded post
const commentTimers = {};
function startCommentPolling(postId){
  if (commentTimers[postId]) return;
  commentTimers[postId] = setInterval(function(){
    $('#commentsList-' + postId).load('includes/comments_list.php?post_id=' + postId);
    $.get('includes/get_comment_count.php', { post_id: postId }, function(d){
      $('#comment-count-' + postId).text(d.count);
    }, 'json');
  }, 3000);
}
function stopCommentPolling(postId){
  if (commentTimers[postId]) { clearInterval(commentTimers[postId]); delete commentTimers[postId]; }
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>
