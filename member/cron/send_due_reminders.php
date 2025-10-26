<?php
// /member/cron/send_due_reminders.php
// Sends 1-day-before due reminders (and Friday → also Monday).
// Safe to run by CLI (cron). If called via HTTP, requires a token (see guard below).

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../database/db_connection.php';

// ---------- PHPMailer includes ----------
// (A) Composer autoload if available
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
  require_once $composerAutoload;
} else {
  // (B) Manual include (adjust paths if your PHPMailer folder is elsewhere)
  require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
  require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
  require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ---------- Optional HTTP guard ----------
// Keep this if you ever want to trigger via URL (e.g., for debugging).
// If you will ONLY use cron/CLI, you can replace this whole block with:
//   if (php_sapi_name() !== 'cli') { http_response_code(403); exit('Forbidden'); }
if (php_sapi_name() !== 'cli') {
  $token = $_GET['token'] ?? '';
  $EXPECTED = 'CHANGE_ME_LONG_RANDOM_TOKEN'; // <-- set to a long random string
  if ($EXPECTED !== 'CHANGE_ME_LONG_RANDOM_TOKEN' && $token !== $EXPECTED) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
}

// ---------- Build candidate dates ----------
// Main target is "tomorrow" (PH). If today is Friday, also include "+3 days" (Monday)
// because your cron runs only on weekdays.
$tz = new DateTimeZone('Asia/Manila');
$today = new DateTime('today', $tz);
$tomorrow = (clone $today)->modify('+1 day')->format('Y-m-d');

$datesToCheck = [$tomorrow];
$isFriday = ($today->format('N') == 5); // 1=Mon ... 7=Sun
if ($isFriday) {
  $monday = (clone $today)->modify('+3 day')->format('Y-m-d');
  $datesToCheck[] = $monday;
}

// ---------- Query: due tomorrow (and Monday on Fridays), approved, not yet reminded ----------
$sql = "
SELECT
  br.request_id,
  br.expected_return_date,
  u.user_email,
  COALESCE(md.full_name, u.user_email) AS full_name,
  GROUP_CONCAT(DISTINCT ii.name ORDER BY ii.name SEPARATOR ', ') AS item_names
FROM borrow_requests br
JOIN user u                   ON u.user_id = br.user_id
LEFT JOIN member_details md   ON md.user_id = u.user_id
JOIN borrow_request_items bri ON bri.request_id = br.request_id
JOIN inventory_items ii       ON ii.item_id = bri.item_id
WHERE br.expected_return_date = ?
  AND br.due_reminder_sent = 0
  AND LOWER(br.status) = 'approved'
GROUP BY br.request_id, br.expected_return_date, u.user_email, full_name
";

$toSend = [];
foreach ($datesToCheck as $d) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    error_log('Prepare failed: ' . $conn->error);
    continue;
  }
  $stmt->bind_param('s', $d);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    // de-dup by request_id just in case
    $toSend[$row['request_id']] = $row;
  }
  $stmt->close();
}

if (!$toSend) {
  echo "[info] No reminders to send for " . implode(',', $datesToCheck) . "\n";
  exit(0);
}

// ---------- PHPMailer SMTP config ----------
try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();

  // === Gmail example (use App Password) ===
  $mail->Host       = 'smtp.gmail.com';
  $mail->SMTPAuth   = true;
  $mail->Username   = 'joshua.lerin@cbsua.edu.ph';     // <-- your Gmail/Workspace address
  $mail->Password   = 'YOUR_16_CHAR_APP_PASSWORD';     // <-- replace with your App Password
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;  // or ENCRYPTION_SMTPS with Port 465
  $mail->Port       = 587;                             // or 465 with ENCRYPTION_SMTPS

  // Sender must match / align with your SMTP identity for best deliverability
  $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Borrowing');
  $mail->CharSet    = 'UTF-8';

  // Optional while testing:
  // $mail->SMTPDebug  = 2;
  // $mail->Debugoutput = 'error_log';

} catch (Exception $e) {
  error_log('Mailer init error: ' . $e->getMessage());
  exit(1);
}

// ---------- Send emails & flag rows ----------
$sent = 0; $fail = 0;
foreach ($toSend as $r) {
  $to     = $r['user_email'];
  $name   = $r['full_name'] ?: 'Member';
  $items  = $r['item_names'] ?: 'your borrowed item(s)';
  $dueYmd = $r['expected_return_date'];
  $dueText = date('F j, Y', strtotime($dueYmd));

  // Subject/body
  $subject = "Reminder: return {$items} tomorrow (Due: {$dueText})";
  $body = "Hi {$name},\n\n"
        . "This is a friendly reminder that your borrowed item(s): {$items}\n"
        . "are due tomorrow ({$dueText}). Please return them on or before the due date\n"
        . "in the same working condition.\n\n"
        . "Thank you!\n— SWKS Borrowing System\n";

  try {
    $mail->clearAddresses();
    $mail->addAddress($to, $name);
    $mail->Subject = $subject;
    $mail->Body    = $body; // plain text
    $mail->AltBody = $body;

    $mail->send();

    // Mark as reminded
    $upd = $conn->prepare("UPDATE borrow_requests SET due_reminder_sent = 1, due_reminder_sent_at = NOW() WHERE request_id = ?");
    $upd->bind_param('i', $r['request_id']);
    $upd->execute();
    $upd->close();

    $sent++;
  } catch (Exception $e) {
    $fail++;
    error_log("Send fail for Req#{$r['request_id']} to {$to}: " . $mail->ErrorInfo);
  }
}

// Summary for cron logs
echo "[done] sent={$sent} fail={$fail} for " . implode(',', $datesToCheck) . "\n";
