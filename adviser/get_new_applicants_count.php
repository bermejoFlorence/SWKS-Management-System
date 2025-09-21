<?php
session_start();
include '../database/db_connection.php'; // adjust path as needed

// Check kung adviser at may org_id
if (!isset($_SESSION['org_id'])) {
    echo 0; // not logged in as adviser or no org_id
    exit;
}

$adviser_org_id = $_SESSION['org_id'];

$sql = "SELECT COUNT(*) AS new_applicants FROM member_details WHERE preferred_org = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adviser_org_id);
$stmt->execute();
$stmt->bind_result($new_applicants);
$stmt->fetch();
$stmt->close();

echo $new_applicants;
?>
