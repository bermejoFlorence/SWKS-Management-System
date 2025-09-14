<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

$user_id = $_SESSION['user_id'];
$carouselQ = $conn->query("SELECT * FROM web_settings WHERE type = 'carousel' ORDER BY created_at DESC");

// Add this for About section
$aboutQ = $conn->query("SELECT * FROM web_settings WHERE type = 'about' LIMIT 1");
$about = $aboutQ->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aca Coordinator Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles/style.css">
</head>
<style>
.card { border-radius: 16px; transition: box-shadow 0.2s; }
.card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.11); }
.main-content .card { border-radius: 18px; transition: box-shadow 0.2s; }
.main-content .card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
.main-content { padding-top: 70px; }
@media (max-width: 575px) { .display-4 { font-size: 2.2rem; } }
</style>
<body>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
<div class="container mt-4">
  <!-- <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 swks-header-row">
    <h2 class="mb-0" style="color: var(--swks-green); font-weight: bold; padding-bottom:10px;">
      <i class="bi bi-images me-2"></i>Homepage Carousel
    </h2>
    <button class="btn btn-success shadow-sm fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#addCarouselModal">
      <i class="bi bi-plus-circle me-1"></i> Add Carousel Image
    </button>
  </div> -->
  <ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="carousel-tab" data-bs-toggle="tab" data-bs-target="#carouselTabPane" type="button" role="tab" aria-controls="carouselTabPane" aria-selected="true">
      <i class="bi bi-images me-1"></i> Homepage Carousel
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#aboutTabPane" type="button" role="tab" aria-controls="aboutTabPane" aria-selected="false">
      <i class="bi bi-info-circle me-1"></i> About Section
    </button>
  </li>
</ul>

<div class="tab-content" id="settingsTabContent">
  <!-- CAROUSEL TAB -->
  <div class="tab-pane fade show active" id="carouselTabPane" role="tabpanel" aria-labelledby="carousel-tab">
    <!-- ==== PASTE YOUR ENTIRE HOMEPAGE CAROUSEL CODE HERE ==== -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 swks-header-row">
      <h2 class="mb-0" style="color: var(--swks-green); font-weight: bold; padding-bottom:10px;">
        <i class="bi bi-images me-2"></i>Homepage Carousel
      </h2>
      <button class="btn btn-success shadow-sm fw-semibold px-4" data-bs-toggle="modal" data-bs-target="#addCarouselModal">
        <i class="bi bi-plus-circle me-1"></i> Add Carousel Image
      </button>
    </div>

  <div class="card border-0 shadow-lg rounded-4">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle table-hover mb-0">
          <thead class="table-success rounded-4">
            <tr>
              <th style="width:4%; text-align:center;">#</th>
              <th style="width:30%; text-align:center;">Image</th>
              <th style="width:40%; text-align:center;">Description</th>
              <th style="width:10%; text-align:center;">Status</th>
              <th style="width:16%; text-align:center;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php $count = 1; while ($row = $carouselQ->fetch_assoc()): ?>
            <tr>
              <td class="text-center fw-bold"><?= $count++ ?></td>
              <td class="text-center">
                <?php if (!empty($row['image_path'])): ?>
                <img src="/swks/<?= htmlspecialchars($row['image_path']) ?>" alt="Carousel" class="rounded shadow-sm" style="width: 100px; height: 120px; object-fit: cover; border: 1px solid #ccc;">
                <?php else: ?>
                <div class="text-muted small">No image</div>
                <?php endif; ?>
              </td>
              <td class="text-center"><?= htmlspecialchars($row['description']) ?></td>
              <td class="text-center">
                <?php if ($row['status'] === 'visible'): ?>
                <span class="badge bg-success px-3 py-2">Visible</span>
                <?php else: ?>
                <span class="badge bg-secondary px-3 py-2">Hidden</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <div class="d-flex justify-content-center gap-2">
                  <button class="btn btn-sm btn-swks-outline rounded-pill px-3 fw-semibold"
                    data-bs-toggle="modal" data-bs-target="#editCarouselModal"
                    data-id="<?= $row['setting_id'] ?>"
                    data-desc="<?= htmlspecialchars($row['description'], ENT_QUOTES) ?>"
                    data-image="/swks/<?= htmlspecialchars($row['image_path']) ?>">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </button>
                  <button 
                        class="btn btn-sm <?= $row['status'] === 'visible' ? 'btn-danger' : 'btn-success' ?> rounded-pill px-3 fw-semibold"
                        data-bs-toggle="modal" 
                        data-bs-target="#confirmToggleModal"
                        data-id="<?= $row['setting_id'] ?>"
                        data-status="<?= $row['status'] ?>"
                        >
                        <i class="bi <?= $row['status'] === 'visible' ? 'bi-eye-slash' : 'bi-eye' ?> me-1"></i>
                        <?= $row['status'] === 'visible' ? 'Hide' : 'Show' ?>
                    </button>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

 <div class="tab-pane fade" id="aboutTabPane" role="tabpanel" aria-labelledby="about-tab">
  <div class="card border-0 shadow-lg rounded-4">
    <div class="card-body">
      <h4 class="fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>About Sentro ng Wika, Kultura at Sining</h4>
      <form action="update_about.php" method="POST" enctype="multipart/form-data" id="aboutForm">
        <div class="mb-3">
          <label for="aboutContent" class="form-label fw-semibold">About SWKS</label>
          <textarea class="form-control" id="aboutContent" name="about_content" rows="5" required><?= htmlspecialchars($about['description'] ?? '') ?></textarea>
        </div>
        <div class="mb-3">
          <label for="departmentHead" class="form-label fw-semibold">Department Head or ACA Coordinator</label>
          <input type="text" class="form-control" id="departmentHead" name="department_head" value="<?= htmlspecialchars($about['department_head'] ?? '') ?>" required>
        </div>
        <!-- Department Head/ACA Coordinator Profile Picture -->
<div class="mb-3">
  <label for="headProfile" class="form-label fw-semibold">ACA Coordinator Profile Picture</label>
  <?php if (!empty($about['head_profile'])): ?>
    <div class="mb-2">
      <img src="/swks/<?= htmlspecialchars($about['head_profile']) ?>" alt="ACA Coordinator" style="max-width:120px; max-height:120px;" class="rounded shadow-sm mb-2">
    </div>
  <?php endif; ?>
  <input type="file" class="form-control" id="headProfile" name="head_profile" accept="image/*">
  <div class="form-text">Upload to change image. Leave blank to keep current image.</div>
</div>

<!-- Org Chart -->
<div class="mb-3">
  <label for="orgChart" class="form-label fw-semibold">Organizational Chart</label>
  <?php if (!empty($about['org_chart'])): ?>
    <div class="mb-2">
      <img src="/swks/<?= htmlspecialchars($about['org_chart']) ?>" alt="Organizational Chart" style="max-width:180px; max-height:180px;" class="rounded shadow-sm mb-2">
    </div>
  <?php endif; ?>
  <input type="file" class="form-control" id="orgChart" name="org_chart" accept="image/*">
  <div class="form-text">Upload to change image. Leave blank to keep current image.</div>
</div>

        <button type="submit" class="btn btn-success fw-semibold px-4 me-2">
          <i class="bi bi-save me-1"></i> Update
        </button>
        <button type="button" class="btn btn-secondary px-3" onclick="resetAboutForm()">Cancel</button>
      </form>
    </div>
  </div>
</div>
</div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editCarouselModal" tabindex="-1" aria-labelledby="editCarouselModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-sm">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-success"><i class="bi bi-pencil-square me-1"></i> Edit Carousel Image</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="update_carousel.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="setting_id" id="editCarouselId">
        <div class="modal-body px-4 pb-0">
          <div class="mb-3">
            <label for="editDesc" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="editDesc" name="description" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Current Image</label><br>
            <img id="editImagePreview" src="#" class="rounded shadow-sm" style="width: 100px; height: 100px; object-fit: cover;">
          </div>
          <div class="mb-3">
            <label for="editImage" class="form-label fw-semibold">Change Image</label>
            <input type="file" class="form-control" id="editImage" name="image" accept="image/*">
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="submit" class="btn btn-success fw-semibold px-4"><i class="bi bi-save me-1"></i> Update</button>
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Add Carousel Modal -->
<div class="modal fade" id="addCarouselModal" tabindex="-1" aria-labelledby="addCarouselModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-sm">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-success">
          <i class="bi bi-plus-circle me-1"></i> Add Carousel Image
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="add_carousel.php" method="POST" enctype="multipart/form-data">
        <div class="modal-body px-4 pb-0">
          <div class="mb-3">
            <label for="carouselImage" class="form-label fw-semibold">Upload Image</label>
            <input type="file" class="form-control" id="carouselImage" name="image" accept="image/*" required>
          </div>
          <div class="mb-3">
            <label for="carouselDesc" class="form-label fw-semibold">Description</label>
            <textarea class="form-control" id="carouselDesc" name="description" rows="3" required></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4">
          <button type="submit" class="btn btn-success fw-semibold px-4">
            <i class="bi bi-save me-1"></i> Save
          </button>
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Toggle Confirm Modal -->
<div class="modal fade" id="confirmToggleModal" tabindex="-1" aria-labelledby="confirmToggleLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 shadow-sm">
      <form action="toggle_carousel.php" method="POST">
        <input type="hidden" name="setting_id" id="toggleSettingId">
        <input type="hidden" name="current_status" id="toggleCurrentStatus">

        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold text-danger" id="confirmToggleLabel">
            <i class="bi bi-exclamation-triangle me-2"></i> Confirm Action
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body px-4">
          <p class="mb-0" id="toggleConfirmText">Are you sure you want to hide this image?</p>
        </div>

        <div class="modal-footer border-0 px-4 pb-4">
          <button type="submit" class="btn btn-danger px-4 fw-semibold">Yes, Confirm</button>
          <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['aboutUpdateSuccess'])): ?>
<script>
    sessionStorage.setItem('aboutUpdateSuccess', '1');
    window.location = "web_settings.php#aboutTabPane"; // optional: stay on about tab after reload
</script>
<?php endif; ?>

<script>
  if (sessionStorage.getItem('carouselAddSuccess')) {
    Swal.fire({
      icon: 'success',
      title: 'Carousel Added!',
      text: 'The image has been successfully uploaded.',
      confirmButtonColor: '#198754'
    });
    sessionStorage.removeItem('carouselAddSuccess');
  }
   if (sessionStorage.getItem('carouselUpdateSuccess')) {
    Swal.fire({
      icon: 'success',
      title: 'Updated!',
      text: 'The carousel item has been successfully updated.',
      confirmButtonColor: '#198754'
    });
    sessionStorage.removeItem('carouselUpdateSuccess');
  }
   if (sessionStorage.getItem('carouselToggleSuccess')) {
    Swal.fire({
      icon: 'success',
      title: 'Status Updated',
      text: 'Carousel visibility status has been changed.',
      confirmButtonColor: '#198754'
    });
    sessionStorage.removeItem('carouselToggleSuccess');
  }
  if (sessionStorage.getItem('aboutUpdateSuccess')) {
  Swal.fire({
    icon: 'success',
    title: 'About Section Updated!',
    text: 'The information has been successfully updated.',
    confirmButtonColor: '#198754'
  });
  sessionStorage.removeItem('aboutUpdateSuccess');
}

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
const editModal = document.getElementById('editCarouselModal');
editModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  document.getElementById('editCarouselId').value = button.getAttribute('data-id');
  document.getElementById('editDesc').value = button.getAttribute('data-desc');
  document.getElementById('editImagePreview').src = button.getAttribute('data-image');
});

const toggleModal = document.getElementById('confirmToggleModal');
toggleModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  const settingId = button.getAttribute('data-id');
  const currentStatus = button.getAttribute('data-status');

  document.getElementById('toggleSettingId').value = settingId;
  document.getElementById('toggleCurrentStatus').value = currentStatus;

  const confirmText = document.getElementById('toggleConfirmText');
  confirmText.textContent = currentStatus === 'visible' 
    ? "Are you sure you want to hide this image from the homepage?"
    : "Do you want to make this image visible on the homepage?";
});
function previewImage(event, previewId) {
  const [file] = event.target.files;
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      const preview = document.getElementById(previewId);
      preview.src = e.target.result;
      preview.style.display = 'block';
    }
    reader.readAsDataURL(file);
  }
}
function resetAboutForm() {
  document.getElementById('aboutForm').reset();
  // Optionally reset image preview to old image
}

document.addEventListener('DOMContentLoaded', function() {
  // If there's a hash in the URL, show the correct tab
  const hash = window.location.hash;
  if(hash) {
    const tab = document.querySelector(`button[data-bs-target="${hash}"]`);
    if(tab) tab.click();
  }
});

</script>
</body>
</html>
