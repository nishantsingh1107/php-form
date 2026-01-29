<?php
    require_once __DIR__ . '/../helpers/_bootstrap.php';
    $input = json_decode(file_get_contents("php://input"), true);
    $token = trim((string)($input['token'] ?? ''));

    if($token === ''){
        api_error("Token is required", 400);
    }

    $tokenStored = 'otp:' . hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT id, email, status, email_verified FROM users WHERE verify_token = :token LIMIT 1");
    $stmt->execute([':token' => $tokenStored]);
    $user = $stmt->fetch();

    if(!$user || $user['status'] !== 'inactive' || (int)$user['email_verified'] !== 0){
        api_error("Invalid resend request", 400);
    }

    $otpPlain = (string)random_int(100000, 999999);
    $otpHash  = password_hash($otpPlain, PASSWORD_DEFAULT);

    $pdo->prepare("UPDATE users SET otp_code = :otp, otp_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) WHERE id = :id")->execute([
        ':otp' => $otpHash,
        ':id'  => (int)$user['id']
    ]);

    send_email($user['email'], "Email Verification OTP", "Your OTP is {$otpPlain}. It is valid for 5 minutes.");
    api_success([
        "message" => "OTP resent successfully"
    ]);
?>