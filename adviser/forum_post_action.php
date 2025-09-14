<?php
session_start();
include_once '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['post_content'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $user_id = $_SESSION['user_id'];

    // --- FIXED ORG_ID LOGIC: Get adviser's org_id from DB, not from $_GET ---
    $org_id = 0;
    $stmt = $conn->prepare("
        SELECT o.org_id
        FROM adviser_details a
        JOIN user u ON a.user_id = u.user_id
        JOIN organization o ON u.org_id = o.org_id
        WHERE a.user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($org_id);
    $stmt->fetch();
    $stmt->close();
    if (!$org_id) $org_id = 11; // fallback to SWKS if adviser has no org

    // Handle attachments
    $attachment_paths = [];
    if (isset($_FILES['attachments']) && isset($_FILES['attachments']['name']) && $_FILES['attachments']['name'][0] !== "") {
        $files = $_FILES['attachments'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmp = $files['tmp_name'][$i];
                $fileName = basename($files['name'][$i]);
                $targetDir = "../uploads/";
                if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

                $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid("attach_", true) . "." . $fileExt;
                $targetFilePath = $targetDir . $newFileName;

                if (move_uploaded_file($fileTmp, $targetFilePath)) {
                    $relativePath = 'uploads/' . $newFileName;
                    $attachment_paths[] = $relativePath; // <-- IMPORTANT!
                }
            }
        }
    }
    $attachment_json = !empty($attachment_paths) ? json_encode($attachment_paths) : '';

    // Insert forum post
    $stmt = $conn->prepare(
        "INSERT INTO forum_post (org_id, user_id, title, content, attachment, created_at) VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("iisss", $org_id, $user_id, $title, $content, $attachment_json);

    if ($stmt->execute()) {
        $post_id = $stmt->insert_id;

        // --- START: Insert Notifications ---
        // Step 1: Get role of the poster (admin/adviser/member)
        $posterRole = '';
        $roleStmt = $conn->prepare("SELECT user_role FROM user WHERE user_id = ?");
        $roleStmt->bind_param("i", $user_id);
        $roleStmt->execute();
        $roleStmt->bind_result($posterRole);
        $roleStmt->fetch();
        $roleStmt->close();

        // --- START: Insert Notifications ---
        // 1. Notify adviser (poster)
        $recipient_ids = [$user_id];

        // 2. Notify all members in the same org (except poster, para walang double)
        $getMembers = $conn->prepare("SELECT user_id FROM user WHERE org_id = ? AND user_role = 'member'");
        $getMembers->bind_param("i", $org_id);
        $getMembers->execute();
        $membersResult = $getMembers->get_result();
        while ($row = $membersResult->fetch_assoc()) {
            if (!in_array($row['user_id'], $recipient_ids)) {
                $recipient_ids[] = $row['user_id'];
            }
        }
        $getMembers->close();

        // 3. Notify all admins (ACA Coordinator)
        $getAdmins = $conn->prepare("SELECT user_id FROM user WHERE user_role = 'admin'");
        $getAdmins->execute();
        $adminsResult = $getAdmins->get_result();
        while ($row = $adminsResult->fetch_assoc()) {
            if (!in_array($row['user_id'], $recipient_ids)) {
                $recipient_ids[] = $row['user_id'];
            }
        }
        $getAdmins->close();

        // Insert notification for each recipient
        $notifStmt = $conn->prepare(
            "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
             VALUES (?, ?, ?, ?, 0, NOW(), ?)"
        );
        $notifType = 'forum_post';
        $notifMsg = !empty($title)
            ? "New forum post: " . $title
            : "A new post was added in your organization forum.";
        foreach ($recipient_ids as $targetUserId) {
            $notifStmt->bind_param("iissi", $targetUserId, $post_id, $notifType, $notifMsg, $org_id);
            $notifStmt->execute();
        }
        $notifStmt->close();
        // --- END: Insert Notifications ---

        echo 'success';
    } else {
        http_response_code(400);
        echo 'error';
    }
    exit();
}
?>
