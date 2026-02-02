<?php
    require_once __DIR__ . '/../helpers/_bootstrap.php';
    $input = json_decode(file_get_contents("php://input"), true);

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if($email === '' || $password === ''){
        api_error("Email and password are required", 400);
    }

    validate_email($email);

    $stmt = $pdo->prepare("SELECT id, name, email, password, role, status, email_verified, must_change_password FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);

    $user = $stmt->fetch();
    if(!$user){
        api_error("User not found", 404);
    }

    if(!password_verify($password, (string)$user['password'])){
        api_error("Incorrect password", 401);
    }

    $status = (string)$user['status'];
    $emailVerified = (int)$user['email_verified'];
    $mustChangePassword = (int)($user['must_change_password'] ?? 0);
    $userId = (int)$user['id'];

    if($status === 'inactive' && $emailVerified === 0 && $mustChangePassword === 0){
        $otpPlain = (string) random_int(100000, 999999);
        $otpHash = password_hash($otpPlain, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $pdo->prepare("UPDATE users SET otp_code = :otp, otp_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE), verify_token = :token WHERE id = :id")->execute([
            ':otp' => $otpHash,
            ':token' => $tokenHash,
            ':id' => $userId
        ]);

        send_email($user['email'], "Email Verification OTP", "Your OTP is {$otpPlain}. It is valid for 5 minutes.");

        api_error("Account not verified. OTP sent to your email.", 403);
    }

    if($status !== 'active'){
        api_error("Your account is disabled. Please contact admin.", 403);
    }

    if($emailVerified === 0){
        api_error("Email not verified. Please verify first.", 403);
    }

    // $_SESSION['user_id'] = $userId;

    if($mustChangePassword === 1){
        api_success([
            "message" => "Password change required",
            "action" => "change_password"
        ]);
    }
    
    $token = create_jwt($userId);

    api_success([
        "message" => "Login successful",
        "role" => $user['role'],
        "token" => $token
    ]);
?>