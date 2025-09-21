<?php
// update_applicant_status.php (host-safe)
include '../database/db_connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('log_errors', 1);
ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); exit('Method Not Allowed');
}

$member_id = (int)($_POST['member_id'] ?? 0);
$status    = (($_POST['status'] ?? '') === 'approved') ? 'approved' : 'rejected';

// --- 1) Update applicant status
$stmt = $conn->prepare("UPDATE member_details SET status=? WHERE member_id=?");
$stmt->bind_param("si", $status, $member_id);
$stmt->execute();
$stmt->close();

// --- 2) Get applicant's email + name (NO get_result)
$email = $full_name = null;
$stmt  = $conn->prepare("SELECT email, full_name FROM member_details WHERE member_id=? LIMIT 1");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$stmt->bind_result($email, $full_name);
$hasApplicant = $stmt->fetch();
$stmt->close();

if ($hasApplicant && $status === 'approved') {

  // --- 3) Ensure a user row exists for this member (user_id == member_id)
  $stmt_check = $conn->prepare("SELECT user_id FROM `user` WHERE user_id=? LIMIT 1");
  $stmt_check->bind_param("i", $member_id);
  $stmt_check->execute();
  $stmt_check->store_result();
  $hasUser = ($stmt_check->num_rows > 0);
  $stmt_check->close();

  if (!$hasUser) {
    // NOTE: If your `user` table requires other NOT NULL columns (e.g., user_role/org_id),
    // add them here accordingly.
    $stmt_ins = $conn->prepare("INSERT INTO `user` (user_id, user_email, user_password) VALUES (?, ?, '')");
    $stmt_ins->bind_param("is", $member_id, $email);
    $stmt_ins->execute();
    $stmt_ins->close();
  }

  // --- 4) Link user_id back to member_details (if not yet set)
  $stmt_fk = $conn->prepare("UPDATE member_details SET user_id=? WHERE member_id=?");
  $stmt_fk->bind_param("ii", $member_id, $member_id);
  $stmt_fk->execute();
  $stmt_fk->close();

  // --- 5) Send activation email via PHPMailer
  // Path is relative to this file's folder; your PHPMailer is /public_html/PHPMailer/src
  $p1 = __DIR__ . '/../PHPMailer/src/PHPMailer.php';
  $p2 = __DIR__ . '/../PHPMailer/src/SMTP.php';
  $p3 = __DIR__ . '/../PHPMailer/src/Exception.php';

  if (file_exists($p1) && file_exists($p2) && file_exists($p3)) {
    require_once $p1; require_once $p2; require_once $p3;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
      $mail->isSMTP();

      // OPTION A: Gmail (same as your smtp_test.php)
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'joshua.lerin@cbsua.edu.ph';
      $mail->Password   = 'drdjfeavapsxuact'; // Gmail App Password (no spaces)
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->Port       = 587;

      // OPTION B (Hostinger mailbox) — comment A, uncomment B if you switch:
      // $mail->Host       = 'smtp.hostinger.com';
      // $mail->SMTPAuth   = true;
      // $mail->Username   = 'you@swks-organization.com';
      // $mail->Password   = 'HOSTINGER_MAIL_PASSWORD';
      // $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      // $mail->Port       = 587;
      // $mail->setFrom('you@swks-organization.com', 'SWKS Membership');

      $mail->CharSet    = 'UTF-8';
      $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Membership');
      $mail->addAddress($email, $full_name);

      // Use your real domain, not localhost
      $link = "https://swks-organization.com/activate.php?id={$member_id}";

      $safeName = htmlspecialchars((string)$full_name, ENT_QUOTES, 'UTF-8');

      $mail->isHTML(true);
      $mail->Subject = 'Membership Application Approved';
      $mail->Body = "
        <p>Dear <b>{$safeName}</b>,</p>
        <p>
          We are pleased to inform you that your application for membership has been <b>approved</b>.<br>
          You may now create your password and activate your account by clicking the link below:
        </p>
        <p style='margin:18px 0;'>
          <a href='{$link}' style='padding:12px 22px; border-radius:6px; background:#043c00; color:#fff; text-decoration:none; font-weight:bold;'>
            Set Your Password
          </a>
        </p>
        <p>If the button does not work, copy and paste this link into your browser:<br>
          <span style='color:#043c00;'>{$link}</span>
        </p>
        <p>Best regards,<br><b>SWKS Membership Committee</b></p>
        <small style='color:gray;'>This is an automated message. Please do not reply.</small>
      ";

      // (Optional) Debug to error_log while testing; set back to 0 in production.
      // $mail->SMTPDebug  = 2;
      // $mail->Debugoutput = function($str,$level){ error_log("PHPMailer[$level] $str"); };

      $mail->send();
    } catch (Throwable $e) {
      error_log("update_applicant_status mail error: ".$e->getMessage());
      // You can echo nothing to keep API response clean, logs will have details.
    }
  } else {
    error_log("PHPMailer files missing in update_applicant_status: $p1 | $p2 | $p3");
  }
}

echo 'ok';
