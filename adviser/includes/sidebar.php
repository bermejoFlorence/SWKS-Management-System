<?php
// adviser/includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);

// Group active state for Applicants section
$applicantPages = ['applications.php','applicant_view.php','applicant_edit.php','application_review.php'];

/* ===== Logo URL resolver (same as admin) ===== */
$docRoot     = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
$sideDirDisk = realpath(__DIR__);                 // disk path of this folder
$logoUrl     = 'logo.png';                        // fallback
$logoExists  = false;
$logoQ       = '';                                // cache-bust

if ($sideDirDisk && $docRoot && strpos($sideDirDisk, $docRoot) === 0) {
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($sideDirDisk, strlen($docRoot))); // e.g. /swks/adviser/includes
    $logoUrl = ($rel === '' ? '' : $rel) . '/logo.png';
    if ($logoUrl === '' || $logoUrl[0] !== '/') $logoUrl = '/' . $logoUrl;
    $logoExists = is_file($sideDirDisk . DIRECTORY_SEPARATOR . 'logo.png');
    if ($logoExists) {
        $logoQ = '?v=' . filemtime($sideDirDisk . DIRECTORY_SEPARATOR . 'logo.png');
    }
}
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <button class="close-sidebar" onclick="toggleSidebar()" tabindex="0" aria-label="Close sidebar">
    <i class="bi bi-x"></i>
  </button>

  <div class="logo">
    <a href="index.php" class="swks-logo-link" aria-label="SWKS Home">
      <?php if ($logoExists): ?>
        <img
          src="<?= htmlspecialchars($logoUrl . $logoQ) ?>"
          alt="SWKS"
          style="
            display:block;
            width: 190px;       /* ← laki ng logo (pwede 200px) */
            max-width: 82%;
            height: auto;       /* keep aspect ratio */
            max-height: 160px;  /* safety cap */
            object-fit: contain;
            margin: 0 auto;
          "
        >
      <?php else: ?>
        <span class="swks-logo-text">SWKS</span>
      <?php endif; ?>
    </a>
  </div>

  <ul>
    <li class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
      <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </li>

    <!-- Applicants (keeps your route names) -->
    <li class="<?= in_array($currentPage, $applicantPages, true) ? 'active' : '' ?>">
      <a href="applications.php"><i class="bi bi-people"></i> Applicants</a>
    </li>

    <li class="<?= $currentPage === 'organization.php' ? 'active' : '' ?>">
      <a href="organization.php"><i class="bi bi-building"></i> Organization</a>
    </li>

    <li class="<?= $currentPage === 'forum.php' ? 'active' : '' ?>">
      <a href="forum.php"><i class="bi bi-chat-dots"></i> Forum</a>
    </li>

    <li class="<?= $currentPage === 'inventory.php' ? 'active' : '' ?>">
      <a href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a>
    </li>
  </ul>
</div>
