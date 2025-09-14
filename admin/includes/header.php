<?php
include_once '../database/db_connection.php';
include_once 'functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();

// Get user info
$userName = $_SESSION['user_name'] ?? 'User Name';
$userEmail = $_SESSION['user_email'] ?? 'email@example.com';
$profilePic = $_SESSION['profile_pic'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['user_role'] ?? '';

// Get notification count
$notifCount = 0;
if ($userId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notification WHERE user_id = ? AND is_seen = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($notifCount);
    $stmt->fetch();
    $stmt->close();
}
?>

<!-- ========== MODERN HEADBAR ========== -->
<style>
    .modern-headbar {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(76, 175, 80, 0.15);
        height: 72px;
        padding: 0 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1020;
        transition: all 0.3s ease;
    }

    .modern-headbar .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 800;
        font-size: 1.35rem;
        color: #2e7d32;
        text-decoration: none;
        letter-spacing: 0.5px;
    }

    .modern-headbar .brand i {
        font-size: 1.6rem;
    }

    .search-container {
        flex: 1;
        max-width: 320px;
        margin: 0 24px;
    }

    .search-container input {
        width: 100%;
        background: rgba(76, 175, 80, 0.05);
        border: 1px solid rgba(76, 175, 80, 0.2);
        border-radius: 50px;
        padding: 8px 16px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        color: #263238;
    }

    .search-container input:focus {
        background: white;
        border-color: #4caf50;
        box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.15);
        outline: none;
    }

    .headbar-actions {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .headbar-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(76, 175, 80, 0.08);
        color: #2e7d32;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        font-size: 1.25rem;
    }

    .headbar-btn:hover {
        background: rgba(76, 175, 80, 0.2);
        transform: translateY(-2px);
    }

    .notif-badge {
        position: absolute;
        top: 6px;
        right: 6px;
        min-width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #e53935;
        color: white;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
        font-weight: 700;
        border: 2px solid white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .profile-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(76, 175, 80, 0.3);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .profile-avatar:hover {
        transform: scale(1.05);
        border-color: #4caf50;
    }

    /* Responsive */
    @media (max-width: 992px) {
        .search-container {
            display: none;
        }
    }

    @media (max-width: 768px) {
        .modern-headbar {
            padding: 0 16px;
        }
        .headbar-actions {
            gap: 12px;
        }
        .headbar-btn {
            width: 36px;
            height: 36px;
            font-size: 1.1rem;
        }
    }

    /* Dropdown Menu Modern Styling */
    .dropdown-menu {
        border-radius: 18px !important;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12) !important;
        border: 1px solid rgba(76, 175, 80, 0.1) !important;
        padding: 0.5rem 0 !important;
        animation: slideDown 0.2s ease forwards;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-item {
        padding: 10px 20px !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
    }

    .dropdown-item:hover {
        background: rgba(76, 175, 80, 0.08) !important;
        transform: translateX(4px);
    }

    .user-info-area {
        padding: 1rem 1.5rem !important;
        background: rgba(76, 175, 80, 0.05) !important;
        border-radius: 12px 12px 0 0 !important;
        margin-bottom: 0.5rem !important;
    }

    .dropdown-divider {
        margin: 0.5rem 0 !important;
    }

    /* Profile Modal */
    .profile-modal .modal-content {
        border-radius: 24px !important;
        box-shadow: 0 12px 40px rgba(76, 175, 80, 0.15) !important;
    }

    .profile-modal .profile-banner {
        background: linear-gradient(135deg, #2e7d32, #4caf50);
        height: 80px;
        border-radius: 24px 24px 0 0;
    }

    .profile-modal .role-badge {
        background: rgba(76, 175, 80, 0.15);
        color: #2e7d32;
        font-weight: 700;
        border-radius: 50px;
        padding: 0.3em 1em;
        font-size: 0.9rem;
    }
</style>

<!-- Headbar -->
<div class="modern-headbar">
    <!-- Hamburger button for mobile/tablet -->
    <button class="hamburger-btn d-lg-none headbar-btn" onclick="toggleSidebar()" aria-label="Open sidebar">
        <i class="bi bi-list"></i>
    </button>

    <!-- Brand -->
    <a href="#" class="brand">
        <i class="bi bi-tree"></i>
        <span>SWKS</span>
    </a>

    <!-- Search Bar (hidden on mobile) -->
    <div class="search-container">
        <input 
            type="text" 
            class="form-control form-control-sm" 
            placeholder="Search events, members..." 
        >
    </div>

    <!-- Right side icons -->
    <div class="headbar-actions">
        <!-- Notification Bell -->
        <div class="dropdown">
            <button class="headbar-btn" id="notifDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <?php if ($notifCount > 0): ?>
                    <span class="notif-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notifDropdownBtn">
                <?php include 'includes/notif_list.php'; ?>
            </ul>
        </div>

        <!-- Profile Dropdown -->
        <div class="dropdown">
            <?php if ($profilePic): ?>
                <img src="<?= strpos($profilePic, 'http') === 0 ? $profilePic : '/swks/' . htmlspecialchars($profilePic) ?>" 
                     alt="Profile" 
                     class="profile-avatar"
                     id="profileDropdown" 
                     data-bs-toggle="dropdown" 
                     aria-expanded="false">
            <?php else: ?>
                <button class="headbar-btn" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person"></i>
                </button>
            <?php endif; ?>

            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li class="user-info-area">
                    <div class="fw-bold" style="font-size: 1.1rem; color: #2e7d32;">
                        <?= htmlspecialchars($userName) ?>
                    </div>
                    <div style="font-size: 0.95rem; font-weight: 600; color: #4caf50;">
                        <?= ucwords($role === 'admin' ? 'ACA Coordinator' : $role) ?>
                    </div>
                    <div class="small text-muted"><?= htmlspecialchars($userEmail) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#myProfileModal">
                        <i class="bi bi-person-lines-fill me-2"></i> My Profile
                    </a>
                </li>
                <li>
                    <a class="dropdown-item text-danger" href="#" id="logoutBtn">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- ========== MODALS (UNCHANGED FUNCTIONALITY, MODERNIZED STYLING) ========== -->
<!-- My Profile Modal -->
<div class="modal fade profile-modal" id="myProfileModal" tabindex="-1" aria-labelledby="myProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="profile-banner"></div>
      <div class="modal-body text-center pt-4">
        <div class="profile-icon d-inline-block rounded-circle p-2 shadow">
          <?php if ($profilePic): ?>
             <img src="/swks/<?= htmlspecialchars($_SESSION['profile_pic'] ?? 'uploads/default.jpg') ?>"
                  alt="Avatar" class="rounded-circle border border-3" width="64" height="64" style="object-fit:cover;">
          <?php else: ?>
            <i class="bi bi-person-circle" style="font-size:4.2rem; color:#fff;"></i>
          <?php endif; ?>
        </div>
        <div class="fw-bold mt-3 mb-1" style="font-size:1.3rem; color: #2e7d32;">
          <?= htmlspecialchars($userName) ?>
        </div>
        <div class="text-muted mb-2 small"><?= htmlspecialchars($userEmail) ?></div>
        <span class="role-badge"><?= htmlspecialchars(ucwords($_SESSION['user_role'] ?? '')) ?></span>
        <div class="mt-4 d-grid gap-2 px-4">
          <button type="button" class="btn btn-outline-success py-2" onclick="closeProfileOpenEditProfile()">
            <i class="bi bi-pencil-square me-2"></i> Edit Profile
          </button>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center pb-4">
        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4">
      <div class="modal-header pb-2">
        <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form enctype="multipart/form-data" id="editProfileForm" method="post" action="profile_update.php">
        <div class="modal-body pb-2">
          <!-- Profile Picture -->
          <div class="text-center mb-4">
            <label for="profilePicInput" class="d-block mb-2 cursor-pointer">
              <?php if ($profilePic): ?>
                 <img id="profilePicPreview" src="/swks/<?= htmlspecialchars($_SESSION['profile_pic'] ?? 'uploads/default.jpg') ?>"
                      alt="Avatar" class="rounded-circle border border-3" width="80" height="80" style="object-fit:cover;">
              <?php else: ?>
                <i class="bi bi-person-circle" id="profilePicPreview" style="font-size:4.5rem; color:#4caf50;"></i>
              <?php endif; ?>
              <div class="small text-muted mt-2">Click to change photo</div>
            </label>
            <input type="file" name="profile_pic" id="profilePicInput" class="d-none" accept="image/*" onchange="previewProfilePic(event)">
          </div>

          <!-- Password Fields -->
          <div class="mb-3">
            <input type="password" class="form-control form-control-lg" name="current_password" placeholder="Current Password" autocomplete="current-password">
          </div>
          <div class="mb-3">
            <input type="password" class="form-control form-control-lg" name="new_password" placeholder="New Password" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <input type="password" class="form-control form-control-lg" name="confirm_new_password" placeholder="Confirm New Password" autocomplete="new-password">
          </div>
        </div>
        <div class="modal-footer border-0 d-grid px-4 pb-4">
          <button type="submit" class="btn btn-success btn-lg">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ========== SCRIPTS ========== -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Logout confirmation
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you really want to logout?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#aaa',
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'Cancel',
                customClass: {
                    confirmButton: 'btn btn-success',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        });
    }
});

function previewProfilePic(event) {
    const input = event.target;
    const preview = document.getElementById('profilePicPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview.tagName === "IMG") {
                preview.src = e.target.result;
            } else {
                preview.outerHTML = `<img src="${e.target.result}" id="profilePicPreview" class="rounded-circle border border-3" width="80" height="80" style="object-fit:cover;">`;
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function closeProfileOpenEditProfile() {
    const profileModal = bootstrap.Modal.getInstance(document.getElementById('myProfileModal'));
    profileModal.hide();
    setTimeout(() => {
        const editModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
        editModal.show();
    }, 350);
}

// Notification auto-refresh
function updateNotifBadge() {
    fetch('includes/notif_count.php')
        .then(response => response.text())
        .then(count => {
            const badge = document.querySelector('.notif-badge');
            count = parseInt(count);
            if (badge) {
                if (count > 0) {
                    badge.style.display = 'flex';
                    badge.innerText = count > 9 ? '9+' : count;
                } else {
                    badge.style.display = 'none';
                }
            }
        });
}

function updateNotifList() {
    fetch('includes/notif_list.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById('notifDropdownMenu').innerHTML = html;
        });
}

updateNotifBadge();
updateNotifList();
setInterval(updateNotifBadge, 3000);
setInterval(updateNotifList, 3000);
</script>