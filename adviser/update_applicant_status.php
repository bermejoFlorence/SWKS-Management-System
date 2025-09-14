<?php
include '../database/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member_id = intval($_POST['member_id']);
    $status = $_POST['status'] == 'approved' ? 'approved' : 'rejected';

    // 1. Update applicant status
    $stmt = $conn->prepare("UPDATE member_details SET status=? WHERE member_id=?");
    $stmt->bind_param("si", $status, $member_id);
    $stmt->execute();

    // 2. Get applicant's email and name
    $stmt = $conn->prepare("SELECT email, full_name FROM member_details WHERE member_id=?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $applicant = $result->fetch_assoc();

    if ($applicant && $status === 'approved') {
        // 3. [ADDED] Ensure a user row exists for this member
        // Check if a user row already exists
        $stmt_check = $conn->prepare("SELECT user_id FROM user WHERE user_id = ?");
        $stmt_check->bind_param("i", $member_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            // Insert user row
            $user_email = $applicant['email'];
            $stmt_insert = $conn->prepare("INSERT INTO user (user_id, user_email, user_password) VALUES (?, ?, '')");
            $stmt_insert->bind_param("is", $member_id, $user_email);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
        $stmt_check->close();

        // 4. [ADDED] Link user_id to member_details (if not yet set)
        $stmt_update_fk = $conn->prepare("UPDATE member_details SET user_id = ? WHERE member_id = ?");
        $stmt_update_fk->bind_param("ii", $member_id, $member_id);
        $stmt_update_fk->execute();
        $stmt_update_fk->close();

        // 5. Load PHPMailer (tamang path: src, hindi scr)
        require_once '../phpmailer/src/PHPMailer.php';
        require_once '../phpmailer/src/SMTP.php';
        require_once '../phpmailer/src/Exception.php';

        // Instantiate with (true) para mag-throw ng error at makita mo agad kung may problema
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'joshua.lerin@cbsua.edu.ph';
            $mail->Password = 'drdj feav apsx uact'; // Your Gmail App Password
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // From/To
            $mail->setFrom('joshua.lerin@cbsua.edu.ph', 'SWKS Advisers');
            $mail->addAddress($applicant['email'], $applicant['full_name']);

            // Compose activation link
            $link = "localhost/swks/activate.php?id={$member_id}";

            // Email Subject & Body (HTML)
            $mail->isHTML(true);
            $mail->Subject = 'Membership Application Approved';
            $mail->Body = "
                <p>Dear <b>{$applicant['full_name']}</b>,</p>
                <p>
                    We are pleased to inform you that your application for membership with our organization has been <b>approved</b> by your adviser.<br>
                    You may now create your password and activate your account by clicking the link below:
                </p>
                <p style='margin:18px 0;'>
                  <a href='$link'
                     style='padding:12px 22px; border-radius:6px; background:#043c00; color:#fff; text-decoration:none; font-weight:bold;'>
                     Set Your Password
                  </a>
                </p>
                <p>
                  If the button does not work, you may copy and paste this link into your browser:<br>
                  <span style='color:#043c00;'>$link</span>
                </p>
                <p>
                  If you did not apply for membership or you believe this message was sent to you in error, please disregard this email.<br>
                  <br>
                  Best regards,<br>
                  <b>SWKS Membership Committee</b>
                </p>
                <small style='color:gray;'>This is an automated message. Please do not reply to this email.</small>
            ";

            $mail->send();
            // For debugging: uncomment to see if sent
            // echo "SUCCESS: Email sent!";
        } catch (Exception $e) {
            // Ipakita ang error message kapag nag-debug ka
            echo "Mailer Error: {$mail->ErrorInfo}";
        }
    }
    echo 'ok';
}
?>
