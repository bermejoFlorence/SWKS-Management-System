<?php
session_start();
include_once '../database/db_connection.php';

// Sanitize input
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);

// Validate required fields
if (!$name || !$description || $quantity <= 0) {
    $_SESSION['error'] = 'Missing required fields.';
    header('Location: inventory.php');
    exit;
}

// Handle image upload
$imagePath = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../uploads/inventory/'; // C:\xampp\htdocs\swks\uploads\inventory
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileTmp = $_FILES['image']['tmp_name'];
    $fileName = basename($_FILES['image']['name']);
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $safeName = 'item_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $uploadPath = $uploadDir . $safeName;

    if (move_uploaded_file($fileTmp, $uploadPath)) {
        $imagePath = 'uploads/inventory/' . $safeName; // path for DB and HTML
    }
}

// Set org_id (null for now, or change if needed)
$orgId = null;

// Insert into DB
$stmt = $conn->prepare("INSERT INTO inventory_items (name, description, quantity_available, status, org_id, image, created_at) VALUES (?, ?, ?, 'active', ?, ?, NOW())");
$stmt->bind_param("ssiss", $name, $description, $quantity, $orgId, $imagePath);

if ($stmt->execute()) {
    echo "<script>
        sessionStorage.setItem('inventoryAddSuccess', '1');
        window.location = 'inventory.php';
    </script>";
} else {
    echo "<script>
        alert('Failed to save item: " . $conn->error . "');
        window.location = 'inventory.php';
    </script>";
}
?>
