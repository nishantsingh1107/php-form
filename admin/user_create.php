<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";
    require_once "../vendor/autoload.php";

    function clean($d){
        return htmlspecialchars(stripslashes(trim($d)),ENT_QUOTES,'UTF-8');
    }

    $name=$email=$mobile=$password="";
    $status = 'inactive';
    $nameErr=$emailErr=$mobileErr=$passErr=$userExist="";
    $imgErrors=[];
    $mailErr="";
    $uploadPath=null;
    $fileExt=null;
    $dbFilePath=null;

    if($_SERVER['REQUEST_METHOD']==='POST'){
        $name=clean($_POST['name']??'');
        $email=clean($_POST['email']??'');
        $mobile=clean($_POST['mobile']??'');
        $password=$_POST['password']??'';
        $status = 'inactive';

        if($name===''||!preg_match("/^[a-zA-Z-' ]{3,50}$/",$name)){
            $nameErr="Invalid name";
        }

        if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
            $emailErr="Invalid email";
        } else {
            $atPos = strrpos($email, '@');
            $domain = $atPos !== false ? strtolower(substr($email, $atPos + 1)) : '';
            if ($domain === '' || str_contains($domain, '..') || str_ends_with($domain, '.com.com') || str_contains($domain, '.com.') || preg_match('/(\.[a-z]{2,})\1$/i', $email)) {
                $emailErr = "Invalid email";
            }
        }

        if(!preg_match('/^\+?[1-9]\d{7,14}$/',$mobile)){
            $mobileErr="Invalid mobile number";
        }

        if(!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/",$password)){
            $passErr="Invalid password format.";
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

            if(empty($imgErrors)){
                $uploadDir = '../public/uploads';
                if(!is_dir($uploadDir)){
                    mkdir($uploadDir,0755,true);
                }
                $uploadPath=$uploadDir.'/'.uniqid('img_',true).'.'.$fileExt;
                if(!move_uploaded_file($_FILES['uploadImg']['tmp_name'],$uploadPath)){
                    $imgErrors[]="Unable to upload image";
                    $uploadPath=null;
                    $dbFilePath=null;
                    $fileExt=null;
                } else {
                    $dbFilePath = 'uploads/' . basename($uploadPath);
                }
            }
        }

        if(!$emailErr&&!$mobileErr){
            $stmt=$pdo->prepare("SELECT id FROM users WHERE email=:email OR mobile=:mobile");
            $stmt->execute([':email'=>$email,':mobile'=>$mobile]);
            if($stmt->fetch()){
                $userExist="User already exists";
            }
        }

        if(!$nameErr&&!$emailErr&&!$mobileErr&&!$passErr&&!$userExist&&empty($imgErrors)){
            $verifyToken=bin2hex(random_bytes(32));

            $stmt=$pdo->prepare("INSERT INTO users(name,email,mobile,password,status,email_verified,verify_token,must_change_password) VALUES(:name,:email,:mobile,:password,'inactive',0,:token,1)");
            $stmt->execute([
                ':name'=>$name,
                ':email'=>$email,
                ':mobile'=>$mobile,
                ':password'=>password_hash($password,PASSWORD_DEFAULT),
                ':token'=>$verifyToken
            ]);

            $userId = (int)$pdo->lastInsertId();

            if ($userId > 0 && $dbFilePath && $fileExt) {
                $pdo->prepare(
                    "INSERT INTO user_files (user_id, file_name, file_path, file_type) VALUES (:uid,:fn,:fp,:ft)"
                )->execute([
                    ':uid' => $userId,
                    ':fn'  => basename($dbFilePath),
                    ':fp'  => $dbFilePath,
                    ':ft'  => $fileExt
                ]);
            }

            $smtpHost = $_ENV['SMTP_HOST'] ?? getenv('SMTP_HOST') ?: 'smtp.gmail.com';
            $smtpPort = (int)($_ENV['SMTP_PORT'] ?? getenv('SMTP_PORT') ?: 587);
            $smtpUser = $_ENV['SMTP_USER'] ?? getenv('SMTP_USER') ?: '';
            $smtpPass = $_ENV['SMTP_PASS'] ?? getenv('SMTP_PASS') ?: '';
            $smtpFrom = $_ENV['SMTP_FROM'] ?? getenv('SMTP_FROM') ?: $smtpUser;
            $smtpFromName = $_ENV['SMTP_FROM_NAME'] ?? getenv('SMTP_FROM_NAME') ?: 'Admin';
            $smtpEncryption = strtolower((string)($_ENV['SMTP_ENCRYPTION'] ?? getenv('SMTP_ENCRYPTION') ?: 'tls'));

            $appUrl = rtrim((string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'http://localhost/phpLearning/Project'), '/');
            $verifyLink = $appUrl . "/public/verify.php?token=" . urlencode($verifyToken);

            if ($smtpUser === '' || $smtpPass === '') {
                $mailErr = "Email server not configured. Please contact admin.";
            } else {
                try{
                    $mail=new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host=$smtpHost;
                    $mail->SMTPAuth=true;
                    $mail->Username=$smtpUser;
                    $mail->Password=str_replace(' ', '', trim($smtpPass));
                    $mail->SMTPSecure=$smtpEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port=$smtpPort;
                    $mail->setFrom($smtpFrom,$smtpFromName);
                    $mail->addAddress($email,$name);

                    $mail->isHTML(true);
                    $mail->Subject="Verify Your Account";
                    $mail->Body="
                        <p>Hello <b>$name</b>,</p>
                        <p>An administrator has created your account.</p>
                        <p>Please verify your email and set your password:</p>
                        <p><a href='$verifyLink'>Verify Account</a></p>
                    ";
                    $mail->send();
                }catch(Throwable $e){
                    $mailErr="Unable to send verification email";
                }
            }

            header("Location: show_users.php");
            exit;
        }
    }
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create User</title>
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
                <h3 class="mb-4 text-center">Create User</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-2">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?=htmlspecialchars($name)?>" required>
                        <span class="text-danger" id="nameClientErr"></span>
                        <span class="text-danger"><?=$nameErr?></span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?=htmlspecialchars($email)?>" required>
                        <span class="text-danger" id="emailClientErr"></span>
                        <span class="text-danger"><?=$emailErr?></span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Mobile</label>
                        <input type="tel" name="mobile" id="mobile" class="form-control" required>
                        <span class="text-danger" id="mobileClientErr"></span>
                        <span class="text-danger"><?=$mobileErr?></span>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Default Password</label>
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
                    <div class="mb-2">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control" value="Inactive" disabled>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Upload Image</label>
                        <input type="file" name="uploadImg" id="uploadImg" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <small class="text-muted">Allowed file extensions: .jpg, .jpeg and .png. File size cannot exceed 2 MB.</small>
                        <span class="text-danger" id="imgClientErr"></span>
                        <span class="text-danger"><?=implode(' ',$imgErrors)?></span>
                        <span class="text-danger d-block"><?=$userExist?></span>
                        <span class="text-danger d-block"><?=$mailErr?></span>
                    </div>
                    <button type="submit" class="btn btn-success w-100" id="createBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none"></span>
                        <span class="btn-text">Create User</span>
                    </button>
                </form>
                <div class="text-center mt-2">
                    <a href="admin_dashboard.php" class='btn btn-primary w-100'>Back</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
    const nameInput = document.getElementById('name');
    const emailInput = document.getElementById('email');
    const passInput = document.getElementById('password');
    const phone = document.getElementById('mobile');
    const imgInput = document.getElementById('uploadImg');
    const nameClientErr = document.getElementById('nameClientErr');
    const emailClientErr = document.getElementById('emailClientErr');
    const passClientErr = document.getElementById('passClientErr');
    const mobileClientErr = document.getElementById('mobileClientErr');
    const imgClientErr = document.getElementById('imgClientErr');

    function setErr(el, errEl, msg){
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }

    function validateName(){
        const v = (nameInput.value || '').trim();
        if (v.length === 0) {
            return setErr(nameInput, nameClientErr, 'Name cannot be empty or only spaces.');
        }
        if (!/^[a-zA-Z-' ]{3,50}$/.test(v)) {
            return setErr(nameInput, nameClientErr, "Name must be 3-50 characters and contain only letters, spaces, ' and -.");
        }
        return setErr(nameInput, nameClientErr, '');
    }

    function validateEmail(){
        const v = (emailInput.value || '').trim();
        if(v.length === 0){
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

    function validatePassword(){
        const v = (passInput.value || '');
        if(v.length === 0){
            return setErr(passInput, passClientErr, 'Password is required.');
        }
        const ok = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/.test(v);
        if(!ok){
            return setErr(passInput, passClientErr, 'Invalid password format.');
        }
        return setErr(passInput, passClientErr, '');
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
    passInput.addEventListener('blur', validatePassword);
    phone.addEventListener('blur', validateMobile);
    if(imgInput) imgInput.addEventListener('change', validateImage);

    document.querySelector("form").addEventListener("submit",e=>{
        const okName = validateName();
        const okEmail = validateEmail();
        const okPass = validatePassword();
        const okMobile = validateMobile();
        const okImg = validateImage();
        if (!okName || !okEmail || !okPass || !okMobile || !okImg) {
            e.preventDefault();
            e.stopPropagation();
            if (!okName) nameInput.focus();
            else if (!okEmail) emailInput.focus();
            else if (!okPass) passInput.focus();
            else if (!okMobile) phone.focus();
            else if (imgInput) imgInput.focus();
            return;
        }

        phone.value=iti.getNumber();
        const btn=document.getElementById("createBtn");
        btn.querySelector(".spinner-border").classList.remove("d-none");
        btn.querySelector(".btn-text").textContent="Creating...";
        btn.disabled=true;
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
