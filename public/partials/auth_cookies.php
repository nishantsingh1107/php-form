<?php

    declare(strict_types=1);
    function auth_is_https_request(): bool{
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }

    function auth_set_cookie(string $name, string $value, int $expiresAt): void{
        $secure = auth_is_https_request();
        setcookie($name, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $_COOKIE[$name] = $value;
    }

    function auth_clear_cookie(string $name): void{
        $secure = auth_is_https_request();
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        unset($_COOKIE[$name]);
    }
?>