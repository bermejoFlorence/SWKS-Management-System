<?php
session_start();
include_once '../database/db_connection.php';

// ✅ Check if admin is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$description = trim($_POST['description'] ?? '');
$image_path = '';
$type = 'carousel';

if (!$description || !isset($_FILES['image'])) {
    die("Missing fields.");
}

// ✅ Handle image upload
$upload_dir = '../uploads/';
$filename = time() . '_' . basename($_FILES['image']['name']);
$target_file = $upload_dir . $filename;

$allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
if (!in_array($_FILES['image']['type'], $allowed_types)) {
    die("Invalid file type.");
}

if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
    // ✅ Insert into DB
    $stmt = $conn->prepare("INSERT INTO web_settings (user_id, type, image_path, description, status) VALUES (?, ?, ?, ?, 'visible')");
    $rel_path = 'uploads/' . $filename; // This is what will be used in <img src>
    $stmt->bind_param("isss", $user_id, $type, $rel_path, $description);
    
    if ($stmt->execute()) {
        // Optional: SweetAlert using sessionStorage
        echo "<script>
            sessionStorage.setItem('carouselAddSuccess', '1');
            window.location.href = 'web_settings.php';
        </script>";
    } else {
        echo "Failed to insert.";
    }

    $stmt->close();
} else {
    echo "Image upload failed.";
}
