<?php
// swks/reset_password.php
include 'database/db_connection.php';

function nowPH() { return date('Y-m-d H:i:s'); }

$mode      = 'form';   // form | invalid | success
$msg_error = '';
$uid_get   = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$tok_get   = isset($_GET['token']) ? trim($_GET['token']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $uid = $uid_get;
  $raw = $tok_get;

  if ($uid <= 0 || $raw === '') {
    $mode = 'invalid';
  } else {
    $tokenHash = hash('sha256', $raw); // compare hashed token
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
    // ===== Strong password checks (server-side) =====
    if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*(\d|[^A-Za-z0-9]))[^\s]{8,}$/', $pass)) {
      $msg_error = 'Password must be at least 8 characters and include uppercase, lowercase, and a number or special character.';
    } elseif ($pass !== $pass2) {
      $msg_error = 'Passwords do not match.';
    }

    if (!$msg_error) {
      $hash = password_hash($pass, PASSWORD_DEFAULT);

      // update user password
      $u = $conn->prepare("UPDATE user SET user_password=? WHERE user_id=?");
      $u->bind_param('si', $hash, $uid);
      $u->execute();
      $u->close();

      // mark this reset token as used
      $r = $conn->prepare("UPDATE password_resets SET used=1 WHERE id=?");
      $r->bind_param('i', $pr['id']);
      $r->execute();
      $r->close();

      $mode = 'success';
      // optional: you can also invalidate other active tokens for this user if you want
      // $conn->query("UPDATE password_resets SET used=1 WHERE user_id=".(int)$uid." AND used=0");
    } else {
      $mode = 'form';
      // keep GET values for re-render
      $uid_get = $uid;
      $tok_get = $raw;
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
  /* ===== Same look & feel as Sign In ===== */
  *,*::before,*::after{ box-sizing:border-box; }
  :root{ --green:#1fab4c; --green-d:#159140; --bg:#f7fff9; --input-h:48px; }

  body{
    margin:0; min-height:100vh; background:#f4f7f5;
    font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
  }

  .login-container{
    min-height:83vh; display:flex; align-items:center; justify-content:center; padding:0 10px;
  }
  .login-box{
    background:#fff; padding:32px 24px 28px; border-radius:18px;
    box-shadow:0 6px 28px rgba(0,0,0,.14);
    width:100%; max-width:600px; margin:40px auto;
    display:flex; flex-direction:column; align-items:center; animation:fadeIn .7s ease;
  }
  @keyframes fadeIn{ from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }

  .login-box h2{
    text-align:center; color:#000; margin-bottom:8px;
    font-weight:800; font-size:2rem;
  }
  .subtext{ color:#5b6b63; font-weight:700; margin:0 0 16px; text-align:center; }

  .login-box form{ width:100%; }

  /* inputs */
  .login-box input[type="password"],
  .login-box input[type="text"]#password,
  .login-box input[type="text"]#password2{
    width:100%; height:var(--input-h); padding:10px 16px; margin-bottom:16px;
    border:1.5px solid var(--green); border-radius:12px; background:var(--bg); font-size:16px;
    outline:none; transition:border-color .2s ease;
  }
  .login-box input:focus{ border-color:var(--green-d); }
  #password{ padding-right:52px; }
  #password2{ padding-right:52px; }

  /* password fields with eye button */
  .password-field{ position:relative; width:100%; height:var(--input-h); margin-bottom:16px; }
  .password-field>input{ height:100%; margin:0; padding-right:52px; }
  .toggle-pass{
    position:absolute; top:0; bottom:0; right:12px; width:40px;
    display:flex; align-items:center; justify-content:center;
    background:transparent; border:0; padding:0; cursor:pointer;
    color:var(--green-d); opacity:.85; line-height:0;
  }
  .toggle-pass:hover{ opacity:1; }
  .toggle-pass svg{ width:20px; height:20px; display:block; }
  .toggle-pass .eye-off{ display:none; }
  .toggle-pass.showing .eye{ display:none; }
  .toggle-pass.showing .eye-off{ display:inline-block; }

  /* alerts / messages */
  .err{ color:#c0392b; font-weight:700; margin:-4px 0 10px; text-align:center; }
  .msg-bad{
    background:#fdecec; color:#a32626; border:1px solid #f5bcbc;
    border-radius:12px; padding:14px 16px; margin:10px auto; max-width:460px; text-align:center;
  }

  /* submit */
  .submit-btn{
    width:100%; padding:13px 0; border:0; border-radius:12px; cursor:pointer;
    font-size:18px; font-weight:800; color:#fff;
    background:linear-gradient(90deg,var(--green),var(--green-d) 80%);
    box-shadow:0 2px 10px rgba(41,128,65,.08); transition:filter .2s ease;
  }
  .submit-btn:hover,.submit-btn:focus{ filter:brightness(.95); }

  /* footer link */
  .form-row{ width:100%; display:flex; justify-content:center; margin:14px 2px 0; }
  .link-btn{ color:#159140; font-weight:800; text-decoration:underline; background:transparent; border:0; cursor:pointer; }

  @media (max-width:520px){
    .login-box{ padding:28px 12px 24px; max-width:98vw; }
    .login-box h2{ font-size:1.6rem; }
  }

  /* Password requirements checklist */
  .pw-req{list-style:none;margin:6px 0 14px;padding:0;font-size:13px}
  .pw-req li{display:flex;align-items:center;gap:8px;color:#6b7a82}
  .pw-req li .dot{width:8px;height:8px;border-radius:50%;background:#c0392b}
  .pw-req li.ok{color:#159140;font-weight:700}
  .pw-req li.ok .dot{background:#159140}
  .pw-req li.bad{color:#c0392b;font-weight:700}
</style>
</head>
<body>

<div class="login-container">
  <div class="login-box">
    <?php if ($mode === 'invalid'): ?>
      <h2>Invalid or Expired Link</h2>
      <div class="msg-bad">Your reset link is invalid or has expired. Please request a new one.</div>
      <div class="form-row"><a class="link-btn" href="forgot_password.php">Request new reset link</a></div>

    <?php elseif ($mode === 'success'): ?>
      <h2>Password Updated</h2>
      <div class="subtext">You can now sign in with your new password.</div>
      <div class="form-row"><a class="link-btn" href="login.php">Back to Sign In</a></div>

    <?php else: /* form */ ?>
      <h2>Reset Password</h2>
      <div class="subtext">Set a strong password (min. 8 chars, with UPPER/lower + number or special)</div>
      <?php if ($msg_error): ?><div class="err"><?= htmlspecialchars($msg_error) ?></div><?php endif; ?>

      <form method="post" autocomplete="off" id="rpForm">
        <input type="hidden" name="uid"   value="<?= (int)($uid_get ?? 0) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($tok_get ?? '', ENT_QUOTES) ?>">

        <div class="password-field">
          <input type="password" name="password" id="password" placeholder="New password" required minlength="8">
          <button type="button" class="toggle-pass" data-target="password" aria-label="Show password" title="Show/Hide password">
            <!-- eye -->
            <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <!-- eye-off -->
            <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.77 21.77 0 0 1 5.06-5.94"/>
              <path d="M1 1l22 22"/>
              <path d="M10.58 10.58a3 3 0 1 0 4.24 4.24"/>
              <path d="M9.9 4.24A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a21.77 21.77 0 0 1-3.27 4.14"/>
            </svg>
          </button>
        </div>

        <!-- Live requirements checklist -->
        <ul class="pw-req" id="pwReq">
          <li id="req-len"      class="bad"><span class="dot"></span> At least <b>8 characters</b></li>
          <li id="req-upper"    class="bad"><span class="dot"></span> With <b>uppercase</b> letter</li>
          <li id="req-lower"    class="bad"><span class="dot"></span> With <b>lowercase</b> letter</li>
          <li id="req-numspec"  class="bad"><span class="dot"></span> With a <b>number or special</b> character</li>
        </ul>

        <div class="password-field">
          <input type="password" name="password2" id="password2" placeholder="Confirm new password" required minlength="8">
          <button type="button" class="toggle-pass" data-target="password2" aria-label="Show password" title="Show/Hide password">
            <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.77 21.77 0 0 1 5.06-5.94"/>
              <path d="M1 1l22 22"/>
              <path d="M10.58 10.58a3 3 0 1 0 4.24 4.24"/>
              <path d="M9.9 4.24A10.94 10.94 0 0 1 12 5c7 0 11 7 11 7a21.77 21.77 0 0 1-3.27 4.14"/>
            </svg>
          </button>
        </div>

        <button class="submit-btn" type="submit">Update Password</button>
      </form>

      <div class="form-row">
        <a class="link-btn" href="login.php">Back to Sign In</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Eye toggle
  document.querySelectorAll('.toggle-pass').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-target');
      const input = document.getElementById(id);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.classList.toggle('showing', show);
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  // Live password strength checklist
  const p1 = document.getElementById('password');
  const p2 = document.getElementById('password2');
  const form = document.getElementById('rpForm') || document.querySelector('form');

  const elLen     = document.getElementById('req-len');
  const elUpper   = document.getElementById('req-upper');
  const elLower   = document.getElementById('req-lower');
  const elNumSpec = document.getElementById('req-numspec');

  function setState(el, ok){
    if (!el) return;
    el.classList.toggle('ok',  !!ok);
    el.classList.toggle('bad', !ok);
  }

  function evaluate(v){
    v = v || '';
    const rules = {
      len: v.length >= 8,
      upper: /[A-Z]/.test(v),
      lower: /[a-z]/.test(v),
      numspec: /(\d|[^A-Za-z0-9])/.test(v)   // number OR special
    };
    setState(elLen, rules.len);
    setState(elUpper, rules.upper);
    setState(elLower, rules.lower);
    setState(elNumSpec, rules.numspec);
    return rules;
  }

  p1?.addEventListener('input', () => evaluate(p1.value));

  form?.addEventListener('submit', async (e) => {
    const a = p1?.value ?? '';
    const b = p2?.value ?? '';
    const r = evaluate(a);
    const passed = r.len && r.upper && r.lower && r.numspec;

    if (!passed) {
      e.preventDefault();
      await Swal.fire({
        icon: 'warning',
        title: 'Weak password',
        html: 'Your password must have:<br>• at least <b>8 characters</b><br>• <b>uppercase</b> & <b>lowercase</b><br>• a <b>number</b> or <b>special</b> character',
        confirmButtonColor: '#159140'
      });
      p1?.focus(); return;
    }
    if (a !== b) {
      e.preventDefault();
      await Swal.fire({
        icon: 'warning',
        title: 'Passwords do not match',
        text: 'Please retype your new password.',
        confirmButtonColor: '#159140'
      });
      p2?.focus(); p2?.select();
    }
  });

  // initial paint
  evaluate(p1?.value || '');
});
</script>

</body>
</html>
<!-- SweetAlert2 (skip this line kung meron ka na) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($mode === 'success'): ?>
<script>
document.addEventListener('DOMContentLoaded', async () => {
  await Swal.fire({
    icon: 'success',
    title: 'Password updated',
    text: 'You can now sign in with your new password.',
    showConfirmButton: false,
    timer: 1800
  });
  // Redirect to login after the toast
  window.location.replace('login.php');
});
</script>
<?php endif; ?>
