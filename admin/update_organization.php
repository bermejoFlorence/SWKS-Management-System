<?php
// update_organization.php
// Handles updating organization name/desc, creating/updating adviser user & details,
// and (optionally) emailing the adviser with an activation link.

include_once '../database/db_connection.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw on mysqli errors (caught by PHP)
ini_set('log_errors', 1);
ini_set('display_errors', 0);            // keep hidden in browser
error_reporting(E_ALL);

error_log("HIT update_organization.php " . date('c'));

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/* ---------- Read & validate input ---------- */
$org_id        = (int)($_POST['org_id'] ?? 0);
$org_name      = trim($_POST['org_name'] ?? '');
$org_desc      = trim($_POST['org_desc'] ?? '');
$adviser_name  = trim($_POST['adviser_name'] ?? '');
$adviser_email = trim($_POST['adviser_email'] ?? '');

// Institutional email check
if (!preg_match('/@cbsua\.edu\.ph$/i', $adviser_email)) {
    echo "<script>
        alert('Only institutional emails ending in @cbsua.edu.ph are allowed.');
        window.history.back();
    </script>";
    exit;
}

if (!($org_id > 0 && $org_name !== '' && $org_desc !== '' && $adviser_name !== '' && $adviser_email !== '')) {
    header("Location: org_details.php?org_id={$org_id}&status=error");
    exit;
}

try {
    /* ---------- Fetch existing org ---------- */
    $orgStmt = $conn->prepare("SELECT org_name, org_desc FROM organization WHERE org_id = ?");
    $orgStmt->bind_param("i", $org_id);
    $orgStmt->execute();
    $orgStmt->bind_result($existing_org_name, $existing_org_desc);
    $orgStmt->fetch();
    $orgStmt->close();

    /* ---------- Update org if changed ---------- */
    if ($existing_org_name !== $org_name || $existing_org_desc !== $org_desc) {
        $updateOrg = $conn->prepare("UPDATE organization SET org_name = ?, org_desc = ? WHERE org_id = ?");
        $updateOrg->bind_param("ssi", $org_name, $org_desc, $org_id);
        $updateOrg->execute();
        $updateOrg->close();
    }

    /* ---------- Find or create adviser `user` ---------- */
    // NOTE: `user` is quoted with backticks to avoid conflicts.
    $adviserQ = $conn->prepare("SELECT user_id, user_email FROM `user` WHERE org_id = ? AND user_role = 'adviser' LIMIT 1");
    $adviserQ->bind_param("i", $org_id);
    $adviserQ->execute();
    $adviserQ->store_result();

    $had_adviser_before = false;
    $adviser_user_id = null;
    $current_user_email = null;

    if ($adviserQ->num_rows > 0) {
        $had_adviser_before = true;
        $adviserQ->bind_result($adviser_user_id, $current_user_email);
        $adviserQ->fetch();
        $adviserQ->close();
    } else {
        $adviserQ->close();
        $createUser = $conn->prepare("INSERT INTO `user` (org_id, user_email, user_password, user_role, created_at) VALUES (?, ?, '', 'adviser', NOW())");
        $createUser->bind_param("is", $org_id, $adviser_email);
        $createUser->execute();
        $adviser_user_id = $createUser->insert_id;
        $createUser->close();
        $current_user_email = null;
    }

    /* ---------- Duplicate email guard (if adviser already existed) ---------- */
    $email_changed = ($current_user_email !== null && $adviser_email !== $current_user_email);
    if ($had_adviser_before) {
        $dupCheck = $conn->prepare("SELECT user_id FROM `user` WHERE user_email = ? AND user_id != ?");
        $dupCheck->bind_param("si", $adviser_email, $adviser_user_id);
        $dupCheck->execute();
        $dupCheck->store_result();
        if ($dupCheck->num_rows > 0) {
            $dupCheck->close();
            header("Location: org_details.php?org_id={$org_id}&duplicate_email=1");
            exit;
        }
        $dupCheck->close();

        if ($email_changed) {
            $updateEmail = $conn->prepare("UPDATE `user` SET user_email = ? WHERE user_id = ?");
            $updateEmail->bind_param("si", $adviser_email, $adviser_user_id);
            $updateEmail->execute();
            $updateEmail->close();
        }
    }

    /* ---------- Upsert adviser_details ---------- */
    $check = $conn->prepare("SELECT adviser_fname, adviser_email FROM adviser_details WHERE user_id = ?");
    $check->bind_param("i", $adviser_user_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->bind_result($ex_fname, $ex_email);
        $check->fetch();
        $check->close();

        if ($ex_fname !== $adviser_name || $ex_email !== $adviser_email) {
            $update = $conn->prepare("UPDATE adviser_details SET adviser_fname = ?, adviser_email = ? WHERE user_id = ?");
            $update->bind_param("ssi", $adviser_name, $adviser_email, $adviser_user_id);
            $update->execute();
            $update->close();
        }
    } else {
        $check->close();
        $insert = $conn->prepare("INSERT INTO adviser_details (user_id, adviser_fname, adviser_email) VALUES (?, ?, ?)");
        $insert->bind_param("iss", $adviser_user_id, $adviser_name, $adviser_email);
        $insert->execute();
        $insert->close();
    }

    /* ---------- Send email if new adviser or email changed ---------- */
    $shouldSend = (!$had_adviser_before) || $email_changed;

    if ($shouldSend && preg_match('/@cbsua\.edu\.ph$/i', $adviser_email)) {
        $p1 = __DIR__ . '/../phpmailer/src/PHPMailer.php';
        $p2 = __DIR__ . '/../phpmailer/src/SMTP.php';
        $p3 = __DIR__ . '/../phpmailer/src/Exception.php';

        if (!file_exists($p1) || !file_exists($p2) || !file_exists($p3)) {
            error_log("PHPMailer files missing: $p1 | $p2 | $p3");
        } else {
            require_once $p1;
            require_once $p2;
            require_once $p3;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'joshua.lerin@cbsua.edu.ph'; // Gmail address
                $mail->Password   = 'drdjfeavapsxuact';          // App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->SMTPDebug  = 0; // keep quiet in production
                $mail->CharSet    = 'UTF-8';

                $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Coordinator');
                $mail->addAddress($adviser_email, $adviser_name);

                $link = "https://swks-organization.com/activate.php?id={$adviser_user_id}";
                $mail->isHTML(true);
                $mail->Subject = 'SWKS Adviser Account Setup';
                $mail->Body    = "
                    <p>Hello <b>{$adviser_name}</b>,</p>
                    <p>You have been assigned as an adviser for an organization in the SWKS system.</p>
                    <p>Please click the button below to set your account password:</p>
                    <p>
                        <a href='{$link}' style='padding:10px 20px; background:#198754; color:#fff; border-radius:6px; text-decoration:none;'>Set Your Password</a>
                    </p>
                    <p>If you didn’t expect this, please ignore this email.</p>
                    <br><small>This is an automated message. Do not reply.</small>
                ";

                $mail->send();
            } catch (Exception $e) {
                error_log("PHPMailer Exception: " . $e->getMessage());
            }
        }
    }

    /* ---------- Success redirect ---------- */
    echo "<script>
        sessionStorage.setItem('orgEditSuccess', '1');
        window.location.href = 'org_details.php?org_id={$org_id}';
    </script>";
    exit;

} catch (Throwable $e) {
    // Any uncaught error -> log and redirect with error
    error_log("update_organization.php ERROR: " . $e->getMessage());
    header("Location: org_details.php?org_id={$org_id}&status=error");
    exit;
}
