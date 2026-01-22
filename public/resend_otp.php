<?php
    use PHPMailer\PHPMailer\PHPMailer;

    require_once "../config/db.php";
    require '../vendor/autoload.php';

    $token = trim((string)($_GET['token'] ?? ''));
    if($token === ''){
        header("Location: register.php");
        exit;
    }

    $tokenStored = 'otp:' . hash('sha256', $token);

    $stmt = $pdo->prepare("SELECT id, email, status, email_verified, must_change_password FROM users WHERE verify_token=:token LIMIT 1");
    $stmt->execute(['token'=>$tokenStored]);
    $user = $stmt->fetch();
    if(!$user || (string)$user['status'] !== 'inactive' || (int)$user['email_verified'] !== 0 || (int)($user['must_change_password'] ?? 0) !== 0){
        header("Location: register.php");
        exit;
    }

    $uid = (int)$user['id'];

    $otpPlain = (string)random_int(100000,999999);
    $otpHash = password_hash($otpPlain, PASSWORD_DEFAULT);
    $newToken = bin2hex(random_bytes(32));
    $newTokenStored = 'otp:' . hash('sha256', $newToken);

    $email = (string)$user['email'];

    $error = "";

    $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
    $smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
    $smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
    $smtpFrom = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: $smtpUser;
    $smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Organization';
    $smtpEncryption = strtolower((string)($_ENV['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls'));

    if ($smtpUser === '' || $smtpPass === '') {
        $error = "Email server not configured. Please contact admin.";
    } else {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = str_replace(' ', '', trim($smtpPass));
            $mail->SMTPSecure = $smtpEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            $mail->Timeout = 10;

            $mail->setFrom($smtpFrom, $smtpFromName);
            $mail->addAddress($email);
            $mail->Subject = "Resend OTP";
            $mail->Body = "Your new OTP is " . $otpPlain . " (valid for 5 minutes)";
            $mail->send();

            $pdo->prepare("UPDATE users SET otp_code=:otp, otp_expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE), verify_token=:newToken WHERE id=:id")->execute(['otp'=>$otpHash,'newToken'=>$newTokenStored,'id'=>$uid]);

            header("Location: otp_verification.php?token=" . urlencode($newToken));
            exit;
        } catch (Throwable $e) {
            $error = "Email server not responding. Please try again.";
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resend OTP</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
            <div>
                <a class="btn btn-outline-primary" href="otp_verification.php?token=<?= htmlspecialchars(urlencode($token)) ?>">Back to OTP</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
