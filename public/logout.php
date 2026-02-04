<?php
    session_start();

    require_once "../config/db.php";
    require_once __DIR__ . '/partials/auth_cookies.php';

    $refresh = (string)($_COOKIE['refresh_token'] ?? '');
    if ($refresh !== '') {
        try {
            $hash = hash('sha256', $refresh);
            $pdo->prepare("UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash")->execute([':hash' => $hash]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    auth_clear_cookie('access_token');
    auth_clear_cookie('refresh_token');

    session_destroy();
    header("Location: login_user.php");
    exit;
?>