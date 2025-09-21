<?php
session_start();
include_once '../database/db_connection.php';
include_once 'includes/auth_adviser.php'; // ensures adviser is logged in

header('Content-Type: application/json');

try {
    // Basic input validation
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $item_id    = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $action     = isset($_POST['action']) ? trim($_POST['action']) : '';

    if (!$request_id || !$item_id || !in_array($action, ['validate', 'reject'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit;
    }

    // Adviser org from session
    $adviser_org_id = $_SESSION['org_id'] ?? 0;
    if (!$adviser_org_id) {
        echo json_encode(['success' => false, 'message' => 'No organization found in session.']);
        exit;
    }

    // Fetch request context (must belong to same org as adviser)
    $stmt = $conn->prepare("
        SELECT 
            br.request_id,
            br.org_id,
            br.user_id AS member_user_id,
            br.status,
            br.created_at,
            bri.item_id,
            bri.quantity_requested,
            ii.name AS item_name,
            md.full_name AS member_name
        FROM borrow_requests br
        JOIN borrow_request_items bri ON bri.request_id = br.request_id
        JOIN inventory_items ii ON ii.item_id = bri.item_id
        JOIN user u ON u.user_id = br.user_id
        LEFT JOIN member_details md ON md.user_id = br.user_id
        WHERE br.request_id = ? AND bri.item_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $request_id, $item_id);
    $stmt->execute();
    $ctx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$ctx) {
        echo json_encode(['success' => false, 'message' => 'Request not found.']);
        exit;
    }
    if ((int)$ctx['org_id'] !== (int)$adviser_org_id) {
        echo json_encode(['success' => false, 'message' => 'Forbidden. Different organization.']);
        exit;
    }

    // Only allow state change from pending -> validated/rejected
    if ($ctx['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'This request is not pending.']);
        exit;
    }

    // Transition
    if ($action === 'validate') {
        $update = $conn->prepare("UPDATE borrow_requests SET status='validated', validated_at=NOW() WHERE request_id=?");
        $msg_action = 'forwarded to admin';
        $notif_type = 'borrow_validated';
    } else { // reject
        $update = $conn->prepare("UPDATE borrow_requests SET status='rejected' WHERE request_id=?");
        $msg_action = 'rejected by adviser';
        $notif_type = 'borrow_rejected';
    }
    $update->bind_param("i", $request_id);
    $update->execute();
    if ($update->affected_rows < 1) {
        $update->close();
        echo json_encode(['success' => false, 'message' => 'No rows updated. Possibly not pending or invalid id.']);
        exit;
    }
    $update->close();

    // Get org name for nicer message
    $orgName = 'Organization';
    $org = $conn->prepare("SELECT org_name FROM organization WHERE org_id = ?");
    $org->bind_param("i", $adviser_org_id);
    $org->execute();
    $org->bind_result($org_name_row);
    if ($org->fetch() && $org_name_row) $orgName = $org_name_row;
    $org->close();

    // Build messages
    $memberName = $ctx['member_name'] ?: 'Member';
    $validatedMsg = sprintf(
        "[%s] Adviser validated request #%d from %s: %d × %s — forwarded to admin.",
        $orgName,
        (int)$ctx['request_id'],
        $memberName,
        (int)$ctx['quantity_requested'],
        $ctx['item_name']
    );
    $rejectedMsg = sprintf(
        "[%s] Your borrow request #%d for %d × %s was rejected by the adviser.",
        $orgName,
        (int)$ctx['request_id'],
        (int)$ctx['quantity_requested'],
        $ctx['item_name']
    );

    // Notifications (branch per action)
    if ($action === 'validate') {
        // Notify ALL admins (role-based, no org filter)
        $admins = [];
        $adm = $conn->prepare("SELECT user_id FROM user WHERE user_role = 'admin'");
        $adm->execute();
        $res = $adm->get_result();
        while ($row = $res->fetch_assoc()) $admins[] = (int)$row['user_id'];
        $adm->close();

        if (!empty($admins)) {
            $ins = $conn->prepare("
                INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
                VALUES (?, 'borrow_validated', ?, 0, NOW(), ?)
            ");
            foreach ($admins as $admin_id) {
                $ins->bind_param("isi", $admin_id, $validatedMsg, $adviser_org_id);
                $ins->execute();
            }
            $ins->close();
        }
    } else {
        // Reject → notify the requesting member only
        $memberUserId = (int)$ctx['member_user_id'];
        if ($memberUserId > 0) {
            $ins = $conn->prepare("
                INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
                VALUES (?, 'borrow_rejected', ?, 0, NOW(), ?)
            ");
            $ins->bind_param("isi", $memberUserId, $rejectedMsg, $adviser_org_id);
            $ins->execute();
            $ins->close();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $action === 'validate'
            ? 'Request forwarded to admin.'
            : 'Request rejected.'
    ]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: '.$e->getMessage()]);
}
