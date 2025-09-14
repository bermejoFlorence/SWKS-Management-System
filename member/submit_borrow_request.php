<?php
session_start();
include_once '../database/db_connection.php';

// ✅ Ensure user is a logged-in member
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'member') {
    header("Location: inventory.php?request=access_denied");
    exit;
}

$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['org_id'];

$item_id = intval($_POST['item_id']);
$quantity = intval($_POST['quantity']);
$reason = trim($_POST['reason'] ?? '');
$created_at = date('Y-m-d H:i:s');

// ✅ Step 1: Insert into borrow_requests
$stmt1 = $conn->prepare("INSERT INTO borrow_requests (user_id, org_id, purpose, status, created_at) VALUES (?, ?, ?, 'pending', ?)");
$stmt1->bind_param("iiss", $user_id, $org_id, $reason, $created_at);

if ($stmt1->execute()) {
    $request_id = $stmt1->insert_id;

    // ✅ Step 2: Insert into borrow_request_items
    $stmt2 = $conn->prepare("INSERT INTO borrow_request_items (request_id, item_id, quantity_requested) VALUES (?, ?, ?)");
    $stmt2->bind_param("iii", $request_id, $item_id, $quantity);

    if ($stmt2->execute()) {
        // ✅ Step 3: Bawasan ang available quantity
        $updateStmt = $conn->prepare("UPDATE inventory_items SET quantity_available = quantity_available - ? WHERE item_id = ? AND quantity_available >= ?");
        $updateStmt->bind_param("iii", $quantity, $item_id, $quantity);
        $updateStmt->execute();
        $updateStmt->close();

        // ✅ Step 4: Send notification to the adviser of the member’s organization
        $getAdviserSql = "SELECT user_id FROM user WHERE org_id = ? AND user_role = 'adviser' LIMIT 1";
        $stmt3 = $conn->prepare($getAdviserSql);
        $stmt3->bind_param("i", $org_id);
        $stmt3->execute();
        $res = $stmt3->get_result();
        if ($adviser = $res->fetch_assoc()) {
            $adviser_user_id = $adviser['user_id'];

            $type = 'borrow_request';
           // Get member's name and org name
            $memberQuery = "
            SELECT md.full_name, o.org_name
            FROM member_details md
            JOIN user u ON md.user_id = u.user_id
            JOIN organization o ON u.org_id = o.org_id
            WHERE md.user_id = ?
            ";
            $memberStmt = $conn->prepare($memberQuery);
            $memberStmt->bind_param("i", $user_id);
            $memberStmt->execute();
            $memberResult = $memberStmt->get_result();
            $memberData = $memberResult->fetch_assoc();

            $memberName = $memberData['full_name'];
            $memberOrg = $memberData['org_name'];
            $message = "$memberName ($memberOrg Member)  has submitted a borrow request.";
            $memberStmt->close();

            $notifSql = "INSERT INTO notification (user_id, type, message, org_id, is_seen, created_at)
                         VALUES (?, ?, ?, ?, 0, NOW())";
            $stmt4 = $conn->prepare($notifSql);
            $stmt4->bind_param("issi", $adviser_user_id, $type, $message, $org_id);
            $stmt4->execute();
            $stmt4->close();
        }
        $stmt3->close();

        // ✅ Redirect with success
        header("Location: inventory.php?request=success");
        exit;
    } else {
        header("Location: inventory.php?request=fail_item");
        exit;
    }
} else {
    header("Location: inventory.php?request=fail_main");
    exit;
}
?>
