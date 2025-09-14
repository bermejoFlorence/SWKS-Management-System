<?php
include 'database/db_connection.php'; // Adjust path if needed
include 'includes/header.php';

$email = '';
$type = '';
$entity_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;

if (!$entity_id) {
    echo '
    <div class="login-container"><div class="login-box">
        <h2>Invalid Link</h2>
        <p>The activation link is invalid or incomplete.</p>
        <a href="login.php" class="btn btn-success mt-3">Back to Login</a>
    </div></div>';
    include 'includes/footer.php';
    exit;
}

// Step 1: Check if entity exists in member_details or adviser_details
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
    echo '
    <div class="login-container"><div class="login-box">
        <h2>Invalid Link</h2>
        <p>The activation link is invalid or the applicant no longer exists.</p>
        <a href="login.php" class="btn btn-success mt-3">Back to Login</a>
    </div></div>';
    include 'includes/footer.php';
    exit;
}

$email = $row['email'];
$type = $row['type'];

// ✅ Step 2: Check if the user exists AND has NOT yet set a password
$stmt2 = $conn->prepare("SELECT 1 FROM user WHERE user_id = ? AND user_email = ? AND (user_password IS NULL OR user_password = '') LIMIT 1");
$stmt2->bind_param("is", $entity_id, $email);
$stmt2->execute();
$result2 = $stmt2->get_result();

if ($result2->num_rows === 0) {
    echo '
    <div class="login-container"><div class="login-box">
        <h2>Link Expired</h2>
        <p>This activation link has already been used or is no longer valid.</p>
        <a href="login.php" class="btn btn-success mt-3">Back to Login</a>
    </div></div>';
    include 'includes/footer.php';
    exit;
}
?>

<style>
/* Copy your login.php style here, but update heading/button colors if you want */
body { 
    min-height: 
    100vh; margin: 0; 
}
.login-container { 
    min-height: 83vh; 
    display: flex; 
    justify-content: center; 
    align-items: center; 
    padding: 0 10px; 
}
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
    from { 
        opacity: 0; 
        transform: translateY(40px);
    } to { opacity: 1; transform: translateY(0);}
 }
.login-box h2 { 
    text-align: center; 
    color:rgb(0, 0, 0); 
    margin-bottom: 32px; 
    font-weight: 700; 
    letter-spacing: 1px; 
    font-size: 2rem; 
}
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
    cursor: pointer; letter-spacing: 1px;
}
.login-box button:hover, .login-box button:focus { 
    background: linear-gradient(90deg, #159140, #117c37 90%);
}
.login-box .error { 
    color: #c0392b; 
    text-align: center; 
    margin-bottom: 16px; 
    font-size: 15px; 
    font-weight: 600; 
}
@media (max-width: 520px) {
    .login-box { 
        padding: 30px 12px 22px 12px; 
        max-width: 98vw;
    }
    .login-box h2 
    { font-size: 1.5rem; 
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
        <h2>Create Password</h2>
        <form id="activateForm" action="activate_action.php" method="post" autocomplete="off">
            <input type="hidden" name="member_id" value="<?= isset($member_id) ? $member_id : '' ?>">
            <input type="email" name="email" placeholder="Email Address" required readonly
                   value="<?= htmlspecialchars($email) ?>">
            <input type="password" name="password" placeholder="New Password" required>
            <button type="submit">Create Password</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('activateForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const formData = new FormData(form);

    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                confirmButtonColor: '#043c00'
            }).then(() => {
                window.location.href = 'login.php';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message,
                confirmButtonColor: '#c0392b'
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Something went wrong. Please try again.',
            confirmButtonColor: '#c0392b'
        });
    });
});
</script>
<?php include 'includes/footer.php'; ?>
