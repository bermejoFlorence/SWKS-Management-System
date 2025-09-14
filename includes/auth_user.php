<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // Hindi naka-login, block access
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
?>