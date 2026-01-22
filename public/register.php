<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require '../vendor/autoload.php';
    require_once "../config/db.php";

    function clean($d){
        return trim($d);
    }

    $nameErr=$emailErr=$mobileErr=$passErr=$cnfpassErr=$userExist="";
    $imgErrors=[];
    $mailErr="";
    $uploadPath=null;
    $fileExt=null;

    if($_SERVER['REQUEST_METHOD']==='POST'){
        $name=clean($_POST['name']??'');
        $email=clean($_POST['email']??'');
        $mobile=clean($_POST['mobile']??'');
        $pass=$_POST['password']??'';
        $cnf=$_POST['cnfpassword']??'';

        if($name===''||!preg_match("/^[a-zA-Z-' ]{3,50}$/",$name)){
            $nameErr="Invalid name";
        }

        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
            $emailErr="Invalid email";
        } else {
            $atPos = strrpos($email, '@');
            $domain = $atPos !== false ? strtolower(substr($email, $atPos + 1)) : '';
            if ($domain === '' || str_contains($domain, '..') || str_ends_with($domain, '.com.com') || str_contains($domain, '.com.')) {
                $emailErr = "Invalid email";
            } elseif (preg_match('/(\.[a-z]{2,})\1$/i', $email)) {
                $emailErr = "Invalid email";
            }
        }

        if(!preg_match('/^\+?[1-9]\d{7,14}$/',$mobile)){
            $mobileErr="Invalid mobile";
        }

        if(!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/",$pass)){
            $passErr="Weak password";
        }

        if($pass!==$cnf){
            $cnfpassErr="Passwords do not match";
        }

        if(!empty($_FILES['uploadImg']['name'])){
            $fileExt=strtolower(pathinfo($_FILES['uploadImg']['name'],PATHINFO_EXTENSION));
            if(!getimagesize($_FILES['uploadImg']['tmp_name'])){
                $imgErrors[]="Invalid image";
            }
            if($_FILES['uploadImg']['size']>2097152){
                $imgErrors[]="Image too large";
            }
            if(!in_array($fileExt,['jpg','jpeg','png'])){
                $imgErrors[]="Invalid image type";
            }
            if(!$imgErrors){
                if(!is_dir('uploads')){
                    mkdir('uploads',0755,true);
                }
                $uploadPath='uploads/'.uniqid('img_',true).'.'.$fileExt;
                if(!move_uploaded_file($_FILES['uploadImg']['tmp_name'],$uploadPath)){
                    $imgErrors[]="Unable to save uploaded image";
                    $uploadPath=null;
                    $fileExt=null;
                }
            }
        }

        if(!$emailErr && !$mobileErr){
            $stmt=$pdo->prepare("SELECT id FROM users WHERE email=:email OR mobile=:mobile LIMIT 1");
            $stmt->execute(['email'=>$email,'mobile'=>$mobile]);
            if($stmt->fetch()){
                $userExist="Your account already exists. Please login.";
                if (!empty($uploadPath) && file_exists($uploadPath)) {
                    @unlink($uploadPath);
                    $uploadPath = null;
                    $fileExt = null;
                }
            }
        }

        if(!$nameErr && !$emailErr && !$mobileErr && !$passErr && !$cnfpassErr && empty($imgErrors) && !$userExist){
            $passwordHash = password_hash($pass, PASSWORD_DEFAULT);
            $otpPlain = (string)random_int(100000,999999);
            $otpHash = password_hash($otpPlain, PASSWORD_DEFAULT);
            $otpToken = bin2hex(random_bytes(32));
            $otpTokenStored = 'otp:' . hash('sha256', $otpToken);

            $pdo->prepare("INSERT INTO users(name,email,mobile,password,status,role,email_verified,verify_token,must_change_password,otp_code,otp_expires_at) VALUES(:name,:email,:mobile,:password,'inactive','user',0,:token,0,:otp,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))")
                ->execute([
                    'name'=>$name,
                    'email'=>$email,
                    'mobile'=>$mobile,
                    'password'=>$passwordHash,
                    'token'=>$otpTokenStored,
                    'otp'=>$otpHash
                ]);
            $userId = (int)$pdo->lastInsertId();

            if ($userId && !empty($uploadPath)) {
                $pdo->prepare("INSERT INTO user_files (user_id, file_name, file_path, file_type) VALUES (:uid, :fn, :fp, :ft)")->execute([
                    'uid' => $userId,
                    'fn'  => basename($uploadPath),
                    'fp'  => $uploadPath,
                    'ft'  => $fileExt
                ]);
            }

            $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $smtpPort = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
            $smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
            $smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
            $smtpFrom = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: $smtpUser;
            $smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Organization';
            $smtpEncryption = strtolower((string)($_ENV['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls'));

            if ($smtpUser === '' || $smtpPass === '') {
                $mailErr = "Email server not configured. Please contact admin.";
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
                    $mail->addAddress($email);
                    $mail->Subject = "Email Verification OTP";
                    $mail->Body = "Your OTP is " . $otpPlain . " valid for 5 minutes";

                    $mail->send();
                    header("Location: otp_verification.php?token=" . urlencode($otpToken));
                    exit;
                } catch (Throwable $e) {
                    $mailErr = "Email server not responding. Please try again.";
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .iti{width:100%}
        .iti input.form-control{padding-left:90px!important}
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h4 class="mb-4 text-center">Create Account</h4>

                <?php if (!empty($userExist)): ?>
                    <div class="alert alert-danger text-center">
                        <?= htmlspecialchars($userExist) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($mailErr)): ?>
                    <div class="alert alert-info text-center">
                        <?= htmlspecialchars($mailErr) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?=htmlspecialchars($_SERVER['PHP_SELF'])?>" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Name<span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                        <span class="text-danger" id="nameClientErr"></span>
                        <span class="text-danger"><?=$nameErr?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email<span class="text-danger">*</span></label>
                        <input type="email" name="email" id="email" class="form-control" required>
                        <span class="text-danger" id="emailClientErr"></span>
                        <span class="text-danger"><?=$emailErr?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile Number<span class="text-danger">*</span></label>
                        <input type="tel" name="mobile" id="mobile" class="form-control" required>
                        <span class="text-danger" id="mobileClientErr"></span>
                        <span class="text-danger"><?=$mobileErr?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password<span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="password" id="password" class="form-control" required>
                            <span class="input-group-text" onclick="togglePassword('password',this)">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                        <small class="text-muted d-block">Use 8 characters with one uppercase, one lowercase, one number, and one special character.</small>
                        <span class="text-danger" id="passClientErr"></span>
                        <span class="text-danger"><?=$passErr?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password<span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" name="cnfpassword" id="cnfpassword" class="form-control" required>
                            <span class="input-group-text" onclick="togglePassword('cnfpassword',this)">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                        <span class="text-danger" id="cnfpassClientErr"></span>
                        <span class="text-danger"><?=$cnfpassErr?></span>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload Image</label>
                        <input type="file" name="uploadImg" id="uploadImg" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <small class="text-muted">Allowed file extensions: .jpg, .jpeg and .png. File size cannot exceed 2 MB.</small>
                        <span class="text-danger" id="imgClientErr"></span>
                        <span class="text-danger"><?=implode(' ',$imgErrors)?></span>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-primary w-100" id="registerBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                        <span class="btn-text">Register</span>
                    </button>
                </form>
                <div class="text-center mt-2">
                    <span>Already have an account? <a href="login_user.php">Login here</a></span>
                </div>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const phone = document.getElementById('mobile');
    const passwordInput = document.getElementById('password');
    const cnfPasswordInput = document.getElementById('cnfpassword');
    const imgInput = document.getElementById('uploadImg');
    const nameClientErr = document.getElementById('nameClientErr');
    const emailClientErr = document.getElementById('emailClientErr');
    const mobileClientErr = document.getElementById('mobileClientErr');
    const passClientErr = document.getElementById('passClientErr');
    const cnfpassClientErr = document.getElementById('cnfpassClientErr');
    const imgClientErr = document.getElementById('imgClientErr');

    function setErr(el, errEl, msg){
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }

    function validateName(){
        const v = (nameInput.value || '').trim();
        if(v.length===0){
            return setErr(nameInput, nameClientErr, 'Name cannot be empty or only spaces.');
        }
        if(!/^[a-zA-Z-' ]{3,50}$/.test(v)){
            return setErr(nameInput, nameClientErr, "Name must be 3-50 characters and contain only letters, spaces, ' and -.");
        }
        return setErr(nameInput, nameClientErr, '');
    }

    function validateEmail(){
        const v = (emailInput.value || '').trim();
        if(v.length===0){
            return setErr(emailInput, emailClientErr, 'Email is required.');
        }
        if(!emailInput.checkValidity()){
            return setErr(emailInput, emailClientErr, 'Enter a valid email address.');
        }
        const at = v.lastIndexOf('@');
        const domain = at >= 0 ? v.slice(at + 1).toLowerCase() : '';
        if(/\.com\.com$/i.test(v) || domain.includes('.com.') || domain.includes('..')){
            return setErr(emailInput, emailClientErr, 'Email domain is invalid (invalid .com ending).');
        }
        if(/(\.[a-z]{2,})\1$/i.test(v)){
            return setErr(emailInput, emailClientErr, 'Email domain is invalid (duplicate extension).');
        }
        return setErr(emailInput, emailClientErr, '');
    }

    const iti=window.intlTelInput(phone,{
        initialCountry:"in",
        separateDialCode:true,
        nationalMode:false,
        autoPlaceholder:"off",
        utilsScript:"https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
    });

    function validateMobile(){
        const v = (phone.value || '').trim();
        if(v.length === 0){
            return setErr(phone, mobileClientErr, 'Mobile number is required.');
        }
        if (typeof iti.isValidNumber === 'function' && !iti.isValidNumber()) {
            return setErr(phone, mobileClientErr, 'Enter a valid mobile number.');
        }
        return setErr(phone, mobileClientErr, '');
    }

    function validatePassword(){
        const v = passwordInput.value || '';
        if (v.length === 0) {
            return setErr(passwordInput, passClientErr, 'Password is required.');
        }
        const strong = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/;
        if (!strong.test(v)) {
            return setErr(passwordInput, passClientErr, 'Invalid Password Format');
        }
        return setErr(passwordInput, passClientErr, '');
    }

    function validateConfirmPassword(){
        const v = cnfPasswordInput.value || '';
        if (v.length === 0) {
            return setErr(cnfPasswordInput, cnfpassClientErr, 'Confirm password is required.');
        }
        if (v !== (passwordInput.value || '')) {
            return setErr(cnfPasswordInput, cnfpassClientErr, 'Passwords do not match.');
        }
        return setErr(cnfPasswordInput, cnfpassClientErr, '');
    }

    function validateImage(){
        if(!imgInput || !imgInput.files || imgInput.files.length === 0){
            return setErr(imgInput, imgClientErr, '');
        }
        const f = imgInput.files[0];
        const name = (f.name || '').toLowerCase();
        const ext = name.includes('.') ? name.split('.').pop() : '';
        const allowedExt = ['jpg','jpeg','png'];
        const allowedMime = ['image/jpeg','image/png'];

        if(!allowedExt.includes(ext)){
            return setErr(imgInput, imgClientErr, 'Only .jpg, .jpeg, .png files are allowed.');
        }
        if(f.type && !allowedMime.includes(f.type)){
            return setErr(imgInput, imgClientErr, 'Invalid image type.');
        }
        if(f.size > 2097152){
            return setErr(imgInput, imgClientErr, 'Image too large (max 2MB).');
        }
        return setErr(imgInput, imgClientErr, '');
    }

    nameInput.addEventListener('blur', validateName);
    emailInput.addEventListener('blur', validateEmail);
    phone.addEventListener('blur', validateMobile);
    passwordInput.addEventListener('blur', validatePassword);
    cnfPasswordInput.addEventListener('blur', validateConfirmPassword);
    passwordInput.addEventListener('input', () => {
        validatePassword();
        if ((cnfPasswordInput.value || '').length > 0) validateConfirmPassword();
    });
    cnfPasswordInput.addEventListener('input', validateConfirmPassword);
    if(imgInput) imgInput.addEventListener('change', validateImage);

    document.querySelector("form").addEventListener("submit",e=>{
        const okName = validateName();
        const okEmail = validateEmail();
        const okMobile = validateMobile();
        const okPass = validatePassword();
        const okCnf = validateConfirmPassword();
        const okImg = validateImage();
        if (!okName || !okEmail || !okMobile || !okPass || !okCnf || !okImg) {
            e.preventDefault();
            e.stopPropagation();
            if (!okName) nameInput.focus();
            else if (!okEmail) emailInput.focus();
            else if (!okMobile) phone.focus();
            else if (!okPass) passwordInput.focus();
            else if (!okCnf) cnfPasswordInput.focus();
            else if (imgInput) imgInput.focus();
            return;
        }

        phone.value=iti.getNumber();
        const btn=document.getElementById("registerBtn");
        btn.querySelector(".spinner-border").classList.remove("d-none");
        btn.querySelector(".btn-text").textContent="Sending OTP...";
        btn.disabled = true;
    });
    function togglePassword(id,el){
        const i=document.getElementById(id);
        const icon=el.querySelector("i");
        if(i.type==="password"){
            i.type="text";
            icon.classList.replace("bi-eye","bi-eye-slash");
        }else{
            i.type="password";
            icon.classList.replace("bi-eye-slash","bi-eye");
        }
    }
</script>
</body>
</html>
