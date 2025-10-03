<?php
// swks/reset_password.php
include 'database/db_connection.php';

function nowPH() { return date('Y-m-d H:i:s'); }

$mode      = 'form';   // form | invalid | success
$msg_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $uid   = isset($_GET['uid'])   ? (int)$_GET['uid'] : 0;
  $raw   = isset($_GET['token']) ? trim($_GET['token']) : '';

  if ($uid <= 0 || $raw === '') {
    $mode = 'invalid';
  } else {
    $tokenHash = hash('sha256', $raw);            // ✨ HASH the raw token from URL

    $stmt = $conn->prepare("
      SELECT id, expires_at, used
      FROM password_resets
      WHERE user_id = ? AND token = ?
      ORDER BY id DESC
      LIMIT 1
    ");
    $stmt->bind_param('is', $uid, $tokenHash);
    $stmt->execute();
    $pr = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pr || (int)$pr['used'] === 1 || nowPH() > $pr['expires_at']) {
      $mode = 'invalid';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $uid        = (int)($_POST['uid'] ?? 0);
  $raw        = trim($_POST['token'] ?? '');
  $pass       = (string)($_POST['password'] ?? '');
  $pass2      = (string)($_POST['password2'] ?? '');
  $tokenHash  = hash('sha256', $raw);

  // re-validate token
  $stmt = $conn->prepare("
    SELECT id, expires_at, used
    FROM password_resets
    WHERE user_id = ? AND token = ?
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->bind_param('is', $uid, $tokenHash);
  $stmt->execute();
  $pr = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$pr || (int)$pr['used'] === 1 || nowPH() > $pr['expires_at']) {
    $mode = 'invalid';
  } else {
    // basic password checks
    if (strlen($pass) < 8)        { $msg_error = 'Password must be at least 8 characters.'; }
    elseif ($pass !== $pass2)     { $msg_error = 'Passwords do not match.'; }

    if (!$msg_error) {
      $hash = password_hash($pass, PASSWORD_DEFAULT);

      // update user password
      $u = $conn->prepare("UPDATE user SET user_password=? WHERE user_id=?");
      $u->bind_param('si', $hash, $uid);
      $u->execute();
      $u->close();

      // mark this reset token as used (and optionally invalidate others)
      $r = $conn->prepare("UPDATE password_resets SET used=1 WHERE id=?");
      $r->bind_param('i', $pr['id']);
      $r->execute();
      $r->close();

      $mode = 'success';
    } else {
      $mode = 'form';
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Reset Password</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  body{margin:0;background:#f6faf7;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif}
  .wrap{min-height:88vh;display:grid;place-items:center;padding:32px}
  .card{width:min(560px,92vw);background:#fff;border:1px solid #e6f0ea;border-radius:18px;
        box-shadow:0 18px 44px rgba(0,0,0,.08);padding:28px 22px;text-align:center}
  h1{margin:6px 0 6px;font-size:2rem;color:#12341f}
  .lead{color:#5b6b63;font-weight:700;margin-bottom:18px}
  form{width:clamp(280px,80vw,380px);margin:0 auto;text-align:left}
  .in{width:100%;height:48px;border-radius:10px;border:1.5px solid #1fab4c;background:#f7fff9;
      padding:10px 14px;font-size:16px;outline:none;margin:8px 0 14px}
  .in:focus{border-color:#159140;box-shadow:0 0 0 3px rgba(21,145,64,.12)}
  .btn{width:100%;height:50px;border:0;border-radius:12px;background:linear-gradient(90deg,#1fab4c,#159140 82%);
       color:#fff;font-weight:800;letter-spacing:.2px;cursor:pointer;box-shadow:0 12px 26px rgba(21,145,64,.18)}
  .msg-bad{background:#fdecec;color:#a32626;border:1px solid #f5bcbc;border-radius:12px;padding:14px 16px;margin:10px auto;max-width:460px}
  .link{display:inline-block;margin-top:12px;font-weight:800;color:#118a3b;text-decoration:none}
  .err{color:#c0392b;font-weight:700;margin:-4px 0 10px}
</style>
</head>
<body>
<div class="wrap"><div class="card">
<?php if ($mode === 'invalid'): ?>
  <h1>Invalid or Expired Link</h1>
  <div class="msg-bad">Your reset link is invalid or has expired. Please request a new one.</div>
  <a class="link" href="forgot_password.php">Request new reset link</a>

<?php elseif ($mode === 'success'): ?>
  <h1>Password Updated</h1>
  <p class="lead">You can now sign in with your new password.</p>
  <a class="link" href="login.php">Back to Sign In</a>

<?php else: // form ?>
  <h1>Set a New Password</h1>
  <p class="lead">Please enter and confirm your new password.</p>
  <?php if ($msg_error): ?><div class="err"><?=htmlspecialchars($msg_error)?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="uid"   value="<?= (int)($_GET['uid'] ?? $_POST['uid'] ?? 0) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? $_POST['token'] ?? '') ?>">
    <label>New password</label>
    <input class="in" type="password" name="password" required minlength="8" placeholder="At least 8 characters">
    <label>Confirm password</label>
    <input class="in" type="password" name="password2" required minlength="8" placeholder="Retype your password">
    <button class="btn" type="submit">Update Password</button>
  </form>
<?php endif; ?>
</div></div>
</body>
</html>
