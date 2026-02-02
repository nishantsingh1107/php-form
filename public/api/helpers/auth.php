<?php
    // function require_login(): void {
    //     if (!isset($_SESSION['user_id'])) {
    //         api_error("Unauthorized", 401);
    //     }
    // }
    
    use Firebase\JWT\JWT;
    use Firebase\JWT\Key;

    function jwt_config(): array{
        $secret = $_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET');
        if(!$secret){
            api_error("JWT secret not configured", 500);
        }

        return [
            'secret' => $secret,
            'issuer' => $_ENV['JWT_ISSUER'] ?? getenv('JWT_ISSUER') ?: 'phpLearning',
            'ttl' => (int)($_ENV['JWT_TTL_SECONDS'] ?? getenv('JWT_TTL_SECONDS') ?: 3600),
            'algo' => 'HS256'
        ];
    }

    function create_jwt(int $userId, string $role = 'user'): string{
        $cfg = jwt_config();
        $now = time();

        return JWT::encode([
            'iss'  => $cfg['issuer'],
            'iat'  => $now,
            'exp'  => $now + $cfg['ttl'],
            'sub'  => $userId,
            'role' => $role,
        ], $cfg['secret'], $cfg['algo']);
    }


    function require_jwt_auth(): array{
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? null;
    
        if (!$token){
            $token = $_POST['token'] ?? null;
        }
        if (!$token){
            api_error("Missing token", 401);
        }
        $cfg = jwt_config();
    
        try{
            JWT::$leeway = 30;
            $claims = (array) JWT::decode($token, new Key($cfg['secret'], $cfg['algo']));
        }catch(Throwable $e){
            api_error("Invalid or expired token", 401);
        }

        if(empty($claims['sub'])){
            api_error("Invalid token payload", 401);
        }
    
        return $claims;
    }
?>