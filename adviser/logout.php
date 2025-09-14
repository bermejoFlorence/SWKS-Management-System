<?php
session_start();
session_unset();
session_destroy();
// Remove session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logging Out...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
Swal.fire({
    icon: 'success',
    title: 'Logged Out',
    text: 'You have been successfully logged out.',
    showConfirmButton: false,
    timer: 1800
}).then(() => {
    window.location.href = '/swks/login.php';
});
setTimeout(function() {
    window.location.href = '/swks/login.php';
}, 1900);
</script>
</body>
</html>
