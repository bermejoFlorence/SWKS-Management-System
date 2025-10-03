<?php
include 'includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password</title>
<style>
  body{min-height:100vh;margin:0;background:#f6faf7;}
  .fp-wrap{min-height:88vh;display:flex;align-items:center;justify-content:center;padding:24px;}
  .fp-card{
    width:min(560px,92vw); background:#fff; border:1px solid #e6f0ea; border-radius:18px;
    box-shadow:0 18px 44px rgba(0,0,0,.08); padding:28px 22px; text-align:center;
  }
  .fp-title{font-size:2rem;font-weight:800;color:#1b2b20;margin:6px 0;}
  .fp-sub{color:#5b6b63;font-weight:700;margin-bottom:18px;}

  /* inner form – shorter width */
  .fp-form{ width:clamp(280px,80vw,360px); margin:0 auto; }

  .fp-input{
    width:100%; height:48px; border-radius:10px; border:1.5px solid #1fab4c;
    background:#f7fff9; padding:10px 14px; font-size:16px; outline:none;
  }
  .fp-input:focus{ border-color:#159140; box-shadow:0 0 0 3px rgba(21,145,64,.12); }

  .fp-btn{
    width:100%; height:50px; margin-top:12px; border:0; border-radius:12px; cursor:pointer;
    font-weight:800; letter-spacing:.2px; color:#fff;
    background:linear-gradient(90deg,#1fab4c,#159140 82%); box-shadow:0 12px 26px rgba(21,145,64,.18);
  }
  .fp-btn[disabled]{filter:grayscale(0.2);opacity:.8;cursor:not-allowed;}

  .fp-back{margin-top:14px;display:inline-block;font-weight:700;color:#159140;text-decoration:none;}
  .fp-back:hover{text-decoration:underline;}
  /* place with the rest of your CSS */
.hint{display:block;min-height:18px;margin:6px 4px 0;font-size:13px;color:#6b7a82}
.hint.bad{color:#c0392b;font-weight:700}

</style>
</head>
<body>

<div class="fp-wrap">
  <div class="fp-card">
    <div class="fp-title">Forgot Password</div>
    <div class="fp-sub">Type your email</div>

    <form id="fpForm" class="fp-form" autocomplete="off" novalidate>
      <input type="email" class="fp-input" name="email" id="email" placeholder="Email Address" required>
      <small id="emailWarn" class="hint"></small>
      <button type="submit" id="sendBtn" class="fp-btn">Continue</button>
    </form>

    <a class="fp-back" href="login.php">Back to Sign In</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('fpForm');
  const btn  = document.getElementById('sendBtn');

  // siguradong tama ang path kahit saan ka mag-navigate
  const SEND_URL  = 'forgot_password_send.php';

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    btn.disabled = true;

    try {
      const fd  = new FormData(form);
      const res = await fetch(SEND_URL, { method: 'POST', body: fd });

      // SAFE PARSE (para makita mo kung hindi JSON ang reply)
      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch (err) {
        console.error('Non-JSON response from server:', text);
        await Swal.fire({ icon:'error', title:'Server error', text: text.slice(0,240) || 'Unexpected response.' });
        return;
      }

      if (data.ok) {
        await Swal.fire({
          icon: 'success',
          title: 'Check your inbox',
          text: 'A password reset link has been sent to your email.'
        });
        window.location.href = 'login.php';
      } else {
        Swal.fire({ icon:'error', title:'Unable to proceed', text: data.msg || 'Please try again.' });
      }
    } catch (err) {
      Swal.fire({ icon:'error', title:'Network error', text:'Please try again.' });
    } finally {
      btn.disabled = false;
    }
  });
});
</script>
<script>
(function(){
  const email = document.getElementById('email');
  const warn  = document.getElementById('emailWarn');
  const CHECK_URL = 'forgot_password_check.php';

  if (!email || !warn) return;

  let t = null;
  email.addEventListener('input', () => {
    clearTimeout(t);
    warn.textContent = '';
    warn.className   = 'hint';

    const v = email.value.trim();
    if (!v) return;

    t = setTimeout(async () => {
      try{
        const res  = await fetch(`${CHECK_URL}?email=${encodeURIComponent(v)}`);
        const text = await res.text();
        let data;
        try { data = JSON.parse(text); } catch { return; }

        if (data.exists && data.role === 'member' && data.deactivated){
          warn.textContent = 'This member account is deactivated. Please contact your adviser/admin.';
          warn.classList.add('bad');
        } else {
          warn.textContent = '';
        }
      }catch(e){}
    }, 350);
  });

  // block submit kapag deactivated
  const form = document.getElementById('fpForm');
  form?.addEventListener('submit', e => {
    if (warn.classList.contains('bad')) {
      e.preventDefault();
      Swal.fire({icon:'warning',title:'Account deactivated',text:'Please contact your adviser/admin.'});
    }
  });
})();
</script>

</body>
</html>
<?php include 'includes/footer.php'; ?>
