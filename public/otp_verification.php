<?php
    session_start();
    require_once "../config/db.php";

    $token = trim((string)($_GET['token'] ?? ''));
    if($token === ''){
        header("Location: register.php");
        exit;
    }

    $tokenStored = 'otp:' . hash('sha256', $token);

    $userStmt = $pdo->prepare(
        "SELECT id, email, status, email_verified, must_change_password, otp_code, otp_expires_at, verify_token, "
        . "(otp_expires_at IS NULL OR otp_expires_at <= UTC_TIMESTAMP()) AS otp_expired "
        . "FROM users WHERE verify_token=:token LIMIT 1"
    );
    $userStmt->execute(['token'=>$tokenStored]);
    $user = $userStmt->fetch();
    if(
        !$user
        || (string)$user['status'] !== 'inactive'
        || (int)$user['email_verified'] !== 0
        || (int)($user['must_change_password'] ?? 0) !== 0
    ){
        header("Location: register.php");
        exit;
    }

    $error = "";
    $success = "";

    $notice = (string)($_SESSION['otp_notice'] ?? '');
    if ($notice !== '') {
        unset($_SESSION['otp_notice']);
    }
    
    $isExpired = (int)($user['otp_expired'] ?? 0) === 1;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $otpIn = trim((string)($_POST['otp'] ?? ''));

        $userStmt->execute(['token'=>$tokenStored]);
        $user = $userStmt->fetch();
        if(!$user || (string)$user['status'] !== 'inactive' || (int)$user['email_verified'] !== 0 || (int)($user['must_change_password'] ?? 0) !== 0){
            header("Location: register.php");
            exit;
        }
        $isExpired = (int)($user['otp_expired'] ?? 0) === 1;

        if($otpIn === '' || !preg_match('/^\d{6}$/', $otpIn)){
            $error = "Enter a valid 6-digit OTP";
        } else {
            if($isExpired){
                $error = "OTP expired. Please resend OTP.";
            } else {
                $otpHash = (string)($user['otp_code'] ?? '');
                if(!$otpHash || !password_verify($otpIn, $otpHash)){
                    $error = "Invalid OTP";
                } 
                else{
                    try{
                        $pdo->prepare("UPDATE users SET status='active', email_verified=1, email_verified_time=UTC_TIMESTAMP(), otp_code=NULL, otp_expires_at=NULL, verify_token=NULL WHERE id=:id")->execute(['id'=>(int)$user['id']]);
                    }catch(Throwable $e){
                        $pdo->prepare("UPDATE users SET status='active', email_verified=1, otp_code=NULL, otp_expires_at=NULL, verify_token=NULL WHERE id=:id")->execute(['id'=>(int)$user['id']]);
                    }
                    $success = "Email verified successfully. You can login now.";
                }
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OTP Verification</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h4 class="text-center mb-4">Email Verification</h4>
                <?php if($error): ?>
                    <div class="alert alert-danger text-center">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <?php if($notice): ?>
                    <div class="alert alert-info text-center">
                        <?= htmlspecialchars($notice) ?>
                    </div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success text-center">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if(!$success): ?>
                    <div class="alert alert-warning text-center">
                        OTP will expire in <strong>5 minutes</strong>
                    </div>
                <?php endif; ?>

                <p class="text-muted text-center mb-3">
                    Enter the 6-digit OTP sent to your email
                </p>
                <form method="post" action="otp_verification.php?token=<?= htmlspecialchars(urlencode($token)) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="otp">OTP<span class="text-danger">*</span></label>
                        <input type="text" name="otp" id="otp" class="form-control text-center" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="Enter OTP" required <?= $success ? 'disabled' : '' ?>>
                    </div>

                    <button id="verifyOtpBtn" class="btn btn-primary w-100" <?= $success ? 'disabled' : '' ?>>Verify OTP</button>
                </form>

                <?php if($success): ?>
                    <div class="text-center mt-3">
                        <div class="alert alert-info text-center mb-2">
                            Redirecting to login in <strong><span id="loginTimer">10</span></strong> seconds...
                        </div>
                        <a class="btn btn-success w-100" href="login_user.php">Go to Login</a>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <div>Didn't recive OTP? <a href="resend_otp.php?token=<?= htmlspecialchars(urlencode($token)) ?>">Resend otp</a></div>
                </div>
            </div>
        </div>
    </div>

    <?php if($success): ?>
    <script>
        (function(){
            let sec = 10;
            const el = document.getElementById('loginTimer');
            const tick = () => {
                sec -= 1;
                if (el) el.textContent = String(sec);
                if (sec <= 0) {
                    window.location.href = 'login_user.php';
                    return;
                }
                window.setTimeout(tick, 1000);
            };
            window.setTimeout(tick, 1000);
        })();
    </script>
    <?php endif; ?>

</body>
</html>
