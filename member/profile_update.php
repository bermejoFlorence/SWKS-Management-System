<?php
session_start();
require '../database/db_connection.php';

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
if (!$userId) {
    echo "<script>alert('User not logged in.'); window.location.href = 'index.php';</script>";
    exit;
}

$uploadDir = realpath(__DIR__ . '/../uploads');
if ($uploadDir === false) {
    $uploadDir = __DIR__ . '/../uploads';
    mkdir($uploadDir, 0755, true);
}
$uploadDir .= '/';
$webUploadDir = 'uploads/';

$profilePicPath = $_SESSION['profile_pic'] ?? null;

if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $filename = uniqid('profile_', true) . "." . $ext;
    $destination = $uploadDir . $filename;

    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
        $profilePicPath = $filename; // Just the filename (not full path)
        $_SESSION['profile_pic'] = $profilePicPath;
    }
}

// Optional: Handle password change (user table)
if (!empty($_POST['new_password']) && $_POST['new_password'] === $_POST['confirm_new_password']) {
    $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $stmt->execute();
    $stmt->close();
}

// Update member_details (NOTE: Add other fields if needed)
$sql = "UPDATE member_details SET profile_picture = ? WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $profilePicPath, $userId);
$stmt->execute();
$success = $stmt->affected_rows > 0;
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Update Profile</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
Swal.fire({
  icon: '<?php echo $success ? "success" : "error"; ?>',
  title: '<?php echo $success ? "Profile Updated!" : "Update Failed"; ?>',
  text: '<?php echo $success ? "Your profile has been updated successfully." : "No changes made or something went wrong."; ?>',
  confirmButtonText: 'OK'
}).then(() => {
  window.location.href = 'index.php'; // Change this to your intended redirect
});
</script>
</body>
</html>
