<?php
session_start();
require '../database/db_connection.php'; // Your database connection file

$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? '';
$userEmail = $_SESSION['user_email'] ?? '';
$role = $_SESSION['user_role'] ?? '';

if (!$userId) {
    echo "<script>
        alert('User not logged in.');
        window.location.href = 'index.php';
    </script>";
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
        $profilePicPath = $webUploadDir . $filename;
        $_SESSION['profile_pic'] = $profilePicPath;
    }
}


if (!empty($_POST['new_password']) && $_POST['new_password'] === $_POST['confirm_new_password']) {
    $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    // Store the hashed password if needed in your users table
    // Example: UPDATE users SET password = ? WHERE id = ?
}

// Update aca_coordinator_details
$sql = "UPDATE aca_coordinator_details 
        SET coor_name = ?, coor_email = ?, profile_pic = ?, created_at = NOW()
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $userName, $userEmail, $profilePicPath, $userId);
$stmt->execute();

$success = $stmt->affected_rows > 0;

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
