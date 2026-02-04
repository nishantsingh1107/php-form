<?php
    declare(strict_types=1);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    require_once __DIR__ . '/auth_cookies.php';
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../api/helpers/auth.php';

    function ui_require_access_token_from_cookie(?string $token): ?array{
        $token = trim((string)$token);
        if ($token === '') {
            return null;
        }
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        try {
            return require_access_token();
        } catch (Throwable $e) {
            return null;
        }
    }

    function ui_try_refresh_tokens(PDO $pdo, string $refreshToken): ?array{
        $refreshToken = trim($refreshToken);
        if ($refreshToken === '') {
            return null;
        }

        $hash = hash('sha256', $refreshToken);

        $stmt = $pdo->prepare("SELECT user_id FROM refresh_tokens WHERE token_hash = :hash AND revoked = 0 AND expires_at > NOW() LIMIT 1");
        $stmt->execute([":hash" => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['user_id'])) {
            return null;
        }

        $userId = (int)$row['user_id'];
        if ($userId <= 0) {
            return null;
        }

        $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash")->execute([":hash" => $hash]);

        $role = 'user';
        try {
            $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = :id LIMIT 1");
            $roleStmt->execute([':id' => $userId]);
            $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if ($roleRow && !empty($roleRow['role'])) {
                $role = (string)$roleRow['role'];
            }
        } catch (Throwable $e) {
            $role = 'user';
        }

        try {
            return [
                'access_token' => create_access_token($userId, $role),
                'refresh_token' => create_refresh_token($pdo, $userId),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    $access = (string)($_COOKIE['access_token'] ?? '');
    $claims = ui_require_access_token_from_cookie($access);

    if (!$claims) {
        $refresh = (string)($_COOKIE['refresh_token'] ?? '');
        if ($refresh !== '') {
            $newTokens = ui_try_refresh_tokens($pdo, $refresh);
            if ($newTokens){
                auth_set_cookie('access_token', (string)$newTokens['access_token'], time() + access_token_ttl());
                auth_set_cookie('refresh_token', (string)$newTokens['refresh_token'], time() + refresh_token_ttl());
                $claims = ui_require_access_token_from_cookie((string)$newTokens['access_token']);
            }
        }
    }

    if (!$claims) {
        auth_clear_cookie('access_token');
        auth_clear_cookie('refresh_token');
        header('Location: login_user.php');
        exit;
    }

    $_SESSION['user_id'] = (int)$claims['sub'];
    $_SESSION['role'] = (string)($claims['role'] ?? 'user');
?>