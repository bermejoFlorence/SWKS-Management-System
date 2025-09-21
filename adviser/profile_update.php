<?php
session_start();
require '../database/db_connection.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    echo "<script>
        alert('User not logged in.');
        window.location.href = 'index.php';
    </script>";
    exit;
}

$debug = []; // DEBUGGING LOGS

// Set upload directories (absolute for PHP, relative for browser)
// Para adviser side (file is in /adviser/)
$uploadDir = dirname(__DIR__) . '/uploads/';  // Absolute path to root-level uploads
$relativeDir = 'uploads/';                    // For DB/browser
// Get current profile_pic from database, not session
$stmt = $conn->prepare("SELECT profile_pic FROM adviser_details WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($existingProfilePic);
$stmt->fetch();
$stmt->close();
$profilePicPath = $existingProfilePic ?? null;


// Fetch current password hash
$stmt = $conn->prepare("SELECT user_password FROM user WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($currentHash);
$stmt->fetch();
$stmt->close();

$changePassword = false;

// Handle password change
if (!empty($_POST['new_password']) && !empty($_POST['current_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_new_password'];

    if (!password_verify($currentPassword, $currentHash)) {
        $errorMsg = "Current password is incorrect.";
        $success = false;
        goto output;
    }

    if ($newPassword !== $confirmPassword) {
        $errorMsg = "New passwords do not match.";
        $success = false;
        goto output;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $changePassword = true;
}

// Handle profile pic upload
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
    $debug[] = "Upload error code: " . $_FILES['profile_pic']['error'];
    if ($_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed)) {
            $errorMsg = "Invalid file type. Allowed: jpg, jpeg, png, gif, webp.";
            $success = false;
            $debug[] = $errorMsg;
            goto output;
        }

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $errorMsg = "Failed to create uploads directory.";
                $success = false;
                $debug[] = $errorMsg;
                goto output;
            }
        }

        $filename = uniqid('profile_', true) . "." . $ext;
        $destination = $uploadDir . $filename;
        $debug[] = "Temp name: " . $_FILES['profile_pic']['tmp_name'];
        $debug[] = "Destination: $destination";
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
            $profilePicPath = $relativeDir . $filename;
            $_SESSION['profile_pic'] = $profilePicPath;
            $debug[] = "File moved successfully!";
        } else {
            $errorMsg = "Failed to move uploaded file!";
            $success = false;
            $debug[] = $errorMsg;
            goto output;
        }
    } else {
        $errorMsg = "File upload error (code: " . $_FILES['profile_pic']['error'] . ")";
        $success = false;
        $debug[] = $errorMsg;
        goto output;
    }
}

// Update user table if password changed
if ($changePassword) {
    $stmt = $conn->prepare("UPDATE user SET user_password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $stmt->execute();
    $stmt->close();
}

// Update adviser_details table (for adviser side)
$sql = "UPDATE adviser_details 
        SET profile_pic = ?
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $profilePicPath, $userId);
$stmt->execute();

$success = $stmt->affected_rows > 0 || $changePassword;
$stmt->close();

output:
?>
<!DOCTYPE html>
<html>
<head>
  <title>Update Profile</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php if (!empty($debug)): ?>
<pre style="color:orange; background:#333; padding:10px; margin:10px;">
<?php echo implode("\n", $debug); ?>
</pre>
<?php endif; ?>
<script>
Swal.fire({
  icon: '<?php echo $success ? "success" : "error"; ?>',
  title: '<?php echo $success ? "Profile Updated!" : "Update Failed"; ?>',
  text: '<?php echo $success ? "Your profile has been updated successfully." : ($errorMsg ?? "No changes made or something went wrong."); ?>',
  confirmButtonText: 'OK'
}).then(() => {
  window.location.href = 'index.php'; // Change this to your intended redirect
});
</script>
</body>
</html>
