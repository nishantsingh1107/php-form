<?php
    use PHPMailer\PHPMailer\PHPMailer;

    session_start();
    require_once "../config/db.php";
    require_once '../vendor/autoload.php';
    
    $emailErr=$passwordErr=$loginErr="";
    $email='';
    if ($_SERVER['REQUEST_METHOD']==='POST') {
    
        $email=trim($_POST['email']??'');
        $pass=$_POST['password']??'';
    
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) {
            $emailErr="Invalid email";
        } else {
            $atPos = strrpos($email, '@');
            $domain = $atPos !== false ? strtolower(substr($email, $atPos + 1)) : '';
            if ($domain === '' || str_contains($domain, '..') || str_ends_with($domain, '.com.com') || str_contains($domain, '.com.') || preg_match('/(\.[a-z]{2,})\1$/i', $email)) {
                $emailErr = "Invalid email";
            }
        }
        if($pass==="") $passwordErr="Password required";
    
        if(!$emailErr && !$passwordErr){
            $stmt=$pdo->prepare("SELECT id,name,email,password,role,status,email_verified,must_change_password FROM users WHERE email=:email");
            $stmt->execute([':email'=>$email]);
            $user=$stmt->fetch(PDO::FETCH_ASSOC);
    
            if(!$user){
                $loginErr="User not found";
            }
            else{
                $hash = trim((string)($user['password'] ?? ''));
                if(!password_verify($pass, $hash)){
                    $passwordErr="Incorrect password";
                }
                else{
                    $status = (string)($user['status'] ?? '');
                    $emailVerified = (int)($user['email_verified'] ?? 0);

                    if ($status === 'inactive' && $emailVerified === 0) {
                        $uid = (int)$user['id'];
                        $otpPlain = (string)random_int(100000, 999999);
                        $otpHash = password_hash($otpPlain, PASSWORD_DEFAULT);

                        $pdo->prepare("UPDATE users SET otp_code=:otp, otp_expires_at=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) WHERE id=:id")
                            ->execute(['otp'=>$otpHash,'id'=>$uid]);

                        $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                        $smtpPort = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
                        $smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
                        $smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
                        $smtpFrom = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: $smtpUser;
                        $smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Organization';
                        $smtpEncryption = strtolower((string)($_ENV['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls'));

                        if ($smtpUser === '' || $smtpPass === '') {
                            $loginErr = "Your account is not verified. Email server not configured.";
                        } else {
                            try {
                                $mail = new PHPMailer(true);
                                $mail->isSMTP();
                                $mail->Host = $smtpHost;
                                $mail->SMTPAuth = true;
                                $mail->Username = $smtpUser;
                                $mail->Password = str_replace(' ', '', trim($smtpPass));
                                $mail->SMTPSecure = $smtpEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port = $smtpPort;
                                $mail->Timeout = 30;

                                $mail->setFrom($smtpFrom, $smtpFromName);
                                $mail->addAddress((string)$user['email']);
                                $mail->Subject = "Email Verification OTP";
                                $mail->Body = "Your OTP is " . $otpPlain . " valid for 5 minutes";
                                $mail->send();

                                $_SESSION['otp_notice'] = "Your account is not verified. Please verify first. A new OTP has been sent.";
                                header("Location: otp_verification.php?uid=" . $uid);
                                exit;
                            } catch (Throwable $e) {
                                $loginErr = "Your account is not verified. Email server not responding. Please try again.";
                            }
                        }
                    }
                    elseif ($status === 'inactive' && $emailVerified === 1) {
                        $loginErr = "Your account is disabled by admin. Please contact the admin.";
                    }
                    elseif ($status !== 'active') {
                        $loginErr = "Your account is disabled by admin. Please contact the admin.";
                    }
                    elseif ($emailVerified === 0) {
                        $loginErr = "Your account is not verified. Please verify first.";
                    }
                    else {
                        $_SESSION['user_id']=$user['id'];

                        if($user['must_change_password']==1){
                            header("Location: change_password.php");
                            exit;
                        }

                        if($user['role']==='admin'){
                            header("Location: ../admin/admin_dashboard.php");
                        }else{
                            header("Location: user_dashboard.php");
                        }
                        exit;
                    }
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h4 class="text-center mb-4">Login</h4>

                <?php if (!empty($loginErr)): ?>
                    <div class="alert alert-danger text-center">
                        <?= htmlspecialchars($loginErr) ?>
                    </div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email<span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="email" value="<?= htmlspecialchars($email) ?>" required>
                        <span class="text-danger" id="emailClientErr"></span>
                        <span class="text-danger"><?= $emailErr ?></span>
                    </div>

                    <div class="mb-3">
                        <label for="loginPass" class="form-label">Password<span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password" id="loginPass" required>
                            <button class="input-group-text" type="button" onclick="toggle('loginPass',this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <span class="text-danger"><?= $passwordErr ?></span>
                    </div>

                    <button id="loginBtn" class="btn btn-success w-100" type="submit">
                        <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                        <span class="btn-text">Login</span>
                    </button>
                </form>

                <div class="text-center mt-3">
                    <span>Don't have an account? <a href="register.php">Register here</a></span>
                </div>
            </div>
        </div>
    </div>
<script>
    const emailInput = document.getElementById('email');
    const emailClientErr = document.getElementById('emailClientErr');

    function setErr(el, errEl, msg){
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }

    function validateEmail(){
        const v = (emailInput.value || '').trim();
        if (v.length === 0) {
            return setErr(emailInput, emailClientErr, 'Email is required.');
        }
        if (!emailInput.checkValidity()) {
            return setErr(emailInput, emailClientErr, 'Enter a valid email address.');
        }
        const at = v.lastIndexOf('@');
        const domain = at >= 0 ? v.slice(at + 1).toLowerCase() : '';
        if (/\.com\.com$/i.test(v) || domain.includes('.com.') || domain.includes('..')) {
            return setErr(emailInput, emailClientErr, 'Email domain is invalid (invalid .com ending).');
        }
        if (/(\.[a-z]{2,})\1$/i.test(v)) {
            return setErr(emailInput, emailClientErr, 'Email domain is invalid (duplicate extension).');
        }
        return setErr(emailInput, emailClientErr, '');
    }

    const f=document.querySelector("form");
    f.addEventListener("submit",function(event){
        if (!validateEmail()) {
            event.preventDefault();
            event.stopPropagation();
            emailInput.focus();
            return;
        }
        const b=document.getElementById("loginBtn");
        b.querySelector(".spinner-border").classList.remove("d-none");
        b.querySelector(".btn-text").textContent="Logging in...";
        b.disabled=true;
    });

    emailInput.addEventListener('blur', validateEmail);
    function toggle(id,el){
        const i=document.getElementById(id);
        const ic=el.querySelector("i");
        if(i.type==="password"){
            i.type="text";
            ic.classList.replace("bi-eye","bi-eye-slash");
        }else{
            i.type="password";
            ic.classList.replace("bi-eye-slash","bi-eye");
        }
    }
</script>
</body>
</html>
