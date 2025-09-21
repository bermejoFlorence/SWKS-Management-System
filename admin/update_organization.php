<?php
// update_organization.php (Host-safe, with richer send rules + logging)
include_once '../database/db_connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('log_errors', 1);
ini_set('display_errors', 0);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* ---------- Inputs ---------- */
$org_id        = (int)($_POST['org_id'] ?? 0);
$org_name      = trim($_POST['org_name'] ?? '');
$org_desc      = trim($_POST['org_desc'] ?? '');
$adviser_name  = trim($_POST['adviser_name'] ?? '');
$adviser_email = trim($_POST['adviser_email'] ?? '');

/* ---------- Validation ---------- */
// Institutional email only
$instEmail = (bool)preg_match('/@cbsua\.edu\.ph$/i', $adviser_email);
if (!$instEmail) {
    echo "<script>alert('Only institutional emails ending in @cbsua.edu.ph are allowed.');history.back();</script>";
    exit;
}

if (!($org_id > 0 && $org_name !== '' && $org_desc !== '' && $adviser_name !== '' && $adviser_email !== '')) {
    header("Location: org_details.php?org_id={$org_id}&status=error");
    exit;
}

try {
    /* ---------- 1) Load current org ---------- */
    $orgStmt = $conn->prepare("SELECT org_name, org_desc FROM organization WHERE org_id = ?");
    $orgStmt->bind_param("i", $org_id);
    $orgStmt->execute();
    $orgStmt->bind_result($existing_org_name, $existing_org_desc);
    $orgStmt->fetch();
    $orgStmt->close();

    $org_changed =
        ($existing_org_name !== null && $existing_org_name !== $org_name) ||
        ($existing_org_desc !== null && $existing_org_desc !== $org_desc);

    /* ---------- 2) Update org if changed ---------- */
    if ($org_changed) {
        $u = $conn->prepare("UPDATE organization SET org_name = ?, org_desc = ? WHERE org_id = ?");
        $u->bind_param("ssi", $org_name, $org_desc, $org_id);
        $u->execute();
        $u->close();
    }

    /* ---------- 3) Find/create adviser user (NO get_result) ---------- */
    $q = $conn->prepare("SELECT user_id, user_email FROM `user` WHERE org_id = ? AND user_role = 'adviser' LIMIT 1");
    $q->bind_param("i", $org_id);
    $q->execute();
    $q->store_result();

    $had_adviser = $q->num_rows > 0;
    $adviser_user_id = null;
    $current_email = null;

    if ($had_adviser) {
        $q->bind_result($adviser_user_id, $current_email);
        $q->fetch();
        $q->close();
    } else {
        $q->close();
        $ins = $conn->prepare("INSERT INTO `user` (org_id, user_email, user_password, user_role, created_at) VALUES (?, ?, '', 'adviser', NOW())");
        $ins->bind_param("is", $org_id, $adviser_email);
        $ins->execute();
        $adviser_user_id = $ins->insert_id;
        $ins->close();
        $current_email = null;
    }

    /* ---------- 4) Email duplication guard + sync user_email ---------- */
    $email_changed = ($current_email !== null && $adviser_email !== $current_email);

    if ($had_adviser) {
        $dup = $conn->prepare("SELECT user_id FROM `user` WHERE user_email = ? AND user_id != ?");
        $dup->bind_param("si", $adviser_email, $adviser_user_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $dup->close();
            header("Location: org_details.php?org_id={$org_id}&duplicate_email=1");
            exit;
        }
        $dup->close();

        if ($email_changed) {
            $ue = $conn->prepare("UPDATE `user` SET user_email = ? WHERE user_id = ?");
            $ue->bind_param("si", $adviser_email, $adviser_user_id);
            $ue->execute();
            $ue->close();
        }
    }

    /* ---------- 5) Upsert adviser_details ---------- */
    $chk = $conn->prepare("SELECT adviser_fname, adviser_email FROM adviser_details WHERE user_id = ?");
    $chk->bind_param("i", $adviser_user_id);
    $chk->execute();
    $chk->store_result();

    $name_changed = false;

    if ($chk->num_rows > 0) {
        $chk->bind_result($ex_fn, $ex_em);
        $chk->fetch();
        $chk->close();

        if ($ex_fn !== $adviser_name || $ex_em !== $adviser_email) {
            $up = $conn->prepare("UPDATE adviser_details SET adviser_fname = ?, adviser_email = ? WHERE user_id = ?");
            $up->bind_param("ssi", $adviser_name, $adviser_email, $adviser_user_id);
            $up->execute();
            $up->close();
            $name_changed = true;
        }
    } else {
        $chk->close();
        $ins2 = $conn->prepare("INSERT INTO adviser_details (user_id, adviser_fname, adviser_email) VALUES (?, ?, ?)");
        $ins2->bind_param("iss", $adviser_user_id, $adviser_name, $adviser_email);
        $ins2->execute();
        $ins2->close();
        $name_changed = true; // first time create → treat as change
    }

    /* ---------- 6) Decide if we should send email ---------- */
    $shouldSend = (!$had_adviser) || $email_changed || $name_changed || $org_changed;

    // Log the decision so you can see in Hostinger logs why it did/didn't send
    error_log(sprintf(
        "SWKS update_organization: org_id=%d shouldSend=%s | flags {new=%s, email_changed=%s, name_changed=%s, org_changed=%s} | to=%s",
        $org_id,
        $shouldSend ? 'YES' : 'NO',
        !$had_adviser ? 'YES' : 'NO',
        $email_changed ? 'YES' : 'NO',
        $name_changed ? 'YES' : 'NO',
        $org_changed ? 'YES' : 'NO',
        $adviser_email
    ));

    /* ---------- 7) Send email (with logging) ---------- */
    if ($shouldSend && $instEmail) {
        $p1 = __DIR__ . '/../phpmailer/src/PHPMailer.php';
        $p2 = __DIR__ . '/../phpmailer/src/SMTP.php';
        $p3 = __DIR__ . '/../phpmailer/src/Exception.php';

        if (file_exists($p1) && file_exists($p2) && file_exists($p3)) {
            require_once $p1;
            require_once $p2;
            require_once $p3;

            $mail = new PHPMailer(true);
            $sentOk = false;

            try {
                $mail->isSMTP();
                // OPTION A: Gmail
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'joshua.lerin@cbsua.edu.ph';     // Gmail address
                $mail->Password   = 'drdjfeavapsxuact';              // App Password (no spaces)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // OPTION B (Hostinger domain mailbox) — switch to this if mas ok deliverability:
                // $mail->Host       = 'smtp.hostinger.com';
                // $mail->SMTPAuth   = true;
                // $mail->Username   = 'you@swks-organization.com';
                // $mail->Password   = 'YOUR_HOSTINGER_MAIL_PASSWORD';
                // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                // $mail->Port       = 587;

                $mail->CharSet    = 'UTF-8';
                $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Coordinator');
                $mail->addAddress($adviser_email, $adviser_name);

                $link = "https://swks-organization.com/activate.php?id={$adviser_user_id}";

                $mail->isHTML(true);
                $mail->Subject = 'SWKS Adviser Account Setup';
                $mail->Body = "
                    <p>Hello <b>" . htmlspecialchars($adviser_name, ENT_QUOTES, 'UTF-8') . "</b>,</p>
                    <p>You have been assigned as an adviser for <b>" . htmlspecialchars($org_name, ENT_QUOTES, 'UTF-8') . "</b> in the SWKS system.</p>
                    <p>Please click the button below to set your account password:</p>
                    <p>
                        <a href='{$link}' style='padding:10px 20px; background:#198754; color:#fff; border-radius:6px; text-decoration:none;'>Set Your Password</a>
                    </p>
                    <p>If you didn’t expect this, please ignore this email.</p>
                    <br><small>This is an automated message. Do not reply.</small>
                ";

                $sentOk = $mail->send();
                if (!$sentOk) {
                    error_log('PHPMailer send() returned false: ' . $mail->ErrorInfo);
                } else {
                    error_log('SWKS mail sent OK to ' . $adviser_email);
                }
            } catch (Exception $e) {
                error_log("PHPMailer Exception: " . $e->getMessage());
            }
        } else {
            error_log("PHPMailer files missing: $p1 | $p2 | $p3");
        }
    }

    /* ---------- 8) Done ---------- */
    echo "<script>sessionStorage.setItem('orgEditSuccess','1'); location.href='org_details.php?org_id={$org_id}';</script>";
    exit;

} catch (Throwable $e) {
    error_log("update_organization.php ERROR: " . $e->getMessage());
    header("Location: org_details.php?org_id={$org_id}&status=error");
    exit;
}
