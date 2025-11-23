<?php
// adviser/validate_borrow_request.php
session_start();
include_once '../database/db_connection.php';
include_once 'includes/auth_adviser.php'; // ensures adviser is logged in
header('Content-Type: application/json');

try {
    // ---------- Inputs ----------
    $request_id = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
    $item_id    = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $action     = isset($_POST['action']) ? trim($_POST['action']) : '';

    if (!$request_id || !$item_id || !in_array($action, ['validate','reject'], true)) {
        echo json_encode(['success'=>false,'message'=>'Invalid parameters.']); exit;
    }

    $adviser_org_id = (int)($_SESSION['org_id'] ?? 0);
    if (!$adviser_org_id) {
        echo json_encode(['success'=>false,'message'=>'No organization in session.']); exit;
    }

    // ---------- Load context (must belong to adviser org, and status must be pending) ----------
    $stmt = $conn->prepare("
        SELECT br.request_id, br.org_id, br.user_id AS member_user_id, br.status, br.created_at,
               bri.item_id, bri.quantity_requested,
               ii.name AS item_name,
               COALESCE(md.full_name, u.user_email) AS member_name,
               u.user_email AS member_email
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
        echo json_encode(['success'=>false,'message'=>'Request not found.']); exit;
    }
    if ((int)$ctx['org_id'] !== $adviser_org_id) {
        echo json_encode(['success'=>false,'message'=>'Forbidden (different organization).']); exit;
    }
    if ($ctx['status'] !== 'pending') {
        echo json_encode(['success'=>false,'message'=>'This request is not pending.']); exit;
    }

    // ---------- Fetch org name (for nicer message text) ----------
    $orgName = 'Organization';
    if ($orgStmt = $conn->prepare("SELECT org_name FROM organization WHERE org_id=?")) {
        $orgStmt->bind_param("i", $adviser_org_id);
        $orgStmt->execute();
        $orgStmt->bind_result($org_name_row);
        if ($orgStmt->fetch() && $org_name_row) $orgName = $org_name_row;
        $orgStmt->close();
    }

    // ---------- Summary (item_count + total_qty) for messages ----------
    $itemCount = 0; $totalQty = 0;
    if ($sum = $conn->prepare("
        SELECT COUNT(*) AS c, COALESCE(SUM(quantity_requested),0) AS q
        FROM borrow_request_items
        WHERE request_id = ?
    ")) {
        $sum->bind_param("i", $request_id);
        $sum->execute();
        $sum->bind_result($c,$q);
        if ($sum->fetch()) { $itemCount = (int)$c; $totalQty = (int)$q; }
        $sum->close();
    }

    $memberId   = (int)$ctx['member_user_id'];
    $memberName = $ctx['member_name'] ?: 'Member';

    // ---------- VALIDATE: status only; notify admins + member ----------
    if ($action === 'validate') {
        $u = $conn->prepare("UPDATE borrow_requests SET status='validated', validated_at=NOW() WHERE request_id=? AND status='pending'");
        $u->bind_param("i", $request_id);
        $u->execute();
        $ok = $u->affected_rows > 0;
        $u->close();

        if (!$ok) { echo json_encode(['success'=>false,'message'=>'Nothing updated.']); exit; }

        // Build messages
        $msgAdmins  = sprintf("[%s] Adviser validated request #%d (%d item%s, total qty %d) from %s — forwarded to admin.",
                        $orgName, $request_id, $itemCount, $itemCount===1?'':'s', $totalQty, $memberName);
        $msgMember  = sprintf("[%s] Your borrow request #%d (%d item%s, total qty %d) was validated by the adviser and forwarded to admin.",
                        $orgName, $request_id, $itemCount, $itemCount===1?'':'s', $totalQty);

        // Notify ALL admins
        $admins = [];
        if ($adm = $conn->prepare("SELECT user_id FROM user WHERE user_role='admin'")) {
            $adm->execute();
            $res = $adm->get_result();
            while ($row = $res->fetch_assoc()) $admins[] = (int)$row['user_id'];
            $adm->close();
        }
        if (!empty($admins)) {
            $ins = $conn->prepare("
                INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
                VALUES (?, 'borrow_validated', ?, 0, NOW(), ?)
            ");
            foreach ($admins as $aid) {
                $ins->bind_param("isi", $aid, $msgAdmins, $adviser_org_id);
                $ins->execute();
            }
            $ins->close();
        }

        // Notify member
        if ($memberId > 0) {
            $ins2 = $conn->prepare("
                INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
                VALUES (?, 'borrow_validated_member', ?, 0, NOW(), ?)
            ");
            $ins2->bind_param("isi", $memberId, $msgMember, $adviser_org_id);
            $ins2->execute();
            $ins2->close();
        }

        echo json_encode(['success'=>true,'message'=>'Request forwarded to admin.']);
        exit;
    }

    // ---------- REJECT: restore stock for ALL items of this request; set status=rejected; notify member ----------
    // (Reason: stock was already deducted at request submit; rejecting cancels that reservation.)
    $conn->begin_transaction();

    // Pull all items of this request
    $rows = [];
    $q = $conn->prepare("SELECT item_id, quantity_requested FROM borrow_request_items WHERE request_id=?");
    $q->bind_param("i", $request_id);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) $rows[] = $row;
    $q->close();

    // Restore stock per item (with row locks)
    $updInv = $conn->prepare("UPDATE inventory_items SET quantity_available = quantity_available + ? WHERE item_id=?");
    foreach ($rows as $r) {
        $iid = (int)$r['item_id'];
        $qty = (int)$r['quantity_requested'];
        if ($qty <= 0) continue;

        // lock
        $lock = $conn->prepare("SELECT quantity_available FROM inventory_items WHERE item_id=? FOR UPDATE");
        $lock->bind_param("i", $iid);
        $lock->execute();
        $lock->close();

        $updInv->bind_param("ii", $qty, $iid);
        $updInv->execute();
    }
    $updInv->close();

    // Mark request as rejected (only from pending)
    $u2 = $conn->prepare("UPDATE borrow_requests SET status='rejected' WHERE request_id=? AND status='pending'");
    $u2->bind_param("i", $request_id);
    $u2->execute();
    $ok = $u2->affected_rows > 0;
    $u2->close();

    if (!$ok) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>'Could not set status to rejected.']); exit;
    }

    $conn->commit();

    // Notify member
    $msgReject = sprintf("[%s] Your borrow request #%d (%d item%s, total qty %d) was rejected by the adviser.",
                    $orgName, $request_id, $itemCount, $itemCount===1?'':'s', $totalQty);
    if ($memberId > 0) {
        $ins3 = $conn->prepare("
            INSERT INTO notification (user_id, type, message, is_seen, created_at, org_id)
            VALUES (?, 'borrow_rejected', ?, 0, NOW(), ?)
        ");
        $ins3->bind_param("isi", $memberId, $msgReject, $adviser_org_id);
        $ins3->execute();
        $ins3->close();
    }

    echo json_encode(['success'=>true,'message'=>'Request rejected. Stock restored.']);

} catch (Throwable $e) {
    // Rollback if there’s an active transaction
    if ($conn->errno === 0) {
        // no-op
    } else {
        @mysqli_rollback($conn);
    }
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
