<?php
// admin/forum_post_admin_action.php
include_once __DIR__ . '/includes/auth_admin.php';
include_once __DIR__ . '/../database/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ensure nothing else was already buffered (warnings/whitespace)
while (ob_get_level() > 0) { ob_end_clean(); }
ini_set('display_errors', '0');   // log server-side; don't print into JSON

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
  // we already loaded $post (title/org) above
  $orgId       = $post['org_id'];                 // may be NULL
  $orgName     = $post['org_name'] ?: 'SWKS';
  $origTitle   = trim((string)($post['title'] ?? ''));
  $titleForMsg = $origTitle !== '' ? $origTitle : 'Untitled';

  /* 1) Attempt delete */
  $st = $conn->prepare("DELETE FROM forum_post WHERE post_id = ?");
  $st->bind_param('i', $postId);
  $st->execute();
  $st->close();

  /* 2) Verify if the row still exists (treat existence as failure) */
  $check = $conn->prepare("SELECT 1 FROM forum_post WHERE post_id = ? LIMIT 1");
  $check->bind_param('i', $postId);
  $check->execute();
  $check->store_result();
  $exists = $check->num_rows > 0;
  $check->close();

  if ($exists) {
    echo json_encode(['ok' => false, 'msg' => 'Delete failed']); 
    exit;
  }

  /* 3) Compose notification message (using data we loaded BEFORE delete) */
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);
  $msg = $isGeneral
    ? (is_null($orgId) ? "An admin deleted a post: {$titleForMsg}"
                       : "[{$orgName}] An admin deleted a post: {$titleForMsg}")
    : "An admin deleted a post in {$orgName}: {$titleForMsg}";

  /* 4) Send notifications without touching the deleted row */
  if ($isGeneral) {
    $sql = "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
            SELECT u.user_id, ?, 'forum_post_deleted', ?, 0, NOW(), ?
            FROM user u
            WHERE u.user_id <> ?";
    $stN = $conn->prepare($sql);
    $orgForNotif = $orgId; // may be NULL or SWKS_ORG_ID
    $stN->bind_param('isii', $postId, $msg, $orgForNotif, $editorId);
    $stN->execute();
    $stN->close();
  } else {
    $sql = "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
            SELECT u.user_id, ?, 'forum_post_deleted', ?, 0, NOW(), ?
            FROM user u
            WHERE u.org_id = ? AND u.user_id <> ?";
    $stN = $conn->prepare($sql);
    $stN->bind_param('isiii', $postId, $msg, $orgId, $orgId, $editorId);
    $stN->execute();
    $stN->close();
  }

  echo json_encode(['ok' => true, 'msg' => 'Deleted']);
  exit;
}


echo json_encode(['ok'=>false,'msg'=>'Unsupported action']);
