<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Build a correct web URL to /<role>/includes/logo.png regardless of the page
$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');   // e.g. /swks/admin  | /swks/adviser | /swks/member
$logoUrl = $baseUrl . '/swks/admin/includes/logo.png';

// (optional) fallback check: if logo is missing, we'll show text instead
$logoExists = is_file(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $logoUrl);

?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="close-sidebar" onclick="toggleSidebar()" tabindex="0" aria-label="Close sidebar">
        <i class="bi bi-x"></i>
    </button>
    <div class="logo">
  <a href="index.php" class="swks-logo-link" aria-label="SWKS Home">
    <?php if (!empty($logoExists)): ?>
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="SWKS" class="swks-logo-img" loading="lazy">
    <?php else: ?>
      <span class="swks-logo-text">SWKS</span>
    <?php endif; ?>
  </a>
</div>

    <ul>
        <li class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>
        <li class="<?= $currentPage == 'organization.php' ? 'active' : '' ?>">
            <a href="organization.php"><i class="bi bi-building"></i> Organization</a>
        </li>
        <li class="<?= $currentPage == 'forum.php' ? 'active' : '' ?>">
            <a href="forum.php"><i class="bi bi-chat-dots"></i> Forum</a>
        </li>
        <li class="<?= $currentPage == 'inventory.php' ? 'active' : '' ?>">
            <a href="inventory.php"><i class="bi bi-box-seam"></i> Inventory</a>
        </li>
    </ul>
</div>
