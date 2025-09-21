<?php
include_once '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org_name = trim($_POST['org_name']);
    $org_desc = trim($_POST['org_desc']);

    if (!empty($org_name) && !empty($org_desc)) {
        $stmt = $conn->prepare("INSERT INTO organization (org_name, org_desc) VALUES (?, ?)");
        $stmt->bind_param("ss", $org_name, $org_desc);
        if ($stmt->execute()) {
            // Show one-time redirect page with success status
            echo "<script>
                sessionStorage.setItem('orgAddSuccess', '1');
                window.location.href = 'organization.php';
            </script>";
            exit;
        }
    }
}

// If failed or invalid, fallback
header("Location: organization.php?status=error");
exit;
?>
