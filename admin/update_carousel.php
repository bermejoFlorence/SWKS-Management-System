<?php
session_start();
include_once '../database/db_connection.php';

// ✅ Check if logged in and admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Unauthorized access.");
}

$setting_id = intval($_POST['setting_id'] ?? 0);
$description = trim($_POST['description'] ?? '');

if ($setting_id <= 0 || !$description) {
    die("Invalid input.");
}

// ✅ Get current image_path from DB
$current = $conn->prepare("SELECT image_path FROM web_settings WHERE setting_id = ?");
$current->bind_param("i", $setting_id);
$current->execute();
$current->bind_result($existingImage);
$current->fetch();
$current->close();

$image_path = $existingImage;

// ✅ Handle optional image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!in_array($_FILES['image']['type'], $allowed_types)) {
        die("Invalid image type.");
    }

    $upload_dir = '../uploads/';
    $filename = time() . '_' . basename($_FILES['image']['name']);
    $target_path = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
        $image_path = 'uploads/' . $filename;
    } else {
        die("Failed to upload image.");
    }
}

// ✅ Update DB
$update = $conn->prepare("UPDATE web_settings SET description = ?, image_path = ? WHERE setting_id = ?");
$update->bind_param("ssi", $description, $image_path, $setting_id);

if ($update->execute()) {
    echo "<script>
        sessionStorage.setItem('carouselUpdateSuccess', '1');
        window.location.href = 'web_settings.php';
    </script>";
} else {
    echo "Failed to update record.";
}

$update->close();
