<?php
    require __DIR__ . "/../helpers/_bootstrap.php";

    $input = json_decode(file_get_contents('php://input'), true);
    $refreshToken = $input['refresh_token'] ?? null;

    if(!$refreshToken){
        api_error("Missing refresh Token", 401);
    }

    $hash = hash('sha256', $refreshToken);

    $stmt = $pdo->prepare("SELECT user_id FROM refresh_tokens WHERE token_hash = :hash AND revoked = 0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([":hash" => $hash]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row){
        api_error("Invalid refresh token", 401);
    }

    $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash")->execute([":hash" => $hash]);

    $newAccess = create_access_token($row['user_id'], 'user');
    $newRefresh = create_refresh_token($pdo, $row['user_id']);

    api_success([
        'access_token' => $newAccess,
        'refresh_token' => $newRefresh
    ])
?>