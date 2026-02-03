<?php
    require __DIR__ . '/../helpers/_bootstrap.php';
    $jwtData = require_access_token();
    $userId = (int)$jwtData['sub'];

    $input = json_decode(file_get_contents("php://input"), true);
    $refreshToken = $input['refresh_token'] ?? null;

    if($refreshToken){
        $hash = hash('sha256', $refreshToken);
        $stmt = $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash AND user_id = :uid");
        $stmt->execute([':hash' => $hash, ':uid' => $userId]);
    }
    api_success(['message' => "Logged out successfully"]);
?>