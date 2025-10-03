<?php
error_log("hit forum_post_admin_action with method=" . $_SERVER['REQUEST_METHOD']);

// admin/forum_post_admin_action.php
include_once __DIR__ . '/includes/auth_admin.php';
include_once __DIR__ . '/../database/db_connection.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Make sure nothing else leaks into our JSON
while (ob_get_level() > 0) { ob_end_clean(); }
ini_set('display_errors', '0'); // log server-side, don't echo

const SWKS_ORG_ID = 11; // adjust if your SWKS org_id is different

// accept 'mode' (preferred) or 'action' (fallback)
$rawAction = $_POST['mode'] ?? $_POST['action'] ?? '';
$action    = strtolower(trim($rawAction));
if ($action === 'remove') $action = 'delete';
if ($action === 'edit')   $action = 'update';

$postId   = (int)($_POST['post_id'] ?? 0);
$editorId = (int)($_SESSION['user_id'] ?? 0);

if (!$editorId) { echo json_encode(['ok'=>false,'msg'=>'Not authenticated']); exit; }
if (!$postId || !in_array($action, ['update','delete'], true)) {
  echo json_encode(['ok'=>false,'msg'=>'Bad request']); exit;
}

/** Load post + org name once (used by both update and delete) */
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
 * - For 'forum_post_deleted', we deliberately set post_id = NULL in the notification
 *   to avoid FK violations (the post no longer exists).
 * - For edits, we can keep the real post_id.
 */
function notify_admin_action(
  mysqli $conn,
  $orgId,            // NULL | int
  ?int $postIdForNotif, // NULL for deleted; real post_id for edited
  int $editorId,
  string $type,      // 'forum_post_edited' | 'forum_post_deleted'
  string $message
): bool {
  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);

  // Decide how to project post_id into the INSERT SELECT
  $postIdSQL = is_null($postIdForNotif) ? "NULL" : "?"; // bind if not NULL
  $bindPostId = !is_null($postIdForNotif);

  if ($isGeneral) {
    if (is_null($orgId)) {
      // General with NULL org_id, and maybe NULL post_id
      $sql = "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
              SELECT u.user_id, {$postIdSQL}, ?, ?, 0, NOW(), NULL
              FROM user u
              WHERE u.user_id <> ?";
      $st = $conn->prepare($sql);
      if ($bindPostId) {
        $st->bind_param('isii', $postIdForNotif, $type, $message, $editorId);
      } else {
        $st->bind_param('sii', $type, $message, $editorId);
      }
    } else {
      // General with explicit SWKS org_id
      $sql = "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
              SELECT u.user_id, {$postIdSQL}, ?, ?, 0, NOW(), ?
              FROM user u
              WHERE u.user_id <> ?";
      $st = $conn->prepare($sql);
      if ($bindPostId) {
        $st->bind_param('isiii', $postIdForNotif, $type, $message, $orgId, $editorId);
      } else {
        $st->bind_param('siii', $type, $message, $orgId, $editorId);
      }
    }
    $ok = $st->execute();
    $err = $st->error;
    $st->close();
    if (!$ok) error_log("[notify_admin_action] general failed: ".$err);
    return (bool)$ok;
  }

  // Org-specific
  $sql = "INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
          SELECT u.user_id, {$postIdSQL}, ?, ?, 0, NOW(), ?
          FROM user u
          WHERE u.org_id = ? AND u.user_id <> ?";
  $st = $conn->prepare($sql);
  if ($bindPostId) {
    $st->bind_param('isiiii', $postIdForNotif, $type, $message, $orgId, $orgId, $editorId);
  } else {
    $st->bind_param('siiii', $type, $message, $orgId, $orgId, $editorId);
  }
  $ok = $st->execute();
  $err = $st->error;
  $st->close();
  if (!$ok) error_log("[notify_admin_action] org-specific failed: ".$err);
  return (bool)$ok;
}

// ---------- Load post ----------
$post = load_post($conn, $postId);
if (!$post) { echo json_encode(['ok'=>false,'msg'=>'Post not found']); exit; }

$orgId       = $post['org_id'];               // may be NULL
$orgName     = $post['org_name'] ?: 'SWKS';
$origTitle   = trim((string)($post['title'] ?? ''));
$titleForMsg = $origTitle !== '' ? $origTitle : 'Untitled';

// ---------- UPDATE ----------
if ($action === 'update') {
  $newTitle   = trim((string)($_POST['title'] ?? ''));
  $newContent = trim((string)($_POST['content'] ?? ''));

  if ($newTitle === '' && $newContent === '') {
    echo json_encode(['ok'=>false,'msg'=>'Nothing to update']); exit;
  }

  $st = $conn->prepare("UPDATE forum_post SET title=?, content=? WHERE post_id=?");
  $st->bind_param('ssi', $newTitle, $newContent, $postId);
  $ok = $st->execute();
  $err = $st->error;
  $st->close();

  if (!$ok) { echo json_encode(['ok'=>false,'msg'=>'Update failed','sql_error'=>$err]); exit; }

  $isGeneral = (is_null($orgId) || (int)$orgId === SWKS_ORG_ID);
  $prefix    = $isGeneral ? (is_null($orgId) ? '' : "[{$orgName}] ") : '';
  $msg       = $isGeneral
    ? "{$prefix}An admin edited a post: {$titleForMsg}"
    : "An admin edited a post in {$orgName}: {$titleForMsg}";

  // For edit, we keep the real post_id in the notification
  notify_admin_action($conn, $orgId, $postId, $editorId, 'forum_post_edited', $msg);

  echo json_encode(['ok'=>true,'msg'=>'Updated']); exit;
}

// ---------- DELETE ----------
// ---------- DELETE ----------
if ($action === 'delete') {

  // helpers: schema checks (safe vs missing tables/cols)
  $hasTable = function(mysqli $c, string $t): bool {
    $sql = "SELECT 1 FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
    $st = $c->prepare($sql);
    $st->bind_param('s', $t);
    $st->execute();
    $st->store_result();
    $ok = $st->num_rows > 0;
    $st->close();
    return $ok;
  };
  $hasColumn = function(mysqli $c, string $t, string $col): bool {
    $sql = "SELECT 1 FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1";
    $st = $c->prepare($sql);
    $st->bind_param('ss', $t, $col);
    $st->execute();
    $st->store_result();
    $ok = $st->num_rows > 0;
    $st->close();
    return $ok;
  };

  $conn->begin_transaction();
  try {
    // 1) Delete dependents first (present in your schema)
    if ($hasTable($conn, 'forum_comment') && $hasColumn($conn, 'forum_comment', 'post_id')) {
      $st = $conn->prepare("DELETE FROM forum_comment WHERE post_id=?");
      $st->bind_param('i', $postId);
      $st->execute(); $st->close();
    }
    if ($hasTable($conn, 'notification') && $hasColumn($conn, 'notification', 'post_id')) {
      $st = $conn->prepare("DELETE FROM notification WHERE post_id=?");
      $st->bind_param('i', $postId);
      $st->execute(); $st->close();
    }

    // (Optional) attachments table — only if you actually have it
    if ($hasTable($conn, 'forum_attachment') && $hasColumn($conn, 'forum_attachment', 'post_id')) {
      $st = $conn->prepare("DELETE FROM forum_attachment WHERE post_id=?");
      $st->bind_param('i', $postId);
      $st->execute(); $st->close();
    }

    // 2) Delete the post
    $st = $conn->prepare("DELETE FROM forum_post WHERE post_id=?");
    $st->bind_param('i', $postId);
    $st->execute(); $st->close();

    // 3) Verify gone (optional)
    $check = $conn->prepare("SELECT 1 FROM forum_post WHERE post_id=? LIMIT 1");
    $check->bind_param('i', $postId);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0;
    $check->close();

    if ($exists) {
      $conn->rollback();
      echo json_encode(['ok'=>false,'msg'=>'Delete failed (still exists)']); exit;
    }

    // 4) Finish
    $conn->commit();
    echo json_encode(['ok'=>true,'msg'=>'Deleted']); exit;

  } catch (Throwable $e) {
    $conn->rollback();
    error_log("[forum_post_admin_action delete] exception: ".$e->getMessage());
    echo json_encode(['ok'=>false,'msg'=>'Delete failed (exception)']); exit;
  }
}

// Fallback
echo json_encode(['ok'=>false,'msg'=>'Unsupported action']);
