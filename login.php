<?php include 'includes/header.php'; ?>
<style>
/* ===== Base / Reset ===== */
*,*::before,*::after{ box-sizing:border-box; }

:root{
  --green:#1fab4c;
  --green-d:#159140;
  --green-dd:#117c37;
  --bg:#f7fff9;
  --input-h:48px;   /* input height */
}

body{
  margin:0; min-height:100vh; background:#f4f7f5;
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;
}

/* ===== Container ===== */
.login-container{
  min-height:83vh; display:flex; align-items:center; justify-content:center; padding:0 10px;
}

/* ===== Card ===== */
.login-box{
  background:#fff;
  padding:32px 24px 28px;
  border-radius:18px;
  box-shadow:0 6px 28px rgba(0,0,0,.14);
  width:100%; max-width:600px;       /* card width (hindi natin gagalawin inputs dito) */
  margin:40px auto;
  display:flex; flex-direction:column; align-items:center;
  animation:fadeIn .7s ease;
}
@keyframes fadeIn{ from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }

.login-box h2{
  text-align:center; color:#000; margin-bottom:24px;
  font-weight:800; letter-spacing:.5px; font-size:2rem;
}

/* >>> CRUCIAL: palawakin ang form para sumagad ang inputs <<< */
.login-box form{ width:100%; }

/* ===== Inputs ===== */
.login-box input[type="email"],
.login-box input[type="password"],
.login-box input[type="text"]#password{
  width:100%;
  height:var(--input-h);
  padding:10px 16px;
  margin-bottom:16px;
  border:1.5px solid var(--green);
  border-radius:12px;
  font-size:16px; outline:none; background:var(--bg);
  transition:border-color .2s ease;
}
.login-box input[type="email"]:focus,
.login-box input[type="password"]:focus,
.login-box input[type="text"]#password:focus{ border-color:var(--green-d); }

/* Extra right padding para sa eye button */
#password{ padding-right:52px; }

/* ===== Password reveal (perfect vertical center) ===== */
.password-field{
  position:relative; width:100%; height:var(--input-h); margin-bottom:16px;
}
.password-field>input{
  height:100%; margin:0; padding-right:52px;
}
.toggle-pass{
  position:absolute; top:0; bottom:0; right:12px;
  width:40px; display:flex; align-items:center; justify-content:center;
  background:transparent; border:0; padding:0; cursor:pointer;
  color:var(--green-d); opacity:.85; line-height:0;
}
.toggle-pass:hover{ opacity:1; }
.toggle-pass svg{ width:20px; height:20px; display:block; }
.toggle-pass .eye-off{ display:none; }
.toggle-pass.showing .eye{ display:none; }
.toggle-pass.showing .eye-off{ display:inline-block; }

/* ===== Submit button ===== */
.login-box .submit-btn{
  width:100%; padding:13px 0;
  background:linear-gradient(90deg,var(--green),var(--green-d) 80%);
  color:#fff; border:none; border-radius:12px;
  font-size:18px; font-weight:800; letter-spacing:.5px;
  transition:filter .2s ease; box-shadow:0 2px 10px rgba(41,128,65,.08);
  cursor:pointer;
}
.login-box .submit-btn:hover,
.login-box .submit-btn:focus{ filter:brightness(.95); }

/* ===== Error & footer ===== */
.login-box .error{ color:#c0392b; text-align:center; margin-bottom:16px; font-size:15px; font-weight:700; }
.form-row{ width:100%; display:flex; justify-content:flex-end; margin:12px 2px 0; }
.form-row-center{ justify-content:center; }
.link-btn,a.link-btn{ appearance:none; border:0; background:transparent; color:var(--green-d); font-weight:800; cursor:pointer; padding:0; line-height:1.1; text-decoration:underline; }
.link-btn:hover{ text-decoration:underline; }

/* ===== Responsive ===== */
@media (max-width:520px){
  .login-box{ padding:28px 12px 24px; max-width:98vw; }
  .login-box h2{ font-size:1.6rem; margin-bottom:20px; }
}
</style>


<div class="login-container">
  <div class="login-box">
    <h2>Sign In</h2>

    <?php if (isset($_GET['error'])): ?>
      <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form id="loginForm" action="login_action.php" method="post" autocomplete="off">
      <input type="email" name="email" placeholder="Email Address" required autofocus>

      <div class="password-field">
        <input type="password" name="password" id="password" placeholder="Password" required>
        <button type="button" class="toggle-pass" aria-label="Show password" title="Show/Hide password">
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

      <button type="submit" class="submit-btn">Sign In</button>
    </form>
  <div class="form-row form-row-center">
  <a href="forgot_password.php" class="link-btn">Forgot password?</a>
</div>


  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Eye toggle: ALWAYS loaded -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  const pass = document.getElementById('password');
  const toggle = document.querySelector('.toggle-pass');
  if (!pass || !toggle) return;

  toggle.addEventListener('click', function () {
    const show = pass.type === 'password';
    pass.type = show ? 'text' : 'password';
    this.classList.toggle('showing', show);
    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});
</script>

<?php if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['role'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
  Swal.fire({
    icon: 'success',
    title: 'Login Successful!',
    text: 'You are now being redirected to your dashboard...',
    showConfirmButton: false,
    timer: 1800
  }).then(function() { redirectByRole(); });
  setTimeout(redirectByRole, 1900);
  
  function redirectByRole(){
    var role = "<?= $_GET['role']; ?>";
    if      (role === "admin")   window.location.href = "admin/index.php";
    else if (role === "adviser") window.location.href = "adviser/index.php";
    else if (role === "member")  window.location.href = "member/index.php";
    else                         window.location.href = "index.php";
  }

   const forgotBtn = document.getElementById('forgotBtn');
  if (forgotBtn) {
    forgotBtn.addEventListener('click', async () => {
      const { value: email } = await Swal.fire({
        title: 'Reset password',
        input: 'email',
        inputLabel: 'Enter your account email',
        inputPlaceholder: 'you@example.com',
        confirmButtonText: 'Send reset link',
        showCancelButton: true,
        inputAttributes: { autocapitalize: 'off' }
      });

      if (!email) return;

      try {
        const fd = new FormData();
        fd.append('email', email.trim());

        const res  = await fetch('forgot_password_request.php', { method: 'POST', body: fd });
        const data = await res.json();

        // Always show generic message to avoid email enumeration
        if (data?.ok) {
          Swal.fire({
            icon: 'success',
            title: 'Check your email',
            text: 'If that email is registered, a reset link has been sent.',
          });
        } else {
          Swal.fire({ icon:'error', title:'Error', text: data?.msg || 'Something went wrong.' });
        }
      } catch (e) {
        Swal.fire({ icon:'error', title:'Network error', text:'Please try again.' });
      }
    });
  }
});
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // existing eye-toggle code...
  const pass = document.getElementById('password');
  const toggle = document.querySelector('.toggle-pass');
  if (pass && toggle) {
    toggle.addEventListener('click', function () {
      const show = pass.type === 'password';
      pass.type = show ? 'text' : 'password';
      this.classList.toggle('showing', show);
      this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  }

  /* === enforce @cbsua.edu.ph === */
  const form  = document.getElementById('loginForm');
  const email = form ? form.querySelector('input[name="email"]') : null;

  if (form && email) {
    form.addEventListener('submit', function (e) {
      const v = (email.value || '').trim().toLowerCase();
      if (!v.endsWith('@cbsua.edu.ph')) {
        e.preventDefault();
        Swal.fire({
          icon: 'warning',
          title: 'Use your Institutional Email',
          text: 'Please sign in using your @cbsua.edu.ph address.',
          confirmButtonColor: '#159140'
        }).then(() => { email.focus(); email.select(); });
      }
    });
  }
});
</script>

<?php include 'includes/footer.php'; ?>
