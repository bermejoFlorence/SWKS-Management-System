<?php
include_once 'includes/auth_admin.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aca Coordinator Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your custom style -->
    <link rel="stylesheet" href="styles/style.css">
</head>
<style>
    .card {
    border-radius: 16px;
    transition: box-shadow 0.2s;
}
.card:hover {
    box-shadow: 0 8px 32px rgba(0,0,0,0.11);
}
.display-4 {
    font-size: 2.8rem;
}
.main-content .card {
    border-radius: 18px;
    transition: box-shadow 0.2s;
}
.main-content .card:hover {
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}

.main-content {
    padding-top: 70px; /* tighter to top, pwede mo pa liitan */
}
@media (max-width: 575px) {
    .display-4 {
        font-size: 2.2rem;
    }
}


</style>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

    </div>
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
    </script>
</body>
</html>
