<?php
session_start();
include_once '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['post_content'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $org_id = $_GET['organization'] ?? 'SWKS';
    $user_id = $_SESSION['user_id'];

    if ($org_id === 'SWKS' || $org_id === '') $org_id = 11;

    // Handle attachments
    $attachment_paths = [];
    if (isset($_FILES['attachments']) && isset($_FILES['attachments']['name']) && $_FILES['attachments']['name'][0] !== "") {
        $files = $_FILES['attachments'];

        // Use shared uploads directory outside any user-type folder
        $targetDir = realpath(__DIR__ . '/../uploads');
        if (!$targetDir) {
            // If uploads/ does not exist, create it
            mkdir(__DIR__ . '/../uploads', 0777, true);
            $targetDir = realpath(__DIR__ . '/../uploads');
        }
        $targetDir .= '/';

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmp = $files['tmp_name'][$i];
                $fileName = basename($files['name'][$i]);
                $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid("attach_", true) . "." . $fileExt;
                $targetFilePath = $targetDir . $newFileName;

                if (move_uploaded_file($fileTmp, $targetFilePath)) {
                    // Save relative path only (for use in <img src> or <a href>)
                    $relativePath = 'uploads/' . $newFileName;
                    $attachment_paths[] = $relativePath;
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

        // Step 2: Select users to notify
        if ($posterRole === 'admin') {
            // Notify ALL users in the system (including admin)
            $getUsers = $conn->prepare(
                "SELECT user_id, org_id FROM user"
            );
            $getUsers->execute();
            $result = $getUsers->get_result();
        } else {
            // Notify only advisers and members in the same org
            $getUsers = $conn->prepare(
                "SELECT user_id, org_id FROM user WHERE org_id = ? AND user_role IN ('adviser', 'member')"
            );
            $getUsers->bind_param("i", $org_id);
            $getUsers->execute();
            $result = $getUsers->get_result();
        }

        // Step 3: Insert notification for each user
        $notifStmt = $conn->prepare(
            "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
             VALUES (?, ?, ?, ?, 0, NOW(), ?)"
        );
        $notifType = 'forum_post';
        $notifMsg = !empty($title)
            ? "New forum post: " . $title
            : "A new post was added in your organization forum.";

        while ($row = $result->fetch_assoc()) {
            $targetUserId = $row['user_id'];
            $targetOrgId = $row['org_id'];
            $notifStmt->bind_param("iissi", $targetUserId, $post_id, $notifType, $notifMsg, $targetOrgId);
            $notifStmt->execute();
        }

        $notifStmt->close();
        $getUsers->close();
        // --- END: Insert Notifications ---

        echo 'success';
    } else {
        http_response_code(400);
        echo 'error';
    }
    exit();
}
?>
