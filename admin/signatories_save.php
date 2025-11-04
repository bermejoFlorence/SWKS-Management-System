<?php
// admin/admin_request_action.php
include_once 'includes/auth_admin.php';
include_once '../database/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

// Make mysqli throw exceptions (so we can catch and respond with JSON)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

try {
    $action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
    $rid    = (int)($_POST['request_id'] ?? 0);

    if (!$rid || !in_array($action, ['approve','reject','return'], true)) {
        throw new Exception('Bad request', 400);
    }

    // Expected state per action
    $expectFrom = ['approve' => 'validated', 'reject' => 'validated', 'return' => 'approved'];
    $toStatus   = ['approve' => 'approved',  'reject'  => 'rejected',  'return' => 'returned'];

    $from = $expectFrom[$action];
    $to   = $toStatus[$action];

    $conn->begin_transaction();

    // 1) Lock the request row
    $stmt = $conn->prepare("SELECT status FROM borrow_requests WHERE request_id = ? FOR UPDATE");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $stmt->bind_result($curStatus);
    if (!$stmt->fetch()) {
        throw new Exception('Request not found', 404);
    }
    $stmt->close();

    if ($curStatus !== $from) {
        throw new Exception("Invalid state: expected '{$from}', got '{$curStatus}'", 409);
    }

    // 2) Load all items in the request
    $items = [];
    $stmt = $conn->prepare("
        SELECT bri.item_id, ii.name, bri.quantity_requested
        FROM borrow_request_items bri
        JOIN inventory_items ii ON ii.item_id = bri.item_id
        WHERE bri.request_id = ?
    ");
    $stmt->bind_param('i', $rid);
    $stmt->execute();
    $stmt->bind_result($itemId, $itemName, $qtyReq);
    while ($stmt->fetch()) {
        $items[] = ['item_id' => (int)$itemId, 'name' => (string)$itemName, 'qty' => (int)$qtyReq];
    }
    $stmt->close();

    if (!$items) {
        throw new Exception('No items found for this request', 404);
    }

    if ($action === 'approve') {
        // 3A) APPROVE: check stock per item (lock rows) then deduct
        $check = $conn->prepare("SELECT quantity_available FROM inventory_items WHERE item_id = ? FOR UPDATE");
        $lack  = [];
        foreach ($items as $it) {
            $check->bind_param('i', $it['item_id']);
            $check->execute();
            $check->bind_result($avail);
            if (!$check->fetch()) {
                $lack[] = "{$it['name']} (not found)";
            } elseif ((int)$avail < $it['qty']) {
                $lack[] = "{$it['name']} (need {$it['qty']}, have {$avail})";
            }
            $check->free_result();
        }
        $check->close();

        if ($lack) {
            $conn->rollback();
            echo json_encode([
                'ok'  => false,
                'msg' => "Insufficient stock for: " . implode('; ', $lack)
            ]);
            exit;
        }

        $upd = $conn->prepare("UPDATE inventory_items SET quantity_available = quantity_available - ? WHERE item_id = ?");
        foreach ($items as $it) {
            $upd->bind_param('ii', $it['qty'], $it['item_id']);
            $upd->execute();
        }
        $upd->close();

        // Update request status (use approved_at if available in schema)
        $stmt = $conn->prepare("UPDATE borrow_requests SET status='approved', approved_at = NOW() WHERE request_id = ?");
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $stmt->close();

    } elseif ($action === 'return') {
        // 3B) RETURN: add back stock
        $upd = $conn->prepare("UPDATE inventory_items SET quantity_available = quantity_available + ? WHERE item_id = ?");
        foreach ($items as $it) {
            $upd->bind_param('ii', $it['qty'], $it['item_id']);
            $upd->execute();
        }
        $upd->close();

        // Update request status (no returned_at column in your ERD, so status lang)
        $stmt = $conn->prepare("UPDATE borrow_requests SET status='returned' WHERE request_id = ?");
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $stmt->close();

    } else { // reject
        // 3C) REJECT: no stock movement
        $stmt = $conn->prepare("UPDATE borrow_requests SET status='rejected' WHERE request_id = ? AND status='validated'");
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['ok' => true, 'new_status' => $to]);
} catch (Throwable $e) {
    // Best-effort rollback
    try { if ($conn->errno === 0) { $conn->rollback(); } } catch (Throwable $ignore) {}
    $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
