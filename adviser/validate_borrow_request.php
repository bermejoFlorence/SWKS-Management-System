<?php
// adviser/validate_borrow_request.php
session_start();
include_once '../database/db_connection.php';
include_once 'includes/auth_adviser.php'; // ensures adviser is logged in
header('Content-Type: application/json');

try {
    // --- Inputs ---
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

    // --- Context: must belong to same org, and status must be pending ---
    $stmt = $conn->prepare("
        SELECT br.request_id, br.org_id, br.status,
               bri.item_id, bri.quantity_requested,
               ii.name AS item_name,
               u.user_id AS member_user_id,
               COALESCE(md.full_name, u.user_email) AS member_name
        FROM borrow_requests br
        JOIN borrow_request_items bri ON bri.request_id = br.request_id
        JOIN inventory_items ii ON ii.item_id = bri.item_id
        JOIN user u ON u.user_id = br.user_id
        LEFT JOIN member_details md ON md.user_id = br.user_id
        WHERE br.request_id=? AND bri.item_id=?
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

    // --- VALIDATE: status only, no stock change (stock was already deducted at request submit) ---
    if ($action === 'validate') {
        $u = $conn->prepare("UPDATE borrow_requests SET status='validated', validated_at=NOW() WHERE request_id=? AND status='pending'");
        $u->bind_param("i", $request_id);
        $u->execute();
        $ok = $u->affected_rows > 0;
        $u->close();

        if (!$ok) { echo json_encode(['success'=>false,'message'=>'Nothing updated.']); exit; }

        // Notify admins (optional: keep your current notification code)
        // ...

        echo json_encode(['success'=>true,'message'=>'Request forwarded to admin.']);
        exit;
    }

    // --- REJECT: add back the reserved quantity for THIS item, then set status=rejected ---
    $qty = (int)$ctx['quantity_requested'];
    if ($qty <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid quantity to return.']); exit; }

    $conn->begin_transaction();

    // 1) lock the inventory row (avoid race on concurrent updates)
    $lock = $conn->prepare("SELECT quantity_available FROM inventory_items WHERE item_id=? FOR UPDATE");
    $lock->bind_param("i", $item_id);
    $lock->execute();
    $lock->close();

    // 2) add back the quantity that was deducted at request time
    $updInv = $conn->prepare("UPDATE inventory_items SET quantity_available = quantity_available + ? WHERE item_id=?");
    if (!$updInv) { $conn->rollback(); echo json_encode(['success'=>false,'message'=>'Prepare failed (inventory update).']); exit; }
    $updInv->bind_param("ii", $qty, $item_id);
    $updInv->execute();
    $updInv->close();

    // 3) mark request as rejected (still whole request → rejected)
    $updReq = $conn->prepare("UPDATE borrow_requests SET status='rejected' WHERE request_id=? AND status='pending'");
    $updReq->bind_param("i", $request_id);
    $updReq->execute();
    $ok = $updReq->affected_rows > 0;
    $updReq->close();

    if (!$ok) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>'Could not set status to rejected.']); exit;
    }

    $conn->commit();

    // Notify member (optional: keep your current notification code)
    // ...

    echo json_encode(['success'=>true,'message'=>'Request rejected. Stock restored.']);
} catch (Throwable $e) {
    // Important: no HTML output, return JSON only
    echo json_encode(['success'=>false,'message'=>'Server error: '.$e->getMessage()]);
}
