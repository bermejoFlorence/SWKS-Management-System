<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';
require __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
  $mail->isSMTP();
  $mail->Host = 'smtp.gmail.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'joshua.lerin@cbsua.edu.ph';
  $mail->Password = 'drdjfeavapsxuact'; // Gmail App Password (walang spaces)
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;

  $mail->SMTPDebug = 2;
  $mail->Debugoutput = 'html';

  $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SMTP Test');
  $mail->addAddress('joshua.lerin@cbsua.edu.ph');
  $mail->Subject = 'SMTP Test';
  $mail->Body = 'This is a test email via Gmail SMTP.';
  $mail->isHTML(true);

  echo $mail->send() ? "OK: test mail sent." : "send() false: ".$mail->ErrorInfo;
} catch (Exception $e) {
  echo "Exception: ".$e->getMessage();
}
