<?php
    session_start();
    require_once "../config/db.php";
    require_once __DIR__ . '/partials/auth_cookies.php';
    require_once __DIR__ . '/api/helpers/auth.php';
    
    $token = $_GET['token'] ?? "";
    $verified = false;
    if($token){
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE verify_token = :token AND email_verified = 0 AND must_change_password = 1");
        $stmt->execute([":token" => $token]);

        $user = $stmt->fetch();
        if($user){
            try{
                $stmt = $pdo->prepare("UPDATE users SET status='active', email_verified = 1, email_verified_time=UTC_TIMESTAMP(), verify_token = NULL, must_change_password = 1 WHERE id = :id");
                $stmt->execute([":id" => $user['id']]);
            }catch(Throwable $e){
                $stmt = $pdo->prepare("UPDATE users SET status='active', email_verified = 1, verify_token = NULL, must_change_password = 1 WHERE id = :id");
                $stmt->execute([":id" => $user['id']]);
            }
            $verified = true;
            $userId = $user['id'];

            $_SESSION['user_id'] = $userId;
            $_SESSION['role'] = (string)($user['role'] ?? 'user');

            try {
                $pdo->prepare("DELETE FROM refresh_tokens WHERE user_id = :uid")->execute([':uid' => (int)$userId]);
            } catch (Throwable $e) {
                // ignore
            }

            $accessToken = create_access_token((int)$userId, (string)($user['role'] ?? 'user'));
            $refreshToken = create_refresh_token($pdo, (int)$userId);
            auth_set_cookie('access_token', $accessToken, time() + access_token_ttl());
            auth_set_cookie('refresh_token', $refreshToken, time() + refresh_token_ttl());

            header("Location: change_password.php");
            exit;
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verified</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">

<div class="card shadow-sm p-4 text-center" style="max-width:420px;">
    <?php if ($verified): ?>
        <h4 class="text-success">Email Verified Successfully</h4>
        <p class="mt-2">
            Your account has been verified.<br>
            Please change the default password to continue.
        </p>
        <a href="change_password.php?id=<?= $userId ?>" class="btn btn-primary mt-3">
            Change Password
        </a>
    <?php else: ?>
        <h4 class="text-danger">Verification Failed</h4>
        <p>The verification link is invalid or expired.</p>
    <?php endif; ?>
</div>
</body>
</html>