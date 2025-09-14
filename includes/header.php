<!-- include/header.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SWKS Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="styles/style.css">
</head>
<body>

<!-- HEADER -->
<header class="navbar navbar-expand-lg navbar-dark custom-green py-2 fixed-top">
    <div class="container-fluid d-flex justify-content-between align-items-start flex-nowrap px-3">
        
        <!-- Logo + Title -->
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="assets/cbsua_logo.png" alt="CBSUA Logo" class="me-2 rounded-circle bg-white" height="44" width="44">
            <div class="text-block">
                <span class="univ-title">Central Bicol State University of Agriculture</span><br>
                <span class="univ-subtitle">Sentro ng Wika, Kultura at Sining</span>
            </div>
        </a>

        <!-- Hamburger Button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
            aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>

    <!-- ❌ MALI: Nakalabas sa .container -->
    <!-- <div class="collapse navbar-collapse" id="mainNav"> -->

    <!-- ✅ TAMA: Ilagay sa loob ng .container -->
    <div class="container-fluid">
        <div class="collapse navbar-collapse" id="mainNav">
            <!-- header.php navigation updated -->
            <ul class="navbar-nav ms-auto p-3 p-lg-0">
                <li class="nav-item"><a class="nav-link fw-bold text-white" href="index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link fw-bold text-white" href="about.php">About</a></li>
                <li class="nav-item"><a class="nav-link fw-bold text-white" href="login.php">Login</a></li>
            </ul>
        </div>
    </div>
</header>
