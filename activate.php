<?php
include 'database/db_connection.php';
include 'includes/header.php';

$email = '';
$type = '';
$entity_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

if (!$entity_id) {
  echo '<div class="login-container"><div class="login-box">
          <h2>Invalid Link</h2>
          <div class="subtext">The activation link is invalid or incomplete.</div>
          <div class="form-row"><a class="link-btn" href="login.php">Back to Sign In</a></div>
        </div></div>';
  include 'includes/footer.php'; exit;
}

// Find applicant email (member or adviser)
$stmt = $conn->prepare("
  SELECT email, 'member' AS type FROM member_details WHERE member_id = ?
  UNION
  SELECT adviser_email AS email, 'adviser' AS type FROM adviser_details WHERE user_id = ?
  LIMIT 1
");
$stmt->bind_param("ii", $entity_id, $entity_id);
$stmt->execute();
$result = $stmt->get_result();

if (!($row = $result->fetch_assoc())) {
  echo '<div class="login-container"><div class="login-box">
          <h2>Invalid Link</h2>
          <div class="subtext">The activation link is invalid or the applicant no longer exists.</div>
          <div class="form-row"><a class="link-btn" href="login.php">Back to Sign In</a></div>
        </div></div>';
  include 'includes<footer.php'; exit;
}

$email = $row['email'];
$type  = $row['type'];

// Check user exists and has no password yet
$stmt2 = $conn->prepare("SELECT 1 FROM user WHERE user_id = ? AND user_email = ? AND (user_password IS NULL OR user_password = '') LIMIT 1");
$stmt2->bind_param("is", $entity_id, $email);
$stmt2->execute();
$result2 = $stmt2->get_result();
if ($result2->num_rows === 0) {
  echo '<div class="login-container"><div class="login-box">
          <h2>Link Expired</h2>
          <div class="subtext">This activation link has already been used or is no longer valid.</div>
          <div class="form-row"><a class="link-btn" href="login.php">Back to Sign In</a></div>
        </div></div>';
  include 'includes/footer.php'; exit;
}
?>

<style>
/* ===== Base / Reset ===== */
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
.login-box input[type="email"],
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

/* submit */
.submit-btn{
  width:100%; padding:13px 0; border:0; border-radius:12px; cursor:pointer;
  font-size:18px; font-weight:800; color:#fff;
  background:linear-gradient(90deg,var(--green),var(--green-d) 80%);
  box-shadow:0 2px 10px rgba(41,128,65,.08); transition:filter .2s ease;
}
.submit-btn:hover,.submit-btn:focus{ filter:brightness(.95); }

/* link row */
.form-row{ width:100%; display:flex; justify-content:center; margin:14px 2px 0; }
.link-btn{ color:#159140; font-weight:800; text-decoration:underline; background:transparent; border:0; cursor:pointer; }

/* checklist */
.pw-req{list-style:none;margin:6px 0 14px;padding:0;font-size:13px}
.pw-req li{display:flex;align-items:center;gap:8px;color:#6b7a82}
.pw-req li .dot{width:8px;height:8px;border-radius:50%;background:#c0392b}
.pw-req li.ok{color:#159140;font-weight:700}
.pw-req li.ok .dot{background:#159140}
.pw-req li.bad{color:#c0392b;font-weight:700}

@media (max-width:520px){
  .login-box{ padding:28px 12px 24px; max-width:98vw; }
  .login-box h2{ font-size:1.6rem; }
}
</style>

<div class="login-container">
  <div class="login-box">
    <h2>Create Password</h2>
    <div class="subtext">Set a strong password (min. 8 chars, with UPPER/lower + number or special)</div>

    <form id="activateForm" action="activate_action.php" method="post" autocomplete="off">
      <!-- send both for safety/back-compat -->
      <input type="hidden" name="user_id"   value="<?= (int)$entity_id ?>">
      <input type="hidden" name="member_id" value="<?= (int)$entity_id ?>">
      <input type="hidden" name="type"      value="<?= htmlspecialchars($type) ?>">

      <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" readonly>

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

      <!-- live requirements -->
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

      <button class="submit-btn" type="submit">Create Password</button>
    </form>
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

  // Live checklist
  const p1 = document.getElementById('password');
  const p2 = document.getElementById('password2');
  const form = document.getElementById('activateForm');

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
      numspec: /(\d|[^A-Za-z0-9])/.test(v)
    };
    setState(elLen, rules.len);
    setState(elUpper, rules.upper);
    setState(elLower, rules.lower);
    setState(elNumSpec, rules.numspec);
    return rules;
  }
  p1?.addEventListener('input', () => evaluate(p1.value));
  evaluate(p1?.value || '');

  // Submit with strong-password + match check; then fetch
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const a = p1?.value ?? '';
    const b = p2?.value ?? '';
    const r = evaluate(a);
    const passed = r.len && r.upper && r.lower && r.numspec;

    if (!passed) {
      await Swal.fire({
        icon: 'warning',
        title: 'Weak password',
        html: 'Your password must have:<br>• at least <b>8 characters</b><br>• <b>uppercase</b> & <b>lowercase</b><br>• a <b>number</b> or <b>special</b> character',
        confirmButtonColor: '#159140'
      });
      p1?.focus(); return;
    }
    if (a !== b) {
      await Swal.fire({
        icon: 'warning',
        title: 'Passwords do not match',
        text: 'Please retype your new password.',
        confirmButtonColor: '#159140'
      });
      p2?.focus(); p2?.select(); return;
    }

    const fd = new FormData(form);
    try {
      const resp = await fetch(form.action, { method: 'POST', body: fd });
      const text = await resp.text();
      let data; try { data = JSON.parse(text); } catch { data = {status:'error', message:text.slice(0,240)||'Unexpected response'}; }

      if (data.status === 'success') {
        await Swal.fire({
          icon: 'success',
          title: 'Password set!',
          text: data.message || 'Your account is now active.',
          showConfirmButton: false,
          timer: 1800
        });
        window.location.replace('login.php');
      } else {
        await Swal.fire({ icon:'error', title:'Error', text:data.message||'Something went wrong.' });
      }
    } catch (err) {
      await Swal.fire({ icon:'error', title:'Network error', text:'Please try again.' });
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
