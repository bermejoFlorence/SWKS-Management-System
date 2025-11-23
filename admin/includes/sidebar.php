<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Build a correct web URL to logo.png sitting BESIDE this sidebar.php
$docRoot     = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
$sideDirDisk = realpath(__DIR__);                                  // disk path of folder containing sidebar.php
$logoUrl     = 'logo.png';                                         // fallback
$logoExists  = false;
$logoQ       = '';                                                 // for cache-busting

if ($sideDirDisk && $docRoot && strpos($sideDirDisk, $docRoot) === 0) {
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($sideDirDisk, strlen($docRoot))); // e.g. /swks/admin/includes
    $logoUrl = ($rel === '' ? '' : $rel) . '/logo.png';
    if ($logoUrl === '' || $logoUrl[0] !== '/') $logoUrl = '/' . $logoUrl;
    $logoExists = is_file($sideDirDisk . DIRECTORY_SEPARATOR . 'logo.png');
    if ($logoExists) {
        $logoQ = '?v=' . filemtime($sideDirDisk . DIRECTORY_SEPARATOR . 'logo.png'); // cache-bust
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
    width: 180px;       /* laki ng logo (pwede 200px kung kasya) */
    max-width: 80%;     /* iwas sumagad sa lapad ng sidebar */
    height: auto;       /* keep aspect ratio */
    max-height: 140px;  /* kontrol sa taas; pwede taasan kung kailangan */
    object-fit: contain;
    margin: 0 auto;     /* center sa sidebar */
  "
>

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
            <a href="inventory.php"><i class="bi bi-boxes"></i> Inventory</a>
        </li>
        <li class="<?= $currentPage == 'web_settings.php' ? 'active' : '' ?>">
            <a href="web_settings.php"><i class="bi bi-gear"></i> Web Settings</a>
        </li>
    </ul>
</div>
