<?php
    require __DIR__ . '/../helpers/_bootstrap.php';
    $input = json_decode(file_get_contents("php://input"), true);

    $token    = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if($token === '' || $password === ''){
        api_error("Token and new password are required", 400);
    }

    validate_password($password);

    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT id, reset_expires_at, (reset_expires_at IS NULL OR reset_expires_at <= UTC_TIMESTAMP()) AS reset_expired FROM users WHERE reset_token_hash = :token LIMIT 1");
    $stmt->execute([':token' => $tokenHash]);
    $user = $stmt->fetch();

    if(!$user){
        api_error("Invalid or expired reset token", 400);
    }
    
    if((int)$user['reset_expired'] === 1){
        api_error("Reset token expired", 410);
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->prepare("UPDATE users SET password = :password, reset_token_hash = NULL, reset_expires_at = NULL WHERE id = :id")->execute([
        ':password' => $newHash,
        ':id'       => (int)$user['id']
    ]);

    api_success([
        "message" => "Password reset successful. You can login now."
    ]);
?>