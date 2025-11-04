<?php
include_once '../database/db_connection.php';
include_once 'functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();
// Get the user's name and email from session (set during login)
$userName = $_SESSION['user_name'] ?? 'User Name';
$userEmail = $_SESSION['user_email'] ?? 'email@example.com';
// Optional: Get profile picture (set to blank if not used)
$profilePic = $_SESSION['profile_pic'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

// GET count of unseen notifications
$notifCount = 0;
$notifications = [];
if ($userId) {
    // Get unseen count
    $sql = "SELECT COUNT(*) AS cnt FROM notification WHERE user_id = ? AND is_seen = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($notifCount);
    $stmt->fetch();
    $stmt->close();
}
?>

<!-- Headbar -->
<div class="headbar d-flex align-items-center">
    <!-- Hamburger button for mobile/tablet -->
    <button class="hamburger-btn d-lg-none" onclick="toggleSidebar()" aria-label="Open sidebar">
        <i class="bi bi-list"></i>
    </button>
    <!-- App/System Name -->
    <span style="font-size: 1.14rem; font-weight: 700; letter-spacing: 1px;">SWKS</span>
    <!-- Right side icons -->
    <div class="ms-auto d-flex align-items-center" style="gap:16px;">
        <!-- Notification Bell -->
        <div class="dropdown" style="margin-right: 10px;">
            <a href="#" class="position-relative" id="notifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false" style="display:inline-block; text-decoration:none; color:inherit;">
                <i class="bi bi-bell" style="font-size:1.55rem; vertical-align:middle;"></i>
             <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                  style="font-size:0.77em; min-width: 22px; z-index: 2; border: 2px solid #fff; <?= $notifCount > 0 ? '' : 'display:none;' ?>">
                <?= $notifCount > 0 ? $notifCount : '' ?>
            </span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow"
                id="notifDropdownMenu"
                style="min-width:340px; max-width:90vw; max-height:380px; overflow:auto;"
                aria-labelledby="notifDropdownBtn">
                <?php include 'includes/notif_list.php'; ?>
            </ul>
        </div>
        <!-- End Notification Bell -->        
        <!-- Profile Dropdown -->
        <div class="dropdown">
            <?php if ($profilePic): ?>
                <!-- Show profile picture if available -->
                <img src="<?= strpos($profilePic, 'http') === 0 ? $profilePic : '/swks/' . htmlspecialchars($profilePic) ?>" alt="Profile" class="rounded-circle" width="32" height="32" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="object-fit:cover;cursor:pointer;">
            <?php else: ?>
                <!-- Fallback to icon -->
                <i class="bi bi-person-circle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="font-size:1.7rem; cursor:pointer;"></i>
            <?php endif; ?>
            <ul class="dropdown-menu dropdown-menu-end shadow profile-dropdown" aria-labelledby="profileDropdown" style="min-width: 260px;">
                <li class="px-3 py-2 mb-1 user-info-area">
                    <div class="fw-bold" style="font-size: 1.09rem;">
                        <?= htmlspecialchars($userName) ?>
                    </div>
                     <div style="font-size:0.97rem; color:#186a1a; font-weight: 500;">
                        <?php
                            // Change role wording here
                            $role = $_SESSION['user_role'] ?? '';
                            if (strtolower($role) === 'admin') {
                                echo 'ACA Coordinator';
                            } else {
                                echo htmlspecialchars(ucwords($role));
                            }
                        ?>
                    </div>
                    <div class="small text-muted" style="white-space:normal;"><?= htmlspecialchars($userEmail) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#myProfileModal">
                        <i class="bi bi-person-lines-fill me-2"></i>My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="#" id="logoutBtn">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
        <!-- End Profile Dropdown -->
    </div>
</div>
<!-- My Profile Modal -->
<div class="modal fade profile-modal" id="myProfileModal" tabindex="-1" aria-labelledby="myProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="profile-banner"></div>
      <div class="modal-body text-center pt-0">
        <div class="profile-icon d-inline-block rounded-circle p-1 shadow" style="margin-bottom:10px;">
          <?php if ($profilePic): ?>
             <img src="/swks/<?= htmlspecialchars($_SESSION['profile_pic'] ?? 'uploads/default.jpg') ?>"
        alt="Avatar" class="rounded-circle border border-2" width="52" height="52" style="object-fit:cover;">
          <?php else: ?>
            <i class="bi bi-person-circle" style="font-size:4.3rem; color:#186a1a;"></i>
          <?php endif; ?>
        </div>
        <div class="fw-bold mt-2 mb-1" style="font-size:1.25rem;">
          <?= htmlspecialchars($userName) ?>
        </div>
        <div class="text-muted mb-1 small"><?= htmlspecialchars($userEmail) ?></div>
        <span class="role-badge"><?= htmlspecialchars(ucwords($_SESSION['user_role'] ?? '')) ?></span>
        <div class="mt-4 d-grid gap-2">
          <button type="button" class="btn btn-light border d-flex align-items-center justify-content-center gap-2 py-2"
            onclick="closeProfileOpenEditProfile()">
            <i class="bi bi-pencil-square" style="font-size:1.2rem;"></i>
            <span>Edit Profile</span>
        </button>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<!-- End My Profile Modal -->

<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header pb-2">
        <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form enctype="multipart/form-data" id="editProfileForm" method="post" action="profile_update.php">
        <div class="modal-body pb-2">
          <!-- Profile Picture Preview & Upload -->
          <div class="text-center mb-3">
            <label for="profilePicInput" class="d-block mb-2" style="cursor:pointer;">
              <?php if ($profilePic): ?>
                 <img id="profilePicPreview" src="/swks/<?= htmlspecialchars($_SESSION['profile_pic'] ?? 'uploads/default.jpg') ?>"
        alt="Avatar" class="rounded-circle border border-2" width="74" height="74" style="object-fit:cover;">

        <?php else: ?>
                <!-- IMPORTANT: Add the ID to the fallback icon -->
                <i class="bi bi-person-circle" id="profilePicPreview" style="font-size:3.8rem;color:#186a1a;"></i>
              <?php endif; ?>
              <div class="small text-muted mt-1">Click to change photo</div>
            </label>
            <input type="file" name="profile_pic" id="profilePicInput" class="d-none" accept="image/*" onchange="previewProfilePic(event)">
          </div>


          <!-- Password Fields -->
          <div class="mb-3">
            <input type="password" class="form-control" name="current_password" placeholder="Current Password" autocomplete="current-password">
          </div>
          <div class="mb-3">
            <input type="password" class="form-control" name="new_password" placeholder="New Password" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <input type="password" class="form-control" name="confirm_new_password" placeholder="Confirm New Password" autocomplete="new-password">
          </div>
        </div>
        <div class="modal-footer border-0 d-grid">
          <button type="submit" class="btn btn-success w-100">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- End Change Password Modal -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you really want to logout?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Call logout.php via redirect
                    window.location.href = 'logout.php';
                }
            });
        });
    }
});
</script>

<script>
function previewProfilePic(event) {
  const input = event.target;
  const preview = document.getElementById('profilePicPreview');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      if (preview.tagName === "IMG") {
        preview.src = e.target.result;
      } else {
        // If fallback icon, replace with image
        preview.outerHTML = `<img src="${e.target.result}" id="profilePicPreview" class="rounded-circle border" width="74" height="74" style="object-fit:cover;">`;
      }
    };
    reader.readAsDataURL(input.files[0]);
  }
}
function closeProfileOpenEditProfile() {
  var profileModal = bootstrap.Modal.getInstance(document.getElementById('myProfileModal'));
  profileModal.hide();
  setTimeout(function() {
    var editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
    editModal.show();
  }, 350);
}
</script>

<script>
// --- Badge helpers ---
function setNotifBadge(n){
  const b = document.getElementById('notifCount');
  if (!b) return;
  b.textContent = n;
  b.style.display = n > 0 ? '' : 'none';
}
function refreshNotifCount(){
  return fetch('includes/notif_count.php', { cache: 'no-store' })
    .then(r => r.text())
    .then(txt => { const n = parseInt(txt, 10) || 0; setNotifBadge(n); })
    .catch(() => {});
}

document.addEventListener('DOMContentLoaded', () => {
  // initial + optional polling
  refreshNotifCount();
  setInterval(refreshNotifCount, 8000);
});

// --- Mark single notif as seen BEFORE navigating ---
document.addEventListener('click', function(e){
  const a = e.target.closest('a.notif-item');
  if (!a) return;

  const id   = a.dataset.notifId;
  const href = a.getAttribute('href') || '#';
  if (!id) return; // walang notif_id? proceed na lang normally

  e.preventDefault();

  // Optimistic decrement sa UI
  const badge = document.getElementById('notifCount');
  if (badge) {
    const cur = parseInt(badge.textContent || '0', 10) || 0;
    setNotifBadge(Math.max(cur - 1, 0));
  }

  // Call the endpoint (GET version mo)
  fetch('includes/mark_notification_seen.php?id=' + encodeURIComponent(id), { cache:'no-store' })
    .finally(() => {
      // Sync count (optional) then navigate
      refreshNotifCount().finally(() => { window.location.href = href; });
    });
});

// --- (Optional) Reload notif list when dropdown opens ---
document.addEventListener('DOMContentLoaded', () => {
  const dd = document.getElementById('notifDropdown');
  if (!dd) return;
  dd.addEventListener('click', () => {
    if (window.jQuery) {
      $('#notifList').load('includes/notif_list.php');
    }
  });
});
</script>

