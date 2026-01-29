<?php
    use PHPMailer\PHPMailer\PHPMailer;

    function send_email(string $to, string $subject, string $body): bool {
        $mail = new PHPMailer(true);
    
        $host = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $port = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
        $username = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
        $password = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
        $fromEmail = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: $username;
        $fromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Organization';
    
        if ($username === '' || $password === '' || $fromEmail === '') {
            return false;
        }
    
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $port;
    
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
    
            return $mail->send();
        } catch (Throwable $e) {
            return false;
        }
    }
?>