<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include_once '../database/db_connection.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'method_not_allowed';
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    http_response_code(401);
    echo 'not_logged_in';
    exit;
}

$title   = trim($_POST['title'] ?? '');
$content = trim($_POST['post_content'] ?? '');

// Basic validation
if ($title === '' && $content === '') {
    http_response_code(400);
    echo 'empty_post';
    exit;
}

// 1) Resolve member's organization (ignore ?organization for members)
$org_id = 0;
$role = '';
if ($stmt = $conn->prepare("SELECT user_role FROM user WHERE user_id = ? LIMIT 1")) {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();
}
$role = strtolower($role);

// If member, get preferred_org (approved). If adviser/admin, allow optional override via GET but still verify org exists.
if ($role === 'member') {
    if ($stmt = $conn->prepare("SELECT preferred_org FROM member_details WHERE user_id = ? AND status = 'approved' LIMIT 1")) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->bind_result($org_id);
        $stmt->fetch();
        $stmt->close();
    }
    if (!$org_id) {
        http_response_code(403);
        echo 'no_org';
        exit;
    }
} else {
    // adviser/admin: accept ?organization but verify it exists; fallback to their own org if any
    $orgParam = $_GET['organization'] ?? '';
    if ($orgParam !== '' && ctype_digit($orgParam)) {
        $org_id = (int)$orgParam;
    } else {
        // fallback to user's org (if they have one)
        if ($stmt = $conn->prepare("SELECT org_id FROM user WHERE user_id = ? LIMIT 1")) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->bind_result($org_id);
            $stmt->fetch();
            $stmt->close();
        }
    }
    if (!$org_id) {
        http_response_code(400);
        echo 'invalid_org';
        exit;
    }
}

// Verify org exists (avoid FK errors)
if ($stmt = $conn->prepare("SELECT COUNT(*) FROM organization WHERE org_id = ?")) {
    $stmt->bind_param('i', $org_id);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    if (!$cnt) {
        http_response_code(400);
        echo 'org_not_found';
        exit;
    }
}

// 2) Handle attachments (optional)
$attachment_paths = [];
if (!empty($_FILES['attachments']) && !empty($_FILES['attachments']['name']) && $_FILES['attachments']['name'][0] !== "") {
    $files = $_FILES['attachments'];

    $uploadsDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadsDir)) {
        if (!mkdir($uploadsDir, 0777, true)) {
            http_response_code(500);
            echo 'mkdir_failed';
            exit;
        }
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $tmp  = $files['tmp_name'][$i];
            $name = basename($files['name'][$i]);
            $ext  = pathinfo($name, PATHINFO_EXTENSION);
            $new  = uniqid('attach_', true) . ($ext ? "." . strtolower($ext) : "");
            $dest = $uploadsDir . '/' . $new;

            if (move_uploaded_file($tmp, $dest)) {
                // Save relative path only
                $attachment_paths[] = 'uploads/' . $new;
            }
        }
    }
}
$attachment_json = $attachment_paths ? json_encode($attachment_paths) : '';

// 3) Insert post + notifications inside a transaction
try {
    $conn->begin_transaction();

    // Insert forum post
    $stmt = $conn->prepare("
        INSERT INTO forum_post (org_id, user_id, title, content, attachment, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new Exception('prep_post: ' . $conn->error);
    }
    $stmt->bind_param('iisss', $org_id, $user_id, $title, $content, $attachment_json);
    if (!$stmt->execute()) {
        throw new Exception('exec_post: ' . $stmt->error);
    }
    $post_id = $stmt->insert_id;
    $stmt->close();

    // Build notification recipients:
    // - Always the org's advisers and members
    // - Also admin users (global)
    // - Exclude the actor (user_id)
    $recipients = [];

    // org advisers & members
    $stmt = $conn->prepare("SELECT user_id FROM user WHERE org_id = ? AND user_role IN ('adviser','member')");
    if ($stmt) {
        $stmt->bind_param('i', $org_id);
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            if ((int)$row['user_id'] !== (int)$user_id) {
                $recipients[(int)$row['user_id']] = ['org_id' => $org_id];
            }
        }
        $stmt->close();
    }

    // admins (no org filter)
    $stmt = $conn->prepare("SELECT user_id, org_id FROM user WHERE user_role = 'admin'");
    if ($stmt) {
        $stmt->execute();
        $rs = $stmt->get_result();
        while ($row = $rs->fetch_assoc()) {
            if ((int)$row['user_id'] !== (int)$user_id) {
                $recipients[(int)$row['user_id']] = ['org_id' => (int)$row['org_id']];
            }
        }
        $stmt->close();
    }

    // Insert notifications (forum_post)
    if (!empty($recipients)) {
        $notifType = 'forum_post';
        $notifMsg  = $title !== '' ? "New forum post: " . $title : "A new post was added in your organization forum.";

        $stmt = $conn->prepare("
            INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
            VALUES (?, ?, ?, ?, 0, NOW(), ?)
        ");
        if (!$stmt) {
            throw new Exception('prep_notif: ' . $conn->error);
        }
        foreach ($recipients as $uid => $meta) {
            $oid = (int)$meta['org_id'];
            $stmt->bind_param('iissi', $uid, $post_id, $notifType, $notifMsg, $oid);
            if (!$stmt->execute()) {
                throw new Exception('exec_notif: ' . $stmt->error);
            }
        }
        $stmt->close();
    }

    $conn->commit();
    echo 'success';
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("forum_post_action error: " . $e->getMessage());
    http_response_code(400);
    echo 'error';
    exit;
}
