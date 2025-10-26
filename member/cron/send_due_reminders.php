<?php
// /member/cron/send_due_reminders.php
// Runs safely via CLI or HTTP. Sends 1-day-before-due reminders.

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../database/db_connection.php';

// ---- PHPMailer: choose one of the two require styles ----
// (A) If using Composer:
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
  require_once $composerAutoload;
  $useComposer = true;
} else {
  // (B) If you included PHPMailer manually, adjust these paths:
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
  $useComposer = false;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// (Optional) Secure this when calling via HTTP:
if (php_sapi_name() !== 'cli') {
  $token = $_GET['token'] ?? '';
  $EXPECTED = 'CHANGE_ME_LONG_RANDOM_TOKEN';
  if ($EXPECTED !== 'CHANGE_ME_LONG_RANDOM_TOKEN' && $token !== $EXPECTED) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

// Compute “tomorrow” in PH time (YYYY-MM-DD)
$tomorrow = (new DateTime('tomorrow', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

// Find requests due tomorrow, not yet reminded, and still active
$sql = "
SELECT
  br.request_id,
  br.expected_return_date,
  u.user_email,
  COALESCE(md.full_name, u.user_email) AS full_name,
  GROUP_CONCAT(DISTINCT ii.name ORDER BY ii.name SEPARATOR ', ') AS item_names
FROM borrow_requests br
JOIN user u                  ON u.user_id = br.user_id
LEFT JOIN member_details md  ON md.user_id = u.user_id
JOIN borrow_request_items bri ON bri.request_id = br.request_id
JOIN inventory_items ii       ON ii.item_id = bri.item_id
WHERE br.expected_return_date = ?
  AND br.due_reminder_sent = 0
  AND LOWER(br.status) = 'approved'
GROUP BY br.request_id, br.expected_return_date, u.user_email, full_name
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $tomorrow);
$stmt->execute();
$res = $stmt->get_result();

$toSend = [];
while ($row = $res->fetch_assoc()) $toSend[] = $row;
$stmt->close();

if (!$toSend) {
  echo "[info] No reminders to send for {$tomorrow}\n";
  exit(0);
}

// Setup PHPMailer (Gmail example; change to your SMTP)
$mail = new PHPMailer(true);
try {
$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = 'smtp.gmail.com';
$mail->SMTPAuth   = true;
$mail->Username   = 'joshua.lerin@cbsua.edu.ph';       // <-- your Gmail
$mail->Password   = 'drdj feav apsx uact';  // <-- app password
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // try 465+ENCRYPTION_SMTPS if 587 is blocked
$mail->Port       = 587;                            // or 465 with ENCRYPTION_SMTPS
$mail->CharSet    = 'UTF-8';
$mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Borrowing');


} catch (Exception $e) {
  error_log('Mailer init error: ' . $e->getMessage());
  exit(1);
}

// Send per request, then flag as sent
$sent = 0; $fail = 0;
foreach ($toSend as $r) {
  $to   = $r['user_email'];
  $name = $r['full_name'] ?: 'Member';
  $items = $r['item_names'] ?: 'your borrowed item(s)';
  $dueText = date('F j, Y', strtotime($r['expected_return_date']));
  $subject = "Reminder: return {$items} tomorrow (Due: {$dueText})";
  $body = "Hi {$name},\n\nThis is a friendly reminder that your borrowed item(s): {$items}\nare due tomorrow ({$dueText}). Please return them on or before the due date\nin the same working condition.\n\nThank you!\n— SWKS Borrowing System\n";

  try {
    $mail->clearAddresses();
    $mail->addAddress($to, $name);
    $mail->Subject = $subject;
    $mail->Body    = $body;     // plain text
    $mail->AltBody = $body;

    $mail->send();

    // mark as reminded
    $upd = $conn->prepare("UPDATE borrow_requests SET due_reminder_sent=1, due_reminder_sent_at=NOW() WHERE request_id=?");
    $upd->bind_param('i', $r['request_id']);
    $upd->execute();
    $upd->close();

    $sent++;
  } catch (Exception $e) {
    $fail++;
    error_log("Send fail for Req#{$r['request_id']} to {$to}: " . $mail->ErrorInfo);
  }
}

echo "[done] sent={$sent} fail={$fail} for {$tomorrow}\n";
