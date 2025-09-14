<?php include 'includes/header.php'; ?>

<style>
/* Whole page background */
body {
    min-height: 100vh;
    margin: 0;
}

/* Container for centering */
.login-container {
    min-height: 83vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 10px;
}

/* Login box styles */
.login-box {
    background: #fff;
    padding: 40px 32px 32px 32px;
    border-radius: 18px;
    box-shadow: 0 6px 28px rgba(0, 0, 0, 0.14);
    width: 100%;
    max-width: 350px;
    margin: 40px auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    animation: fadeIn 0.7s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(40px);}
    to { opacity: 1; transform: translateY(0);}
}

.login-box h2 {
    text-align: center;
    color:rgb(0, 0, 0);
    margin-bottom: 32px;
    font-weight: 700;
    letter-spacing: 1px;
    font-size: 2rem;
}

/* Input field style */
.login-box input[type="email"],
.login-box input[type="password"] {
    width: 100%;
    padding: 13px 12px;
    margin-bottom: 20px;
    border: 1.5px solid #1fab4c;
    border-radius: 8px;
    font-size: 16px;
    outline: none;
    background: #f7fff9;
    transition: border 0.2s;
}
.login-box input[type="email"]:focus,
.login-box input[type="password"]:focus {
    border-color: #159140;
}

/* Button style */
.login-box button {
    width: 100%;
    padding: 13px 0;
    background: linear-gradient(90deg, #1fab4c, #159140 80%);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 700;
    transition: background 0.2s;
    box-shadow: 0 2px 10px rgba(41,128,65,0.08);
    cursor: pointer;
    letter-spacing: 1px;
}
.login-box button:hover, .login-box button:focus {
    background: linear-gradient(90deg, #159140, #117c37 90%);
}

/* Error message style */
.login-box .error {
    color: #c0392b;
    text-align: center;
    margin-bottom: 16px;
    font-size: 15px;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 520px) {
    .login-box {
        padding: 30px 12px 22px 12px;
        max-width: 98vw;
    }
    .login-box h2 {
        font-size: 1.5rem;
        margin-bottom: 22px;
    }
    .login-container {
        min-height: 90vh;
        padding: 0 2px;
    }
}
</style>

<div class="login-container">
    <div class="login-box">
        <h2>Sign In</h2>
        <?php if (isset($_GET['error'])): ?>
            <div class="error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>
        <form action="login_action.php" method="post" autocomplete="off">
            <input type="email" name="email" placeholder="Email Address" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (isset($_GET['success']) && $_GET['success'] == 1 && isset($_GET['role'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    Swal.fire({
        icon: 'success',
        title: 'Login Successful!',
        text: 'You are now being redirected to your dashboard...',
        showConfirmButton: false,
        timer: 1800
    }).then(function(result) {
        // Redirect depende sa role
        var role = "<?php echo $_GET['role']; ?>";
        if(role === "admin") {
            window.location.href = "admin/index.php";
        } else if(role === "adviser") {
            window.location.href = "adviser/index.php";
        } else if(role === "member") {
            window.location.href = "member/index.php";
        } else {
            window.location.href = "index.php";
        }
    });
    // For automatic redirect even without clicking OK
    setTimeout(function() {
        var role = "<?php echo $_GET['role']; ?>";
        if(role === "admin") {
            window.location.href = "admin/index.php";
        } else if(role === "adviser") {
            window.location.href = "adviser/index.php";
        } else if(role === "member") {
            window.location.href = "member/index.php";
        } else {
            window.location.href = "index.php";
        }
    }, 1900);
});
</script>
<?php endif; ?>



<?php include 'includes/footer.php'; ?>
