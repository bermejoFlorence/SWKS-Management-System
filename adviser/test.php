<?php
require '../phpmailer/src/PHPMailer.php';
require '../phpmailer/src/SMTP.php';
require '../phpmailer/src/Exception.php';

$mail = new PHPMailer\PHPMailer\PHPMailer();
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'joshua.lerin@cbsua.edu.ph';
    $mail->Password = 'your_app_password'; // <-- paste mo dito
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Test');
    $mail->addAddress('YOUR_EMAIL@domain.com', 'Test User'); // Palitan ng active email mo

    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body    = 'This is a test email from PHPMailer.';

    $mail->send();
    echo 'Test email sent! Check your inbox (or spam).';
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}
?>
