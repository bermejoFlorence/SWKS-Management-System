<?php include 'includes/header.php'; ?>

<style>
/* Whole page background */
body { min-height: 100vh; margin: 0; }

/* Container for centering */
.login-container{
  min-height: 83vh;
  display:flex; justify-content:center; align-items:center;
  padding:0 10px;
}

/* Login box */
.login-box{
  background:#fff;
  padding:40px 32px 32px 32px;
  border-radius:18px;
  box-shadow:0 6px 28px rgba(0,0,0,.14);
  width:100%; max-width:350px; margin:40px auto;
  display:flex; flex-direction:column; align-items:center;
  position:relative; animation:fadeIn .7s;
}
@keyframes fadeIn{ from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }

.login-box h2{
  text-align:center; color:#000; margin-bottom:32px;
  font-weight:700; letter-spacing:1px; font-size:2rem;
}

/* Inputs (email + password even when toggled to text) */
.login-box input[type="email"],
.login-box input[type="password"],
.login-box input[type="text"]#password{
  width:100%;
  padding:13px 12px;
  margin-bottom:20px;
  border:1.5px solid #1fab4c;
  border-radius:8px;
  font-size:16px; outline:none;
  background:#f7fff9;
  transition:border .2s;
}
.login-box input[type="email"]:focus,
.login-box input[type="password"]:focus,
.login-box input[type="text"]#password:focus{ border-color:#159140; }

/* Submit button ONLY */
.login-box .submit-btn{
  width:100%; padding:13px 0;
  background:linear-gradient(90deg,#1fab4c,#159140 80%);
  color:#fff; border:none; border-radius:8px;
  font-size:18px; font-weight:700; letter-spacing:1px;
  transition:background .2s; box-shadow:0 2px 10px rgba(41,128,65,.08);
  cursor:pointer;
}
.login-box .submit-btn:hover,
.login-box .submit-btn:focus{
  background:linear-gradient(90deg,#159140,#117c37 90%);
}

/* Error message */
.login-box .error{
  color:#c0392b; text-align:center; margin-bottom:16px;
  font-size:15px; font-weight:600;
}

/* Password reveal */
.password-field{ position:relative; margin-bottom:20px; }
.password-field input{ padding-right:44px; } /* space for the eye */
.toggle-pass{
  position:absolute; right:10px; top:50%; transform:translateY(-50%);
  background:transparent; border:0; padding:6px; line-height:1; cursor:pointer;
  color:#159140; opacity:.75;
}
.toggle-pass:hover{ opacity:1; }
.toggle-pass svg{ width:22px; height:22px; display:inline-block; }
.toggle-pass .eye-off{ display:none; }
.toggle-pass.showing .eye{ display:none; }
.toggle-pass.showing .eye-off{ display:inline-block; }

/* Responsive */
@media (max-width:520px){
  .login-box{ padding:30px 12px 22px; max-width:98vw; }
  .login-box h2{ font-size:1.5rem; margin-bottom:22px; }
  .login-container{ min-height:90vh; padding:0 2px; }
}
/* 1) Gawing mas wide ang card at bawasan ang side padding */
.login-box{
  max-width: 460px;              /* dati 350px */
  padding: 32px 24px 28px;       /* mas konting side padding para humaba ang fields */
}

/* 2) Pahabain at i-center ang fields sa loob ng card */
.login-box input[type="email"],
.password-field{                 /* wrapper ng password */
  width: clamp(320px, 88vw, 440px);
  margin-left: auto;
  margin-right: auto;
}

/* 3) Uniform height + mas sakto ang padding sa loob ng inputs */
.login-box input[type="email"],
.login-box input[type="password"],
.login-box input[type="text"]#password{
  height: 52px;
  padding: 12px 48px 12px 16px;  /* may space sa kanan para sa eye */
  border-radius: 10px;
}

/* 4) Perfect vertical centering ng eye button */
.password-field{
  position: relative;
  margin-bottom: 20px;
}
.password-field input{
  width: 100%;
}
.toggle-pass{
  position: absolute;
  right: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 28px; height: 20px;
  display: grid;                 /* centers the svg perfectly */
  place-items: center;
  background: transparent;
  border: 0;
  padding: 0;
  cursor: pointer;
  color: #159140;
  opacity: .8;
}
.toggle-pass:hover{ opacity: 1; }
.toggle-pass svg{ width: 20px; height: 20px; }
.form-row{
  width:100%;
  display:flex;
  justify-content:flex-end;
  margin: -6px 2px 16px;   /* a little tighter, adjust as you like */
    margin-top: 20px;
}
.link-btn{
  appearance:none;
  border:0;
  background:transparent;
  color:#159140;
  font-weight:700;
  cursor:pointer;
  padding:0;
  line-height:1.1;
}
.link-btn:hover{ text-decoration:underline; }
.form-row-center{ justify-content:center; }

</style>

<div class="login-container">
  <div class="login-box">
    <h2>Sign In</h2>

    <?php if (isset($_GET['error'])): ?>
      <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <form action="login_action.php" method="post" autocomplete="off">
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

<?php include 'includes/footer.php'; ?>
