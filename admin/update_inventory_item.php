<?php
session_start();
include_once '../database/db_connection.php';

$item_id = intval($_POST['item_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$quantity = intval($_POST['quantity'] ?? 0);

// Validate input
if ($item_id <= 0 || !$name || !$description || $quantity <= 0) {
    echo "<script>
        alert('Missing or invalid input.');
        window.location = 'inventory.php';
    </script>";
    exit;
}

// Fetch current item to get old image path
$currentQ = $conn->prepare("SELECT image FROM inventory_items WHERE item_id = ?");
$currentQ->bind_param("i", $item_id);
$currentQ->execute();
$currentResult = $currentQ->get_result();
$current = $currentResult->fetch_assoc();
$currentImage = $current['image'] ?? null;

$imagePath = $currentImage;

// If a new image is uploaded
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/inventory/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileTmp = $_FILES['image']['tmp_name'];
    $fileName = basename($_FILES['image']['name']);
    $ext = pathinfo($fileName, PATHINFO_EXTENSION);
    $safeName = 'item_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $uploadPath = $uploadDir . $safeName;

    if (move_uploaded_file($fileTmp, $uploadPath)) {
        $imagePath = 'uploads/inventory/' . $safeName;
    }
}

// Update database
$stmt = $conn->prepare("UPDATE inventory_items SET name=?, description=?, quantity_available=?, image=? WHERE item_id=?");
$stmt->bind_param("ssisi", $name, $description, $quantity, $imagePath, $item_id);

if ($stmt->execute()) {
    echo "<script>
        sessionStorage.setItem('inventoryUpdateSuccess', '1');
        window.location = 'inventory.php';
    </script>";
} else {
    echo "<script>
        alert('Update failed. Try again.');
        window.location = 'inventory.php';
    </script>";
}
?>
