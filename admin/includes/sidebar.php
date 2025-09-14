<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="close-sidebar" onclick="toggleSidebar()" tabindex="0" aria-label="Close sidebar">
        <i class="bi bi-x"></i>
    </button>
    <div class="logo">
        <a href="index.php" class="swks-logo-link">
            <!-- Debugging: Show the path being used -->
            <?php echo "<!-- Current path: " . $_SERVER['PHP_SELF'] . " -->"; ?>
            
            <!-- Try multiple path options -->
            <img src="./assets/9.png" alt="SWKS Logo" class="logo-image" 
                 style="max-height: 100px; max-width: 150px; height: auto; width: auto; display: block;"
                 onerror="this.onerror=null; this.src='../assets/9.png'; console.log('Trying fallback path');"
                 onload="console.log('Logo loaded successfully');">
            
            <!-- Fallback text in case image fails -->
            <noscript>
                <span class="swks-logo-text">SWKS</span>
            </noscript>
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