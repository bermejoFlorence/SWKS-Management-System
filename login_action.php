<?php
session_start();
include 'database/db_connection.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

function back($msg){
  header('Location: login.php?error=' . urlencode($msg));
  exit();
}

if (!$email || !$password) back('Please enter both email and password');

// Use backticks — table name `user` is reserved-ish in MySQL
$stmt = $conn->prepare("SELECT * FROM `user` WHERE `user_email` = ?");
if (!$stmt) back('Server error. Please try again.');
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) back('Invalid email or password');
if (!password_verify($password, $user['user_password'])) back('Invalid email or password');

// Role checks
$userId = (int)$user['user_id'];
$role   = strtolower(trim($user['user_role'] ?? ''));
$_SESSION['user_id']    = $userId;
$_SESSION['org_id']     = $user['org_id'] ?? null;
$_SESSION['user_email'] = $user['user_email'] ?? '';
$_SESSION['user_role']  = $user['user_role'] ?? '';
$_SESSION['user_name']  = 'User';
$_SESSION['profile_pic']= '';

if ($role === 'admin') {
  $q = $conn->prepare("SELECT `coor_name`, `profile_pic` FROM `aca_coordinator_details` WHERE `user_id`=?");
  $q->bind_param("i", $userId);
  $q->execute(); $q->bind_result($name,$pic);
  if ($q->fetch()){ $_SESSION['user_name'] = $name ?: 'Admin'; $_SESSION['profile_pic'] = $pic ?: ''; }
  $q->close();

} elseif ($role === 'adviser') {
  $q = $conn->prepare("SELECT `adviser_fname`, `profile_pic` FROM `adviser_details` WHERE `user_id`=?");
  $q->bind_param("i", $userId);
  $q->execute(); $q->bind_result($fname,$pic);
  if ($q->fetch()){ $_SESSION['user_name'] = $fname ?: 'Adviser'; $_SESSION['profile_pic'] = $pic ?: ''; }
  $q->close();

} elseif ($role === 'member') {
  // Get member info + STATUS
  $q = $conn->prepare("SELECT `full_name`, `profile_picture`, `status` FROM `member_details` WHERE `user_id`=? LIMIT 1");
  $q->bind_param("i", $userId);
  $q->execute(); $q->bind_result($full,$pic,$statusRaw);
  $has = $q->fetch();
  $q->close();

  if (!$has) {
    // walang member_details record — huwag i-login
    session_unset();
    session_destroy();
    back('Your membership record was not found. Please contact your adviser.');
  }

  // Normalize status: only 'approved' can login; everything else blocked
  $status = strtolower(trim((string)$statusRaw));
  if ($status !== 'approved') {
    session_unset();
    session_destroy();

    // nicer message depende sa status
    if ($status === 'deactivated' || $status === 'deactivate' || $status === '') {
      back('Your account is deactivated.');
    } elseif ($status === 'pending') {
      back('Your membership is still pending approval.');
    } elseif ($status === 'rejected') {
      back('Your membership application was rejected.');
    } else {
      back('Your account status does not allow login. Please contact your adviser.');
    }
  }

  // ok to login (approved)
  $_SESSION['user_name']   = $full ?: 'Member';
  $_SESSION['profile_pic'] = $pic  ?: '';
}

// success redirect (SweetAlert uses ?success=1&role=)
header('Location: login.php?success=1&role=' . urlencode($role));
exit();
