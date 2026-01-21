<?php
    session_start();
    require_once "../config/db.php";
    
    $token = $_GET['token'] ?? "";
    $verified = false;
    if($token){
        $stmt = $pdo->prepare("SELECT id, role FROM users WHERE verify_token = :token AND email_verified = 0");
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