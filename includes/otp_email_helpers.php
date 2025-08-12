<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/db.php'; // Assuming db.php is in the same includes directory

function sendOtpEmail($email, $otp) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        //Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP for Account Verification';
        $mail->MessageID = '<' . uniqid() . '@serdihin.com>';
        $mail->addCustomHeader('In-Reply-To', '<' . uniqid() . '@serdihin.com>');
        $mail->addCustomHeader('References', '<' . uniqid() . '@serdihin.com>');
        $mail->Body    = '<div style="font-family: Arial, sans-serif; line-height: 1.5; color: #333;">
                            <h2 style="color: #1a1a1a;">Your One-Time Password (OTP)</h2>
                            <p>Please use the following OTP to verify your account. This OTP is valid for <strong>' . OTP_EXPIRY_MINUTES . ' minutes</strong>.</p>
                            <div style="background-color: #f0f8ff; border: 1px solid #cceeff; padding: 15px 25px; text-align: center; margin: 20px auto; max-width: 250px; border-radius: 8px;">
                                <strong style="font-size: 28px; color: #007bff; letter-spacing: 3px; display: block; margin: 0;">' . htmlspecialchars($otp) . '</strong>
                            </div>
                            <p>If you did not request this OTP, please ignore this email. Your account security is important to us.</p>
                            <p style="font-size: 0.85em; color: #666;">This is an automated message. Please do not reply directly to this email.</p>
                        </div>';
        $mail->AltBody = 'Your One-Time Password (OTP) is: ' . htmlspecialchars($otp) . '. This OTP is valid for ' . OTP_EXPIRY_MINUTES . ' minutes. If you did not request this OTP, please ignore this email. Your account security is important to us.';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function generateAndStoreOtp($user_id) {
    $db = new Database();
    $otp = rand(100000, 999999);
    $otp_expires_at = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));

    $db->query("INSERT INTO user_otps (user_id, otp, otp_expires_at) VALUES (?, ?, ?)", [$user_id, $otp, $otp_expires_at]);
    return ['otp' => $otp, 'expires_at' => $otp_expires_at];
}
