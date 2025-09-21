<?php
session_start();
include_once '../database/db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Unauthorized access.");
}

$setting_id = intval($_POST['setting_id'] ?? 0);
$current_status = $_POST['current_status'] ?? '';

if (!$setting_id || !in_array($current_status, ['visible', 'hidden'])) {
    die("Invalid request.");
}

$new_status = ($current_status === 'visible') ? 'hidden' : 'visible';

$stmt = $conn->prepare("UPDATE web_settings SET status = ? WHERE setting_id = ?");
$stmt->bind_param("si", $new_status, $setting_id);

if ($stmt->execute()) {
    echo "<script>
        sessionStorage.setItem('carouselToggleSuccess', '1');
        window.location.href = 'web_settings.php';
    </script>";
} else {
    echo "Failed to update status.";
}
$stmt->close();
