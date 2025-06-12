<?php
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;

    public function __construct($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $from_email) {
        $this->mail = new PHPMailer(true);

        try {
            $this->mail->isSMTP();
            $this->mail->Host = $smtp_host;
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $smtp_user;
            $this->mail->Password = $smtp_pass;
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $smtp_port;
            $this->mail->setFrom($from_email);
            $this->mail->CharSet = 'UTF-8';
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("فشل في إعداد البريد: " . $e->getMessage());
        }
    }

    public function send($to, $subject, $body) {
        try {
            $this->mail->clearAllRecipients(); // في حال أُعيد استخدام الكائن
            $this->mail->addAddress($to);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            throw new Exception("فشل الإرسال: " . $this->mail->ErrorInfo);
        }
    }
}
