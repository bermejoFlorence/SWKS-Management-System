<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// (Optional) para active din kapag nasa subpages ka ng applicants
$applicantPages = ['applications.php','applicant_view.php','applicant_edit.php','application_review.php'];
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="close-sidebar" onclick="toggleSidebar()" tabindex="0" aria-label="Close sidebar">
        <i class="bi bi-x"></i>
    </button>
    <div class="logo">
        <a href="index.php" class="swks-logo-link">
            <span class="swks-logo-text">SWKS</span>
        </a>
    </div>
    <ul>
        <li class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        </li>

                <!-- NEW: Applicants -->
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
