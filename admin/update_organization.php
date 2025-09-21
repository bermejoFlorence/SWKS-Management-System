<?php
include_once '../database/db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $org_id = intval($_POST['org_id']);
    $org_name = trim($_POST['org_name'] ?? '');
    $org_desc = trim($_POST['org_desc'] ?? '');
    $adviser_name = trim($_POST['adviser_name'] ?? '');
    $adviser_email = trim($_POST['adviser_email'] ?? '');

    // ✅ Institutional email validation (applies to all cases)
    if (!str_ends_with($adviser_email, '@cbsua.edu.ph')) {
        echo "<script>
            alert('Only institutional emails ending in @cbsua.edu.ph are allowed.');
            window.history.back();
        </script>";
        exit;
    }

    if ($org_id > 0 && $org_name && $org_desc && $adviser_email && $adviser_name) {

        // Get existing organization data
        $orgStmt = $conn->prepare("SELECT org_name, org_desc FROM organization WHERE org_id = ?");
        $orgStmt->bind_param("i", $org_id);
        $orgStmt->execute();
        $orgStmt->bind_result($existing_org_name, $existing_org_desc);
        $orgStmt->fetch();
        $orgStmt->close();

        // Update org if changed
        if ($org_name !== $existing_org_name || $org_desc !== $existing_org_desc) {
            $updateOrg = $conn->prepare("UPDATE organization SET org_name = ?, org_desc = ? WHERE org_id = ?");
            $updateOrg->bind_param("ssi", $org_name, $org_desc, $org_id);
            $updateOrg->execute();
            $updateOrg->close();
        }

        // Check for existing adviser user
        $adviserQ = $conn->prepare("SELECT user_id, user_email FROM user WHERE org_id = ? AND user_role = 'adviser' LIMIT 1");
        $adviserQ->bind_param("i", $org_id);
        $adviserQ->execute();
        $adviserRes = $adviserQ->get_result();

        if ($adviserRes->num_rows === 0) {
            // No adviser user yet — create one
            $createUser = $conn->prepare("INSERT INTO user (org_id, user_email, user_password, user_role, created_at) VALUES (?, ?, '', 'adviser', NOW())");
            $createUser->bind_param("is", $org_id, $adviser_email);
            $createUser->execute();
            $adviser_user_id = $createUser->insert_id;
            $createUser->close();

            $email_changed = true;
        } else {
            $userRow = $adviserRes->fetch_assoc();
            $adviser_user_id = $userRow['user_id'];
            $current_user_email = $userRow['user_email'];

            // Determine if adviser email changed
            $email_changed = ($adviser_email !== $current_user_email);

            // Prevent using an email that's already used by another user
            $dupCheck = $conn->prepare("SELECT user_id FROM user WHERE user_email = ? AND user_id != ?");
            $dupCheck->bind_param("si", $adviser_email, $adviser_user_id);
            $dupCheck->execute();
            $dupResult = $dupCheck->get_result();

            if ($dupResult->num_rows > 0) {
                $dupCheck->close();
                header("Location: org_details.php?org_id={$org_id}&duplicate_email=1");
                exit;
            }
            $dupCheck->close();


            // Optional: sync user_email if changed
            if ($email_changed) {
                $updateEmail = $conn->prepare("UPDATE user SET user_email = ? WHERE user_id = ?");
                $updateEmail->bind_param("si", $adviser_email, $adviser_user_id);
                $updateEmail->execute();
                $updateEmail->close();
            }
        }

        // Check if adviser_details entry exists
        $check = $conn->prepare("SELECT adviser_fname, adviser_email FROM adviser_details WHERE user_id = ?");
        $check->bind_param("i", $adviser_user_id);
        $check->execute();
        $result = $check->get_result();
        $existing = $result->fetch_assoc();
        $check->close();

        $name_changed = false;

        if ($existing) {
            if ($existing['adviser_fname'] !== $adviser_name || $existing['adviser_email'] !== $adviser_email) {
                $update = $conn->prepare("UPDATE adviser_details SET adviser_fname = ?, adviser_email = ? WHERE user_id = ?");
                $update->bind_param("ssi", $adviser_name, $adviser_email, $adviser_user_id);
                $update->execute();
                $update->close();
                $name_changed = true;
            }
        } else {
            $insert = $conn->prepare("INSERT INTO adviser_details (user_id, adviser_fname, adviser_email) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $adviser_user_id, $adviser_name, $adviser_email);
            $insert->execute();
            $insert->close();
            $name_changed = true;
        }

        // Send email only if email changed
        if ($email_changed && str_ends_with($adviser_email, '@cbsua.edu.ph')) {
            require_once '../phpmailer/src/PHPMailer.php';
            require_once '../phpmailer/src/SMTP.php';
            require_once '../phpmailer/src/Exception.php';

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'joshua.lerin@cbsua.edu.ph'; // Replace
                $mail->Password = 'drdj feav apsx uact'; // Replace with Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Coordinator');
                $mail->addAddress($adviser_email, $adviser_name);

                $link = "localhost/swks/activate.php?id={$adviser_user_id}";

                $mail->isHTML(true);
                $mail->Subject = 'SWKS Adviser Account Setup';
                $mail->Body = "
                    <p>Hello <b>{$adviser_name}</b>,</p>
                    <p>You have been assigned as an adviser for an organization in the SWKS system.</p>
                    <p>Please click the button below to set your account password:</p>
                    <p>
                        <a href='{$link}' style='padding:10px 20px; background:#198754; color:white; border-radius:6px; text-decoration:none;'>Set Your Password</a>
                    </p>
                    <p>If you didn’t expect this, please ignore this email.</p>
                    <br><small>This is an automated message. Do not reply.</small>
                ";
                $mail->send();
            } catch (Exception $e) {
                error_log("PHPMailer Error: " . $mail->ErrorInfo);
            }
        }

        echo "<script>
    sessionStorage.setItem('orgEditSuccess', '1');
    window.location.href = 'org_details.php?org_id={$org_id}';
</script>";
exit;

    }

    header("Location: org_details.php?org_id=$org_id&status=error");
    exit;
}
?>
