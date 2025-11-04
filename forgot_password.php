<?php include 'includes/header.php'; ?>

<style>
/* ===== same look & feel as login ===== */
*,*::before,*::after{ box-sizing:border-box; }
:root{
  --green:#1fab4c; --green-d:#159140; --bg:#f7fff9; --input-h:48px;
}

body{ margin:0; min-height:100vh; background:#f4f7f5; font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }

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

.login-box h2{ text-align:center; color:#000; margin-bottom:8px; font-weight:800; font-size:2rem; }
.subtext{ color:#5b6b63; font-weight:700; margin:0 0 16px; }

.login-box form{ width:100%; }

.login-box input[type="email"]{
  width:100%; height:var(--input-h); padding:10px 16px; margin-bottom:8px;
  border:1.5px solid var(--green); border-radius:12px; background:var(--bg); font-size:16px;
  outline:none; transition:border-color .2s ease;
}
.login-box input[type="email"]:focus{ border-color:var(--green-d); }

.hint{ display:block; min-height:18px; margin:6px 4px 12px; font-size:13px; color:#6b7a82; }
.hint.bad{ color:#c0392b; font-weight:700; }

.submit-btn{
  width:100%; padding:13px 0; border:0; border-radius:12px; cursor:pointer;
  font-size:18px; font-weight:800; color:#fff;
  background:linear-gradient(90deg,var(--green),var(--green-d) 80%);
  box-shadow:0 2px 10px rgba(41,128,65,.08); transition:filter .2s ease;
}
.submit-btn:hover,.submit-btn:focus{ filter:brightness(.95); }
.submit-btn[disabled]{ filter:grayscale(.2); opacity:.9; cursor:not-allowed; }

.form-row{ width:100%; display:flex; justify-content:center; margin:14px 2px 0; }
.link-btn{ color:#159140; font-weight:800; text-decoration:underline; background:transparent; border:0; cursor:pointer; }

/* keep consistency on small screens */
@media (max-width:520px){
  .login-box{ padding:28px 12px 24px; max-width:98vw; }
  .login-box h2{ font-size:1.6rem; }
}
</style>

<div class="login-container">
  <div class="login-box">
    <h2>Forgot Password</h2>
    <div class="subtext">Type your institutional email</div>

    <form id="fpForm" autocomplete="off" novalidate>
      <input type="email" name="email" id="email" placeholder="Email Address" required>
      <small id="emailWarn" class="hint"></small>
      <button type="submit" id="sendBtn" class="submit-btn">Continue</button>
    </form>

    <div class="form-row">
      <a class="link-btn" href="login.php">Back to Sign In</a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('fpForm');
  const btn  = document.getElementById('sendBtn');
  const email = document.getElementById('email');
  const warn  = document.getElementById('emailWarn');

  const SEND_URL  = 'forgot_password_send.php';
  const CHECK_URL = 'forgot_password_check.php';

  // live hint: institutional email enforcement + deactivated check
  let t = null;
  email.addEventListener('input', () => {
    clearTimeout(t);
    const v = (email.value || '').trim().toLowerCase();
    warn.textContent = '';
    warn.className = 'hint';

    // quick domain hint
    if (v && !v.endsWith('@cbsua.edu.ph')) {
      warn.textContent = 'Use your @cbsua.edu.ph address.';
      warn.classList.add('bad');
    }

    // async status check (only if looks institutional)
    if (v && v.endsWith('@cbsua.edu.ph')) {
      t = setTimeout(async () => {
        try {
          const res  = await fetch(`${CHECK_URL}?email=${encodeURIComponent(v)}`);
          const text = await res.text();
          let data; try { data = JSON.parse(text); } catch { return; }
          if (data.exists && data.role === 'member' && data.deactivated) {
            warn.textContent = 'This member account is deactivated. Please contact your adviser/admin.';
            warn.classList.add('bad');
          } else {
            warn.textContent = '';
          }
        } catch(_) {}
      }, 300);
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const v = (email.value || '').trim().toLowerCase();
    if (!v.endsWith('@cbsua.edu.ph')) {
      await Swal.fire({
        icon: 'warning',
        title: 'Use your Institutional Email',
        text: 'Please use your @cbsua.edu.ph address.',
        confirmButtonColor: '#159140'
      });
      email.focus(); email.select();
      return;
    }
    if (warn.classList.contains('bad') && warn.textContent.toLowerCase().includes('deactivated')) {
      await Swal.fire({
        icon: 'warning',
        title: 'Account deactivated',
        text: 'Please contact your adviser/admin.',
        confirmButtonColor: '#159140'
      });
      return;
    }

    btn.disabled = true;
    try {
      const fd  = new FormData(form);
      const res = await fetch(SEND_URL, { method: 'POST', body: fd });

      const text = await res.text();
      let data; try { data = JSON.parse(text); }
      catch {
        console.error('Non-JSON response:', text);
        await Swal.fire({ icon:'error', title:'Server error', text: text.slice(0,240) || 'Unexpected response.' });
        return;
      }

      if (data.ok) {
        await Swal.fire({ icon:'success', title:'Check your inbox', text:'A password reset link has been sent to your email.' });
        window.location.href = 'login.php';
      } else {
        Swal.fire({ icon:'error', title:'Unable to proceed', text: data.msg || 'Please try again.' });
      }
    } catch {
      Swal.fire({ icon:'error', title:'Network error', text:'Please try again.' });
    } finally {
      btn.disabled = false;
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>
