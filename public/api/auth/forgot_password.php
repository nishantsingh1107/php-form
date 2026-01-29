<?php
    require __DIR__ . '/../helpers/_bootstrap.php';

    $input = json_decode(file_get_contents("php://input"), true);
    $email = trim($input['email'] ?? '');

    if($email === ''){
        api_error("Email is required", 400);
    }

    validate_email($email);

    $stmt = $pdo->prepare("SELECT id, status, email_verified FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if(!$user || $user['status'] !== 'active' || (int)$user['email_verified'] !== 1){
        api_success([
            "message" => "If the email exists, a reset link has been sent"
        ]);
    }

    $tokenPlain = bin2hex(random_bytes(32));
    $tokenHash  = hash('sha256', $tokenPlain);

    $pdo->prepare("UPDATE users SET reset_token_hash = :token, reset_expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE) WHERE id = :id")->execute([
        ':token' => $tokenHash,
        ':id'    => (int)$user['id']
    ]);

    $resetLink = "http://localhost/phpLearning/Project/public/api/auth/reset_password.php?token={$tokenPlain}";

    send_email($email, "Reset Your Password", "Click the link below to reset your password:\n\n{$resetLink}\n\nThis link is valid for 10 minutes.");

    api_success([
        "message" => "If the email exists, a reset link has been sent"
    ]);
?>