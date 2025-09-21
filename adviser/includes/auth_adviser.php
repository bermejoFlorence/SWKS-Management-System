<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (
        !isset($_SESSION['user_id']) ||
    strtolower(trim($_SESSION['user_role'])) !== 'adviser' ||
    !isset($_SESSION['org_id'])
) {
    header("Location: ../login.php?error=Unauthorized+access");
    exit();
}
?>
