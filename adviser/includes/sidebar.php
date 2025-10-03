<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// 1) Base path ng kasalukuyang page ( /admin , /adviser , /member ; minsan /swks/admin )
$roleBase = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// 2) Subukan ang ilang kandidato para masakop ang may/without /swks
$candidates = [
  $roleBase . '/includes/logo.png',           // /admin/includes/logo.png  (o /swks/admin/… depende sa host)
  '/swks' . $roleBase . '/includes/logo.png', // /swks/admin/includes/logo.png (kapag nire-rewrite ang /swks → /)
  '/assets/logo.png',                         // optional shared fallback
];

$logoUrl = null;
foreach ($candidates as $u) {
  $abs = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . $u;
  if (is_file($abs)) { $logoUrl = $u; break; }
}
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
