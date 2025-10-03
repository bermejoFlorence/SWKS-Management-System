<?php
// admin/forum_post_admin_action.php
include_once __DIR__ . '/includes/auth_admin.php';
include_once __DIR__ . '/../database/db_connection.php';

header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// === CONFIG ==================================================================
const SWKS_ORG_ID = 11;   // <-- set this to your real SWKS org_id

// === INPUTS ==================================================================
$action   = strtolower(trim($_POST['action'] ?? ''));
$postId   = (int)($_POST['post_id'] ?? 0);
$editorId = (int)($_SESSION['user_id'] ?? 0);

if (!$editorId) { echo json_encode(['ok'=>false,'msg'=>'Not authenticated']); exit; }
if (!$postId || !in_array($action, ['update','delete'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'Bad request']); exit;
}

// === HELPERS =================================================================
/**
 * Notify rule:
 *  - If post.org_id IS NULL or == SWKS_ORG_ID → broadcast to ALL users (exclude editor),
 *    save notification.org_id = p.org_id (NULL/11).
 *  - Else (org-specific) → notify only users with same org_id (exclude editor),
 *    save notification.org_id = that org_id.
 *
 * $type e.g. 'forum_post_edited', 'forum_post_deleted'
 * $messagePlain is already final text (no HTML)
 */
function broadcast_post_notification(
  mysqli $conn,
  int $postId,
  int $editorId,
  string $type,
  string $messagePlain
): bool {

  // Decide general/org-specific *from the DB* (source of truth)
  $q = $conn->prepare("SELECT org_id FROM forum_post WHERE post_id=? LIMIT 1");
  $q->bind_param('i', $postId);
  $q->execute();
  $q->bind_result($pOrgId);
  $has = $q->fetch();
  $q->close();

  if (!$has) return false;

  $isGeneral = (is_null($pOrgId) || (int)$pOrgId === SWKS_ORG_ID);

  if ($isGeneral) {
    // GENERAL (SWKS/NULL): notify everyone; keep p.org_id (NULL or 11) in the notif row
    $sql = "
      INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
      SELECT u.user_id, p.post_id, ?, ?, 0, NOW(), p.org_id
      FROM user u
      JOIN forum_post p ON p.post_id = ?
      WHERE u.user_id <> ?
    ";
    if ($st = $conn->prepare($sql)) {
      $st->bind_param('ssii', $type, $messagePlain, $postId, $editorId); // s s i i
      $ok = $st->execute();
      $st->close();
      return (bool)$ok;
    }
    return false;
  }

  // ORG-SPECIFIC: notify same-org users only; keep that org_id
  $sql = "
    INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
    SELECT u.user_id, p.post_id, ?, ?, 0, NOW(), p.org_id
    FROM user u
    JOIN forum_post p ON p.post_id = ?
    WHERE u.org_id = p.org_id
      AND u.user_id <> ?
  ";
  if ($st = $conn->prepare($sql)) {
    $st->bind_param('ssii', $type, $messagePlain, $postId, $editorId); // s s i i
    $ok = $st->execute();
    $st->close();
    return (bool)$ok;
  }
  return false;
}

/** Fetch post + org name for message composition */
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

// === LOAD POST ===============================================================
$post = load_post($conn, $postId);
if (!$post) { echo json_encode(['ok'=>false,'msg'=>'Post not found']); exit; }

$orgId     = $post['org_id'];                  // may be NULL
$orgName   = $post['org_name'] ?: 'SWKS';
$origTitle = trim((string)($post['title'] ?? ''));
$titleForMsg = $origTitle !== '' ? $origTitle : 'Untitled';

// === ACTIONS =================================================================
if ($action === 'update') {
  $newTitle   = trim((string)($_POST['title'] ?? ''));
  $newContent = trim((string)($_POST['content'] ?? ''));

  if ($newTitle === '' && $newContent === '') {
    echo json_encode(['ok'=>false,'msg'=>'Nothing to update']); exit;
  }

  $sql = "UPDATE forum_post SET title=?, content=? WHERE post_id=?";
  $st  = $conn->prepare($sql);
  $st->bind_param('ssi', $newTitle, $newContent, $postId);
  $ok  = $st->execute();
  $st->close();

  if (!$ok) {
    echo json_encode(['ok'=>false,'msg'=>'Update failed']); exit;
  }

  // Compose message
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);
  if ($isGeneral) {
    // Show SWKS tag if the post sits in SWKS (org_id 11); if NULL, no tag.
    $prefix = is_null($orgId) ? '' : "[{$orgName}] ";
    $msg = "{$prefix}An admin edited a post: {$titleForMsg}";
  } else {
    $msg = "An admin edited a post in {$orgName}: {$titleForMsg}";
  }

  broadcast_post_notification($conn, $postId, $editorId, 'forum_post_edited', $msg);

  echo json_encode(['ok'=>true, 'msg'=>'Updated']); exit;
}

if ($action === 'delete') {
  // (Optional) you might also delete comments/attachments here
  $st = $conn->prepare("DELETE FROM forum_post WHERE post_id=?");
  $st->bind_param('i', $postId);
  $ok = $st->execute();
  $st->close();

  if (!$ok) {
    echo json_encode(['ok'=>false,'msg'=>'Delete failed']); exit;
  }

  // Compose message after deletion
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);
  if ($isGeneral) {
    $prefix = is_null($orgId) ? '' : "[{$orgName}] ";
    $msg = "{$prefix}An admin deleted a post: {$titleForMsg}";
  } else {
    $msg = "An admin deleted a post in {$orgName}: {$titleForMsg}";
  }

  broadcast_post_notification($conn, $postId, $editorId, 'forum_post_deleted', $msg);

  echo json_encode(['ok'=>true, 'msg'=>'Deleted']); exit;
}

// fallback
echo json_encode(['ok'=>false,'msg'=>'Unsupported action']);
