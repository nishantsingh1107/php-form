<?php
    require __DIR__ . "/../helpers/_bootstrap.php";

    $input = json_decode(file_get_contents("php://input"), true);
    
    $token = trim((string)$input['token'] ?? '');
    $otp = trim((string)$input['otp'] ?? '');

    if($token === '' || $otp === ''){
        api_error("Token and OTP are required", 400);
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        api_error("OTP must be a 6-digit number", 400);
    }

    $tokenStored = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT id, status, email_verified, must_change_password, otp_code, otp_expires_at, (otp_expires_at IS NULL OR otp_expires_at <= UTC_TIMESTAMP()) AS otp_expired FROM users WHERE verify_token = :token LIMIT 1");
    $stmt->execute([":token" => $tokenStored]);

    $user = $stmt->fetch();
    if (!$user) {
        api_error("Invalid verification request", 400);
    }

    if ($user['status'] !== 'inactive') {
        api_error("Account already activated or invalid", 400);
    }

    if ((int)$user['email_verified'] !== 0) {
        api_error("Email already verified", 400);
    }

    // if ((int)$user['must_change_password'] === 1) {
    // api_error("This account must change password before activation", 403);
    // }

    if((int)$user['otp_expired'] === 1){
        api_error("otp expired, Please resend OTP", 410);
    }

    if(!password_verify($otp, (string)$user['otp_code'])){
        api_error("Invalid OTP", 401);
    }

    $pdo->prepare("UPDATE users SET status = 'active', email_verified = 1, email_verified_time = UTC_TIMESTAMP(), otp_code = NULL, otp_expires_at = NULL, verify_token = NULL WHERE id = :id")->execute([":id" => (int)$user['id']]);

    api_success(["message" => "Email verified successfully. You can login now."]);
?>