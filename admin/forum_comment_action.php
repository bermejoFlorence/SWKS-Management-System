<?php
session_start();
include_once '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'], $_POST['comment_text'])) {
    $user_id = $_SESSION['user_id'] ?? null;
    $post_id = intval($_POST['post_id']);
    $text = trim($_POST['comment_text']);

    if ($user_id && $text !== "") {
        // Insert the comment
        $stmt = $conn->prepare(
            "INSERT INTO forum_comment (post_id, user_id, comment_text, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->bind_param("iis", $post_id, $user_id, $text);
        $stmt->execute();
        $comment_id = $stmt->insert_id;
        $stmt->close();

        // Get post owner and org_id
        $getOwner = $conn->prepare("SELECT user_id, org_id FROM forum_post WHERE post_id = ?");
        $getOwner->bind_param("i", $post_id);
        $getOwner->execute();
        $getOwner->bind_result($post_owner_id, $org_id);
        $getOwner->fetch();
        $getOwner->close();

        // Get commenter details: name, user_role, org_name
        $commenter_name = '';
        $commenter_role = '';
        $org_name = '';
        $getDetails = $conn->prepare(
            "SELECT u.user_role, u.org_id, m.full_name, a.adviser_fname, c.coor_name, o.org_name
             FROM user u
             LEFT JOIN member_details m ON m.user_id = u.user_id
             LEFT JOIN adviser_details a ON a.user_id = u.user_id
             LEFT JOIN aca_coordinator_details c ON c.user_id = u.user_id
             LEFT JOIN organization o ON o.org_id = u.org_id
             WHERE u.user_id = ?"
        );
        $getDetails->bind_param("i", $user_id);
        $getDetails->execute();
        $getDetails->bind_result($commenter_role, $commenter_org_id, $member_name, $adviser_name, $coor_name, $org_name);
        if ($getDetails->fetch()) {
            if ($commenter_role === 'admin') {
                $commenter_name = $coor_name . " (Aca Coordinator)";
            } elseif ($commenter_role === 'adviser') {
                $commenter_name = $adviser_name . " (" . $org_name . " Adviser)";
            } elseif ($commenter_role === 'member') {
                $commenter_name = $member_name . " (" . $org_name . " Member)";
            } else {
                $commenter_name = "Unknown User";
            }
        }
        $getDetails->close();

        // Get user_role ng post owner (admin/adviser/member)
        $getRole = $conn->prepare("SELECT user_role FROM user WHERE user_id = ?");
        $getRole->bind_param("i", $post_owner_id);
        $getRole->execute();
        $getRole->bind_result($owner_role);
        $getRole->fetch();
        $getRole->close();

        if ($owner_role === 'admin') {
            // Notify ALL users, including the commenter/admin
            $getUsers = $conn->prepare("SELECT user_id FROM user");
            $getUsers->execute();
            $userResult = $getUsers->get_result();

            $notif_type = "forum_comment";
            $notif_message = "<b>$commenter_name</b> commented on post.";
            while ($userRow = $userResult->fetch_assoc()) {
                $notify_user_id = $userRow['user_id'];
                $insertNotif = $conn->prepare(
                    "INSERT INTO notification (user_id, post_id, comment_id, org_id, type, message, is_seen, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 0, NOW())"
                );
                $insertNotif->bind_param("iiiiss", $notify_user_id, $post_id, $comment_id, $org_id, $notif_type, $notif_message);
                $insertNotif->execute();
                $insertNotif->close();
            }
            $getUsers->close();
        } else if ($post_owner_id != $user_id) {
            // Default: notify post owner lang (not self)
            $notif_message = "<b>$commenter_name</b> commented on your post.";
            $notif_type = "forum_comment";
            $insertNotif = $conn->prepare(
                "INSERT INTO notification (user_id, post_id, comment_id, org_id, type, message, is_seen, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW())"
            );
            $insertNotif->bind_param("iiiiss", $post_owner_id, $post_id, $comment_id, $org_id, $notif_type, $notif_message);
            $insertNotif->execute();
            $insertNotif->close();
        }
    }

    // Redirect back to the correct page
    if (isset($_POST['from_view']) && $_POST['from_view']) {
        header("Location: view_forum_post.php?post_id=$post_id");
    } else {
        header("Location: forum.php#post-$post_id");
    }
    exit();
} else {
    header("Location: forum.php");
    exit();
}
?>
