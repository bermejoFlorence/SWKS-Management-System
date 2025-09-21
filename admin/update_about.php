<?php
// REMOVE error reporting in production!
ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$type = 'about';
$about_content = $_POST['about_content'] ?? '';
$department_head = $_POST['department_head'] ?? '';

// --- Handle Profile Image Upload ---
$head_profile_path = null;
if (isset($_FILES['head_profile']) && $_FILES['head_profile']['error'] === UPLOAD_ERR_OK) {
    $img_name = $_FILES['head_profile']['name'];
    $img_tmp = $_FILES['head_profile']['tmp_name'];
    $ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowed)) {
        $new_name = 'head_profile_' . time() . '.' . $ext;
        $target = '../uploads/' . $new_name;
        if (move_uploaded_file($img_tmp, $target)) {
            $head_profile_path = 'uploads/' . $new_name;
        }
    }
}

// --- Handle Org Chart Upload ---
$org_chart_path = null;
if (isset($_FILES['org_chart']) && $_FILES['org_chart']['error'] === UPLOAD_ERR_OK) {
    $img_name = $_FILES['org_chart']['name'];
    $img_tmp = $_FILES['org_chart']['tmp_name'];
    $ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (in_array($ext, $allowed)) {
        $new_name = 'org_chart_' . time() . '.' . $ext;
        $target = '../uploads/' . $new_name;
        if (move_uploaded_file($img_tmp, $target)) {
            $org_chart_path = 'uploads/' . $new_name;
        }
    }
}

// --- Check if 'about' row exists for this user ---
$aboutQ = $conn->query("SELECT * FROM web_settings WHERE type = 'about' AND user_id = $user_id LIMIT 1");
if ($aboutQ->num_rows > 0) {
    // --- UPDATE ---
    $updateSQL = "UPDATE web_settings SET description=?, department_head=?";
    $params = [$about_content, $department_head];
    $types = 'ss';

    if ($head_profile_path) {
        $updateSQL .= ", head_profile=?";
        $params[] = $head_profile_path;
        $types .= 's';
    }
    if ($org_chart_path) {
        $updateSQL .= ", org_chart=?";
        $params[] = $org_chart_path;
        $types .= 's';
    }

    $updateSQL .= " WHERE type='about' AND user_id=?";
    $params[] = $user_id;
    $types .= 'i';

    $stmt = $conn->prepare($updateSQL);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

} else {
    // --- INSERT ---
    // If no image, set as blank string (not null)
    $head_profile_path = $head_profile_path ?? '';
    $org_chart_path = $org_chart_path ?? '';
    $insertSQL = "INSERT INTO web_settings (user_id, type, description, department_head, head_profile, org_chart, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'visible', NOW())";
    $stmt = $conn->prepare($insertSQL);
    $stmt->bind_param('isssss', $user_id, $type, $about_content, $department_head, $head_profile_path, $org_chart_path);
    $stmt->execute();
    $stmt->close();
}

// SweetAlert + stay on About tab
header("Location: web_settings.php?aboutUpdateSuccess=1#aboutTabPane");
exit;
?>
