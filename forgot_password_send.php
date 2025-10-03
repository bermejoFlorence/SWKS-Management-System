<?php
// swks/forgot_password/forgot_password_send.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'database/db_connection.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok'=>false, 'msg'=>'Invalid method']); exit;
}

$email = trim($_POST['email'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok'=>false, 'msg'=>'Invalid email']); exit;
}

/* 1) Hanapin ang user */
$stmt = $conn->prepare("SELECT user_id, user_role FROM user WHERE user_email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
  echo json_encode(['ok'=>false, 'msg'=>'Email not found.']); exit; // gusto mo ng explicit error
}

$user_id = (int)$user['user_id'];
$role    = (string)$user['user_role'];

/* 2) Kung MEMBER, i-check kung deactivated sa member_details */
if ($role === 'member') {
  $q = $conn->prepare("
    SELECT status 
    FROM member_details 
    WHERE user_id = ? OR email = ?
    ORDER BY date_submitted DESC 
    LIMIT 1
  ");
  $q->bind_param('is', $user_id, $email);
  $q->execute();
  $row = $q->get_result()->fetch_assoc();
  $q->close();

  $status = strtolower($row['status'] ?? '');
  if (in_array($status, ['deactivated','inactive','disabled'])) {
    echo json_encode([
      'ok'  => false,
      'msg' => 'This account is deactivated. Please contact your adviser/admin.'
    ]);
    exit;
  }
}

/* 3) Generate reset token + save */
$raw   = bin2hex(random_bytes(32));
$token = hash('sha256', $raw);
$expires = date('Y-m-d H:i:s', time()+3600); // 1 hour

$conn->query("
  CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id), INDEX(token)
  ) ENGINE=InnoDB
");

$ins = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
$ins->bind_param('iss', $user_id, $token, $expires);
$ins->execute();
$ins->close();

/* 4) Send email via PHPMailer */
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

$mail = new PHPMailer(true);

try {
  $BASE_URL = 'https://swks-organization.com';
  $resetUrl = $BASE_URL . '/reset_password.php?uid=' . $user_id . '&token=' . $raw;

  $mail->isSMTP();
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'joshua.lerin@cbsua.edu.ph';       // TODO
  $mail->Password   = 'drdj feav apsx uact';          // TODO (Gmail App Password)
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port       = 587;

  $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Accounts');
  $mail->addAddress($email);

  $mail->isHTML(true);
  $mail->Subject = 'Reset your SWKS password';
  $mail->Body = "
    <p>We received a request to reset your password.</p>
    <p style='margin:16px 0'>
      <a href='$resetUrl' style='padding:12px 22px;border-radius:6px;background:#159140;color:#fff;text-decoration:none;font-weight:bold'>
        Reset Password
      </a>
    </p>
    <p>If the button does not work, copy and paste this link into your browser:<br>
    <span style='color:#159140'>$resetUrl</span></p>
    <p>This link will expire in 1 hour.</p>
  ";

  $mail->send();

  echo json_encode(['ok'=>true]);

} catch (Exception $e) {
  echo json_encode(['ok'=>false, 'msg'=>'Mailer Error: '.$mail->ErrorInfo]);
}
