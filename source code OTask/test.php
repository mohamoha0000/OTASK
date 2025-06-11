<?php
// استدعاء ملفات المكتبة
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// إعدادات Brevo SMTP
$smtp_server = "smtp-relay.brevo.com";
$smtp_port = 587;
$smtp_login = "8f5763001@smtp-brevo.com";
/*xsmtpsib-5a1c7cf8daaa95e8809c5e3bcc62f2e9cd54244a14fbac10de9c10cd5166758a-A19KHYtEDfhrbSxd*/
$encoded = "eHNtdHBzaWItNWExYzdjZjhkYWFhOTVlODgwOWM1ZTNiY2M2MmYyZTljZDU0MjQ0YTE0ZmJhYzEwZGU5YzEwY2Q1MTY2NzU4YS1BMTlLSFl0RURmaHJiU3hk";
$smtp_password = base64_decode($encoded);

$sender_email = "moham3iof@gmail.com";
$receiver_email = "mohamedelmaeyouf@gmail.com";
$subject = "رسالة تجريبية من PHP عبر Brevo";
$body = "مرحبًا! هذه رسالة تجريبية أُرسلت عبر SMTP باستخدام PHP و Brevo.hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = $smtp_server;
    $mail->SMTPAuth = true;
    $mail->Username = $smtp_login;
    $mail->Password = $smtp_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = $smtp_port;

    $mail->setFrom($sender_email);
    $mail->addAddress($receiver_email);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->CharSet = 'UTF-8';
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    $mail->send();
    echo "✅ تم إرسال البريد الإلكتروني بنجاح.";
} catch (Exception $e) {
    echo "❌ حدث خطأ أثناء الإرسال: {$mail->ErrorInfo}";
}
