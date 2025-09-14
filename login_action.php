<?php
session_start();
include 'database/db_connection.php'; // adjust path if needed

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email && $password) {
    $stmt = $conn->prepare("SELECT * FROM user WHERE user_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['user_password'])) {
            // Set main session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['org_id'] = $user['org_id'];
            $_SESSION['user_email'] = $user['user_email'];
            $_SESSION['user_role'] = $user['user_role'];

            $userId = $user['user_id'];
            $role = strtolower(trim($user['user_role']));

            // Default
            $_SESSION['user_name'] = 'User';
            $_SESSION['profile_pic'] = '';

            // Fetch name (and profile pic) based on role
            if ($role === 'admin') {
                $q = $conn->prepare("SELECT coor_name, profile_pic FROM aca_coordinator_details WHERE user_id=?");
                $q->bind_param("i", $userId);
                $q->execute();
                $q->bind_result($name, $profilePic);
                if ($q->fetch()) {
                    $_SESSION['user_name'] = $name ?: "Admin";
                    $_SESSION['profile_pic'] = $profilePic ?: '';
                }
                $q->close();
            } elseif ($role === 'adviser') {
               $q = $conn->prepare("SELECT adviser_fname, profile_pic FROM adviser_details WHERE user_id=?");
                $q->bind_param("i", $userId);
                $q->execute();
                $q->bind_result($fullName, $profilePic);
                if ($q->fetch()) {
                    $_SESSION['user_name'] = $fullName ?: "Adviser";
                    $_SESSION['profile_pic'] = $profilePic ?: '';
                }

                $q->close();
            } elseif ($role === 'member') {
                $q = $conn->prepare("SELECT full_name, profile_picture FROM member_details WHERE user_id=?");
                $q->bind_param("i", $userId);
                $q->execute();
                $q->bind_result($name, $profilePic);
                if ($q->fetch()) {
                    $_SESSION['user_name'] = $name ?: "Member";
                    $_SESSION['profile_pic'] = $profilePic ?: '';
                }
                $q->close();
            }

            // Redirect to login.php with success and role (for SweetAlert)
            header("Location: login.php?success=1&role=$role");
            exit();
        } else {
            // Wrong password
            header("Location: login.php?error=Invalid+email+or+password");
            exit();
        }
    } else {
        // Email not found
        header("Location: login.php?error=Invalid+email+or+password");
        exit();
    }
} else {
    // Missing fields
    header("Location: login.php?error=Please+enter+both+email+and+password");
    exit();
}
?>
