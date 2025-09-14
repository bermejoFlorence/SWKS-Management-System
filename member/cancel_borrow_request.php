<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include_once 'includes/auth_member.php';
include_once '../database/db_connection.php';

function back_to_requests($code, $extra = '') {
    $q = "request={$code}";
    if ($extra !== '') $q .= "&current=" . urlencode($extra);
    header("Location: inventory.php?{$q}#requests");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_to_requests('cancel_failed');
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    back_to_requests('access_denied');
}

$request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
if ($request_id <= 0) {
    back_to_requests('cancel_failed');
}

/* 1) Read current status (and verify ownership) */
$stmt = $conn->prepare("SELECT status FROM borrow_requests WHERE request_id = ? AND user_id = ? LIMIT 1");
if (!$stmt) {
    back_to_requests('cancel_failed');
}
$stmt->bind_param('ii', $request_id, $user_id);
$stmt->execute();
$stmt->bind_result($status);
$found = $stmt->fetch();
$stmt->close();

if (!$found) {
    // not yours or not found
    back_to_requests('cancel_forbidden');
}

$norm = trim(strtolower((string)$status));
if ($norm !== 'pending') {
    // Already moved to another state; tell the user which one
    back_to_requests('cancel_forbidden', $status);
}

/* 2) Now perform the cancel (no status condition since we just verified) */
$stmt = $conn->prepare("UPDATE borrow_requests SET status = 'cancelled' WHERE request_id = ? AND user_id = ?");
if (!$stmt) {
    back_to_requests('cancel_failed');
}
$stmt->bind_param('ii', $request_id, $user_id);
$stmt->execute();
$ok = ($stmt->affected_rows === 1);
$stmt->close();

if ($ok) {
    back_to_requests('cancelled');
} else {
    // Very rare: race condition (status changed after SELECT but before UPDATE)
    back_to_requests('cancel_forbidden');
}
