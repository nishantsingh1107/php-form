<?php
    require __DIR__ . "/../helpers/_bootstrap.php";
    
    $input = json_decode(file_get_contents("php://input"), true);
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $mobile = trim($input['mobile'] ?? '');
    $password = $input['password'] ?? '';

    if($name === '' || $email === '' || $mobile === '' || $password === ''){
        api_error("Name, email, mobile number, password are required", 400);
    }

    validate_name($name);
    validate_email($email);
    validate_mobile($mobile);
    validate_password($password);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR mobile = :mobile LIMIT 1");
    $stmt->execute([":email" => $email, ":mobile" => $mobile]);
    if($stmt->fetch()){
        api_error("Email or mobile already registered", 409);
    }

    $otpPlain = (string) random_int(100000, 999999);
    $otpHash = password_hash($otpPlain, PASSWORD_DEFAULT);
    $tokenPlain = bin2hex(random_bytes(32));
    $tokenStored = 'otp:' . hash('sha256', $tokenPlain);

    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, mobile, password, status, email_verified, otp_code, otp_expires_at, verify_token, must_change_password) VALUES (:name, :email, :mobile, :password, 'inactive', 0, :otp, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE), :token, 0)");
    $stmt->execute([
        ":name" => $name,
        ":email" => $email,
        ":mobile" => $mobile,
        ":password" => $password_hash,
        ':otp' => $otpHash,
        ':token' => $tokenStored
    ]);

    send_email($email, "Email Verification OTP", "Your OTP is {$otpPlain}. It is valid for 5 minutes.");

    api_success([
        "message" => "OTP sent to your email",
        "token" => $tokenPlain
    ], 201);
?>