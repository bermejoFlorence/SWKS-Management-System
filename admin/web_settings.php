<?php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

$user_id = $_SESSION['user_id'];
$carouselQ = $conn->query("SELECT * FROM web_settings WHERE type = 'carousel' ORDER BY created_at DESC");

// Add this for About section
$aboutQ = $conn->query("SELECT * FROM web_settings WHERE type = 'about' LIMIT 1");
$about = $aboutQ->fetch_assoc();

$org_id  = (int)($_SESSION['org_id'] ?? 0);
$use_org = $org_id > 0;

$recActive = $appActive = [];
$recHistory = $appHistory = [];

if ($use_org) {
  // ORG-SPECIFIC
  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id=? AND role='recommending_approval' AND is_active=1 
                          ORDER BY started_on DESC LIMIT 1");
  $stmt->bind_param("i",$org_id);
  $stmt->execute();
  $recActive = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id=? AND role='approved' AND is_active=1 
                          ORDER BY started_on DESC LIMIT 1");
  $stmt->bind_param("i",$org_id);
  $stmt->execute();
  $appActive = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id=? AND role='recommending_approval' 
                          ORDER BY started_on DESC, signatory_id DESC");
  $stmt->bind_param("i",$org_id);
  $stmt->execute();
  $recHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id=? AND role='approved' 
                          ORDER BY started_on DESC, signatory_id DESC");
  $stmt->bind_param("i",$org_id);
  $stmt->execute();
  $appHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

} else {
  // GLOBAL (admin has no org_id)
  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id IS NULL AND role='recommending_approval' AND is_active=1 
                          ORDER BY started_on DESC LIMIT 1");
  $stmt->execute();
  $recActive = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id IS NULL AND role='approved' AND is_active=1 
                          ORDER BY started_on DESC LIMIT 1");
  $stmt->execute();
  $appActive = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id IS NULL AND role='recommending_approval' 
                          ORDER BY started_on DESC, signatory_id DESC");
  $stmt->execute();
  $recHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();

  $stmt = $conn->prepare("SELECT * FROM signatories 
                          WHERE org_id IS NULL AND role='approved' 
                          ORDER BY started_on DESC, signatory_id DESC");
  $stmt->execute();
  $appHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $stmt->close();
}

// make sure $history is filled after the above
$history = [
  'recommending_approval' => $recHistory ?? [],
  'approved'              => $appHistory ?? [],
];

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
/* === Softer SWKS theme === */
:root{
  --swks-green: #198754;      /* brand green */
  --swks-blue:  #2b4c7e;      /* MUTED blue (hindi masyadong matingkad) */
  --swks-blue-ink: #4a5f78;   /* blue-gray for inactive text */
  --swks-tab-hover: #f3f6fb;  /* very soft hover bg */
}

/* Tabs: inactive = blue-gray text, active = green with underline */
.nav-tabs{ border-bottom: 2px solid #e9ecef; }
.nav-tabs .nav-link{
  color: var(--swks-blue-ink);
  font-weight: 700;
  border: none;
  border-bottom: 3px solid transparent;
  border-radius: 0;
  padding: .75rem 1rem;
  background: transparent;
  transition: all .15s ease;
}
.nav-tabs .nav-link:hover{
  color: var(--swks-blue);
  background: var(--swks-tab-hover);
}
.nav-tabs .nav-link.active{
  color: var(--swks-green);
  background: transparent;
  border-bottom-color: var(--swks-green);
}
.nav-tabs .nav-link .bi{ margin-right: .35rem; color: inherit; }

/* Badges to match the softer palette */
/* Approved = muted blue */
.badge.text-bg-primary,
span.badge.bg-primary{
  background-color: var(--swks-blue) !important;
  border: 1px solid rgba(0,0,0,.05);
}
/* Recommending = brand green */
.badge.text-bg-success,
span.badge.bg-success{
  background-color: var(--swks-green) !important;
  border: 1px solid rgba(0,0,0,.05);
}


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
  <li class="nav-item" role="presentation">
  <button class="nav-link" id="signatories-tab"
          data-bs-toggle="tab" data-bs-target="#signatoriesTabPane"
          type="button" role="tab" aria-controls="signatoriesTabPane"
          aria-selected="false">
    <i class="bi bi-person-badge me-1"></i> Signatories
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

<!-- ========== SIGNATORIES TAB (NEW) ========== -->
<!-- ========== SIGNATORIES TAB (NO SIGNATURE UPLOAD) ========== -->
<div class="tab-pane fade" id="signatoriesTabPane" role="tabpanel" aria-labelledby="signatories-tab">
  <div class="card border-0 shadow-lg rounded-4">
    <div class="card-body">
      <h4 class="fw-bold mb-3"><i class="bi bi-person-badge me-2"></i>Signatories</h4>

      <!-- FORM (active entries only) -->
      <form action="signatories_save.php" method="POST" id="signatoriesForm">
        <!-- Recommending Approval -->
        <div class="border rounded-3 p-3 mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge text-bg-success rounded-pill px-3 py-2">Recommending Approval</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Name</label>
              <input type="text" class="form-control" name="recommending_name"
       value="<?= htmlspecialchars($recActive['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Designation / Title</label>
         <input type="text" class="form-control" name="recommending_title"
       value="<?= htmlspecialchars($recActive['title'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <!-- Approved -->
        <div class="border rounded-3 p-3 mb-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge text-bg-primary rounded-pill px-3 py-2">Approved</span>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Name</label>
             <input type="text" class="form-control" name="approved_name"
       value="<?= htmlspecialchars($appActive['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Designation / Title</label>
         <input type="text" class="form-control" name="approved_title"
       value="<?= htmlspecialchars($appActive['title'] ?? '') ?>" required>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-success fw-semibold px-4 me-2">
          <i class="bi bi-save me-1"></i> Save Signatories
        </button>
        <button type="button" class="btn btn-secondary px-3"
                onclick="document.getElementById('signatoriesForm').reset()">Cancel</button>
      </form>

      <!-- HISTORY TABLES -->
      <hr class="my-4">
      <!-- History title styled like Signatories -->
<h3 class="history-section-title">
  <i class="bi bi-clock-history"></i>
  Signatories — History
</h3>

      <!-- Recommending Approval History -->
      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge text-bg-success rounded-pill px-3 py-2">Recommending Approval — History</span>
        </div>
       <div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Name</th>
        <th>Designation</th>
        <th>Status</th>
        <th>Started On</th>
        <th>Ended On</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($history['recommending_approval'] as $r): ?>
        <?php
          // compute start/end
   $start = $r['started_on'] ?: $r['updated_at'];
$end   = $r['is_active'] ? null : ($r['ended_on'] ?: $r['updated_at']);

          // nice formatting
          $fmt   = fn($d) => $d ? date('M d, Y', strtotime($d)) : '';
        ?>
        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['title']) ?></td>
          <td>
            <?php if ($r['is_active']): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= $fmt($start) ?></td>
          <td><?= $r['is_active'] ? '<span class="text-success fw-semibold">Present</span>' : $fmt($end) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

      </div>

      <!-- Approved History -->
      <div class="mb-2">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="badge text-bg-primary rounded-pill px-3 py-2">Approved — History</span>
        </div>
   <div class="table-responsive">
  <table class="table table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Name</th>
        <th>Designation</th>
        <th>Status</th>
        <th>Started On</th>
        <th>Ended On</th>
      </tr>
    </thead>
    <tbody>
   <?php foreach ($history['approved'] as $r): ?>
  <?php
    // mas safe gamit ang null coalescing (walang undefined index warning)
    $start = $r['started_on'] ?? ($r['updated_at'] ?? null);
    $end   = !empty($r['is_active']) ? null : ($r['ended_on'] ?? ($r['updated_at'] ?? null));
    $fmt   = fn($d) => $d ? date('M d, Y', strtotime($d)) : '';
  ?>

        <tr>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= htmlspecialchars($r['title']) ?></td>
          <td>
            <?php if ($r['is_active']): ?>
              <span class="badge bg-success">Active</span>
            <?php else: ?>
              <span class="badge bg-secondary">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= $fmt($start) ?></td>
          <td><?= $r['is_active'] ? '<span class="text-success fw-semibold">Present</span>' : $fmt($end) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

      </div>

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
<?php if (isset($_GET['signatoriesUpdateSuccess'])): ?>
<script>
  sessionStorage.setItem('signatoriesUpdateSuccess','1');
  history.replaceState(null, '', 'web_settings.php#signatoriesTabPane');
</script>
<?php endif; ?>

<?php if (isset($_GET['signatoriesRestoreSuccess'])): ?>
<script>
  sessionStorage.setItem('signatoriesRestoreSuccess','1');
  // window.location = "web_settings.php#signatoriesTabPane";
  history.replaceState(null, '', 'web_settings.php#signatoriesTabPane');
</script>
<?php endif; ?>

<?php if (isset($_GET['signatoriesError'])): ?>
<script>
  sessionStorage.setItem('signatoriesUpdateError', <?= json_encode($_GET['signatoriesError']) ?>);
  window.location = "web_settings.php#signatoriesTabPane";
</script>
<?php endif; ?>

<script>
  // Sidebar toggle for mobile
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
  }

  // Global on-load
  document.addEventListener('DOMContentLoaded', function () {
    // --- Open tab from hash (e.g. #signatoriesTabPane)
    const hash = window.location.hash;
    if (hash) {
      const tab = document.querySelector(`button[data-bs-target="${hash}"]`);
      if (tab) tab.click();
    }

    // --- Edit Carousel Modal wiring
    const editModal = document.getElementById('editCarouselModal');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        document.getElementById('editCarouselId').value = button.getAttribute('data-id');
        document.getElementById('editDesc').value = button.getAttribute('data-desc');
        document.getElementById('editImagePreview').src = button.getAttribute('data-image');
      });
    }

    // --- Toggle (show/hide) Carousel Modal wiring
    const toggleModal = document.getElementById('confirmToggleModal');
    if (toggleModal) {
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
    }

    // --- Close sidebar when clicking outside (mobile)
    document.addEventListener('click', function(event) {
      const sidebar   = document.getElementById('sidebar');
      const hamburger = document.querySelector('.hamburger-btn');
      if (window.innerWidth <= 992 && sidebar?.classList.contains('show')) {
        if (!sidebar.contains(event.target) && event.target !== hamburger) {
          sidebar.classList.remove('show');
        }
      }
    });
    document.querySelector('.hamburger-btn')?.addEventListener('click', function(e) {
      e.stopPropagation();
    });

    // --- Restore confirmation (Signatories)
    document.querySelectorAll('.restore-form').forEach(f=>{
      f.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const ok = await Swal.fire({
          icon:'question',
          title:'Restore this entry?',
          text:'It will become the active signatory for this role.',
          showCancelButton:true,
          confirmButtonText:'Yes, restore',
          confirmButtonColor:'#198754'
        });
        if (ok.isConfirmed) e.target.submit();
      });
    });
    const err = sessionStorage.getItem('signatoriesUpdateError');
if (err) {
  Swal.fire({
    icon: 'error',
    title: 'Save failed',
    text: err.replace(/\+/g,' '),
    confirmButtonColor: '#198754'
  });
  sessionStorage.removeItem('signatoriesUpdateError');
}


    // --- SweetAlert toasts (grouped here)
    const toasts = [
      ['carouselAddSuccess',    {title:'Carousel Added!', text:'The image has been successfully uploaded.'}],
      ['carouselUpdateSuccess', {title:'Updated!', text:'The carousel item has been successfully updated.'}],
      ['carouselToggleSuccess', {title:'Status Updated', text:'Carousel visibility status has been changed.'}],
      ['aboutUpdateSuccess',    {title:'About Section Updated!', text:'The information has been successfully updated.'}],
      ['signatoriesUpdateSuccess', {title:'Signatories Saved!', text:'Signatories have been updated.'}],
      ['signatoriesRestoreSuccess', {title:'Restored', text:'The selected signatory has been restored as active.'}],
    ];
    for (const [key, cfg] of toasts) {
      if (sessionStorage.getItem(key)) {
        Swal.fire({ icon:'success', confirmButtonColor:'#198754', ...cfg });
        sessionStorage.removeItem(key);
      }
    }
  });

  // Helpers you already use
  function previewImage(event, previewId) {
    const [file] = event.target.files || [];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const preview = document.getElementById(previewId);
      if (preview) {
        preview.src = e.target.result;
        preview.style.display = 'block';
      }
    };
    reader.readAsDataURL(file);
  }
  function resetAboutForm() {
    document.getElementById('aboutForm')?.reset();
  }
</script>

</body>
</html>
