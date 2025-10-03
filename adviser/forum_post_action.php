<?php
// admin/forum_post_action.php
session_start();
include_once '../database/db_connection.php';

// Always return JSON (your JS shows SweetAlerts based on this)
header('Content-Type: application/json');

// 🔧 Change this if your SWKS org_id is different
const SWKS_ORG_ID = 11;

// 1) Basic guards
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
  exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!$user_id) {
  http_response_code(401);
  echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
  exit;
}

$title   = trim($_POST['title'] ?? '');
$content = trim($_POST['post_content'] ?? '');
if ($content === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'Post content is required.']);
  exit;
}

// org_id passed via ?organization=... on the form action
$org_in  = $_GET['organization'] ?? 'SWKS';
$org_id  = ($org_in === 'SWKS' || $org_in === '') ? SWKS_ORG_ID : (int)$org_in;

// 2) Get poster role
$posterRole = '';
if ($stmt = $conn->prepare("SELECT user_role FROM user WHERE user_id=?")) {
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $stmt->bind_result($posterRole);
  $stmt->fetch();
  $stmt->close();
}
if ($posterRole === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'message' => 'User role not found.']);
  exit;
}

// 3) NEW: Block admin posting to a specific org that has no members/advisers yet
if ($posterRole === 'admin' && $org_id !== SWKS_ORG_ID) {
  $audCount = 0;
  if ($chk = $conn->prepare("SELECT COUNT(*) FROM user WHERE org_id=? AND user_id<>?")) {
    $chk->bind_param("ii", $org_id, $user_id);
    $chk->execute();
    $chk->bind_result($audCount);
    $chk->fetch();
    $chk->close();
  }
  if ((int)$audCount === 0) {
    echo json_encode([
      'success' => false,
      'reason'  => 'no_recipients',
      'message' => 'This organization has no members/advisers yet. Please add users before posting.'
    ]);
    exit;
  }
}

// 4) Handle attachments (optional)
$attachment_paths = [];
if (!empty($_FILES['attachments']['name'][0])) {
  $files = $_FILES['attachments'];
  $uploadDir = realpath(__DIR__ . '/../uploads');
  if ($uploadDir === false) {
    // create uploads if missing
    @mkdir(__DIR__ . '/../uploads', 0777, true);
    $uploadDir = realpath(__DIR__ . '/../uploads');
  }
  if ($uploadDir === false) {
    echo json_encode(['success'=>false, 'message'=>'Cannot create uploads directory.']);
    exit;
  }
  $uploadDir = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR;

  for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] === UPLOAD_ERR_OK) {
      $tmp  = $files['tmp_name'][$i];
      $ext  = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
      $name = uniqid('attach_', true) . ($ext ? '.' . $ext : '');
      if (move_uploaded_file($tmp, $uploadDir . $name)) {
        // store relative path for <img src="/swks/uploads/..">
        $attachment_paths[] = 'uploads/' . $name;
      }
    }
  }
}
$attachment_json = $attachment_paths ? json_encode($attachment_paths) : '';

// 5) Insert forum post
if (!($stmt = $conn->prepare(
  "INSERT INTO forum_post (org_id, user_id, title, content, attachment, created_at)
   VALUES (?, ?, ?, ?, ?, NOW())"
))) {
  echo json_encode(['success'=>false, 'message'=>'Prepare failed while saving post.']);
  exit;
}
$stmt->bind_param("iisss", $org_id, $user_id, $title, $content, $attachment_json);
if (!$stmt->execute()) {
  $stmt->close();
  echo json_encode(['success'=>false, 'message'=>'Could not save post.']);
  exit;
}
$post_id = $stmt->insert_id;
$stmt->close();

// 6) Compose notification message
$orgName = 'SWKS';
if ($gx = $conn->prepare("SELECT org_name FROM organization WHERE org_id=? LIMIT 1")) {
  $gx->bind_param("i", $org_id);
  $gx->execute();
  $gx->bind_result($orgNameRes);
  if ($gx->fetch() && $orgNameRes) $orgName = $orgNameRes;
  $gx->close();
}

$notifType = 'forum_post';
$notifMsg  = ($org_id === SWKS_ORG_ID)
  ? ($title !== '' ? "[SWKS] New forum post: $title" : "[SWKS] A new post was added.")
  : ($title !== '' ? "New forum post in $orgName: $title" : "A new post was added in $orgName.");

// 7) Find recipients
if ($posterRole === 'admin') {
  if ($org_id === SWKS_ORG_ID) {
    // Global: notify everyone except the poster
    $getUsers = $conn->prepare("SELECT user_id FROM user WHERE user_id <> ?");
    $getUsers->bind_param("i", $user_id);
  } else {
    // Specific org: notify users in that org except the poster
    $getUsers = $conn->prepare("SELECT user_id FROM user WHERE org_id=? AND user_id <> ?");
    $getUsers->bind_param("ii", $org_id, $user_id);
  }
} else {
  // Non-admin poster → notify adviser + members of same org (exclude poster)
  $getUsers = $conn->prepare("
    SELECT user_id FROM user
    WHERE org_id=? AND user_id<>? AND user_role IN ('adviser','member')
  ");
  $getUsers->bind_param("ii", $org_id, $user_id);
}

$getUsers->execute();
$res = $getUsers->get_result();

// 8) Insert notifications (one per recipient)
//    Always store the org_id of the POST so filtering is correct
$notifStmt = $conn->prepare("
  INSERT INTO notification (user_id, post_id, type, message, is_seen, created_at, org_id)
  VALUES (?, ?, ?, ?, 0, NOW(), ?)
");
while ($row = $res->fetch_assoc()) {
  $targetUid = (int)$row['user_id'];
  $notifStmt->bind_param("iissi", $targetUid, $post_id, $notifType, $notifMsg, $org_id);
  $notifStmt->execute();
}
$notifStmt->close();
$getUsers->close();

// 9) Done
echo json_encode(['success' => true, 'message' => 'Posted', 'post_id' => $post_id, 'org_id' => $org_id]);
