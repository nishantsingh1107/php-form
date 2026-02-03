<?php
    // function require_login(): void {
    //     if (!isset($_SESSION['user_id'])) {
    //         api_error("Unauthorized", 401);
    //     }
    // }

    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    function access_token_ttl(): int {
        return (int)($_ENV['JWT_ACCESS_TTL'] ?? 900); // 15 min
    }

    function refresh_token_ttl(): int {
        return (int)($_ENV['JWT_REFRESH_TTL'] ?? 2592000); // 30 days
    }

    function jwt_config(): array{
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        if(!$secret){
            api_error("JWT secret not configured", 500);
        }

        return [
            'secret' => $secret,
            'issuer' => $_ENV['JWT_ISSUER'] ?? getenv('JWT_ISSUER') ?: 'Organization',
            'algo' => 'HS256'
        ];
    }

    function create_access_token(int $userId, string $role = 'user'): string{
        $cfg = jwt_config();
        $now = time();

        return JWT::encode([
            'iss' => $cfg['issuer'],
            'iat' => $now,
            'exp' => $now + access_token_ttl(),
            'sub' => $userId,
            'role' => $role,
            'type' => 'access',
        ], $cfg['secret'], $cfg['algo']);
    }

    function create_refresh_token(PDO $pdo, int $userId): string {
        $plain = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $plain);

        $expiresAt = date('Y-m-d H:i:s', time() + refresh_token_ttl());

        $stmt = $pdo->prepare("INSERT INTO refresh_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, :exp)");
        $stmt->execute([
            ':uid'  => $userId,
            ':hash' => $hash,
            ':exp'  => $expiresAt
        ]);
        return $plain;
    }

    function get_token_from_request(): ?string {
        $input = json_decode(file_get_contents('php://input'), true);
        if(is_array($input) && !empty($input['access_token'])){
            return $input['access_token'];
        }
        if(!empty($_POST['access_token'])){
            return $_POST['access_token'];
        }
        if(!empty($_SERVER['HTTP_AUTHORIZATION'])){
            if(preg_match('/Bearer\s+(\S+)/', $_SERVER['HTTP_AUTHORIZATION'], $m)){
                return $m[1];
            }
        }
        return null;
    }


    function require_access_token(): array {
        $token = get_token_from_request();
        if (!$token) {
            api_error("Missing token", 401);
        }
        $cfg = jwt_config();
        try {
            JWT::$leeway = 30;
            $claims = (array) JWT::decode($token, new Key($cfg['secret'], $cfg['algo']));
            if (($claims['type'] ?? '') !== 'access'){
                api_error("Invalid token type", 401);
            }
            if(($claims['iss'] ?? '') !== $cfg['issuer']){
                api_error("Invalid token issuer", 401);
            }
        }catch (Throwable $e){
            api_error("Invalid or expired token", 401);
        }
        if(empty($claims['sub'])){
            api_error("Invalid token payload", 401);
        }
        return $claims;
    }
?>