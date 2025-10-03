<?php
// admin/forum_post_admin_action.php
include_once __DIR__ . '/includes/auth_admin.php';
include_once __DIR__ . '/../database/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

const SWKS_ORG_ID = 11; // adjust if needed

$action   = strtolower(trim($_POST['action'] ?? ''));
$postId   = (int)($_POST['post_id'] ?? 0);
$editorId = (int)($_SESSION['user_id'] ?? 0);

if (!$editorId) { echo json_encode(['ok'=>false,'msg'=>'Not authenticated']); exit; }
if (!$postId || !in_array($action, ['update','delete'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'Bad request']); exit;
}

/** Load post + org name once (we’ll need it even for delete) */
function load_post(mysqli $conn, int $postId): ?array {
  $sql = "
    SELECT p.post_id, p.user_id AS author_id, p.org_id, p.title, p.content,
           o.org_name
    FROM forum_post p
    LEFT JOIN organization o ON o.org_id = p.org_id
    WHERE p.post_id=? LIMIT 1
  ";
  $st = $conn->prepare($sql);
  $st->bind_param('i', $postId);
  $st->execute();
  $res = $st->get_result();
  $row = $res->fetch_assoc();
  $st->close();
  return $row ?: null;
}

/**
 * Notify recipients for an edited/deleted post.
 * We pass $orgId directly (don’t requery forum_post).
 */
function notify_admin_action(
  mysqli $conn,
  int $orgId = null,   // null allowed
  int $postId,
  int $editorId,
  string $type,        // 'forum_post_edited' | 'forum_post_deleted'
  string $message
): bool {
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);

  if ($isGeneral) {
    $sql = "
      INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
      SELECT u.user_id, ?, ?, ?, 0, NOW(), ?
      FROM user u
      WHERE u.user_id <> ?
    ";
    $st = $conn->prepare($sql);
    $st->bind_param('issii', $postId, $type, $message, $orgId, $editorId);
    $ok = $st->execute();
    $st->close();
    return (bool)$ok;
  }

  $sql = "
    INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
    SELECT u.user_id, ?, ?, ?, 0, NOW(), ?
    FROM user u
    WHERE u.org_id = ?
      AND u.user_id <> ?
  ";
  $st = $conn->prepare($sql);
  $st->bind_param('issiiii', $postId, $type, $message, $orgId, $orgId, $editorId);
  $ok = $st->execute();
  $st->close();
  return (bool)$ok;
}

$post = load_post($conn, $postId);
if (!$post) { echo json_encode(['ok'=>false,'msg'=>'Post not found']); exit; }

$orgId       = $post['org_id'];               // may be NULL
$orgName     = $post['org_name'] ?: 'SWKS';
$origTitle   = trim((string)($post['title'] ?? ''));
$titleForMsg = $origTitle !== '' ? $origTitle : 'Untitled';

if ($action === 'update') {
  $newTitle   = trim((string)($_POST['title'] ?? ''));
  $newContent = trim((string)($_POST['content'] ?? ''));

  if ($newTitle === '' && $newContent === '') {
    echo json_encode(['ok'=>false,'msg'=>'Nothing to update']); exit;
  }

  $st = $conn->prepare("UPDATE forum_post SET title=?, content=? WHERE post_id=?");
  $st->bind_param('ssi', $newTitle, $newContent, $postId);
  $ok = $st->execute();
  $st->close();

  if (!$ok) { echo json_encode(['ok'=>false,'msg'=>'Update failed']); exit; }

  // Build message
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);
  $prefix    = $isGeneral ? (is_null($orgId) ? '' : "[{$orgName}] ") : '';
  $msg       = $isGeneral
    ? "{$prefix}An admin edited a post: {$titleForMsg}"
    : "An admin edited a post in {$orgName}: {$titleForMsg}";

  notify_admin_action($conn, $orgId, $postId, $editorId, 'forum_post_edited', $msg);

  echo json_encode(['ok'=>true,'msg'=>'Updated']); exit;
}

if ($action === 'delete') {
  // Compose message BEFORE deleting
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);
  $prefix    = $isGeneral ? (is_null($orgId) ? '' : "[{$orgName}] ") : '';
  $msg       = $isGeneral
    ? "{$prefix}An admin deleted a post: {$titleForMsg}"
    : "An admin deleted a post in {$orgName}: {$titleForMsg}";

  // Delete in a transaction: comments -> notifications -> post
  $conn->begin_transaction();
  try {
    // 1) Remove comments referencing the post
    if ($st = $conn->prepare("DELETE FROM forum_comment WHERE post_id=?")) {
      $st->bind_param('i', $postId);
      $st->execute();
      $st->close();
    }

    // 2) Remove existing notifications for this post (cleanup)
    if ($st = $conn->prepare("DELETE FROM notification WHERE post_id=?")) {
      $st->bind_param('i', $postId);
      $st->execute();
      $st->close();
    }

    // 3) Delete the post itself
    $st = $conn->prepare("DELETE FROM forum_post WHERE post_id=?");
    $st->bind_param('i', $postId);
    $st->execute();
    $affected = $st->affected_rows;
    $st->close();

    if ($affected <= 0) {
      throw new Exception('Delete failed');
    }

    $conn->commit();

    // 4) Send delete notifications AFTER commit (we already have $orgId)
    notify_admin_action($conn, $orgId, $postId, $editorId, 'forum_post_deleted', $msg);

    echo json_encode(['ok'=>true,'msg'=>'Deleted']); exit;

  } catch (Throwable $e) {
    $conn->rollback();
    echo json_encode(['ok'=>false,'msg'=>'Delete failed']); exit;
  }
}

echo json_encode(['ok'=>false,'msg'=>'Unsupported action']);
