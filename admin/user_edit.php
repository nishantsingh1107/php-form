<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    $id=$_GET['id']??null;
    if(!$id)die("Invalid request");

    $stmt=$pdo->prepare("SELECT u.*, (SELECT file_path FROM user_files WHERE user_id=u.id ORDER BY id DESC LIMIT 1) AS file_path FROM users u WHERE u.id=:id ");
    $stmt->execute([':id'=>$id]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$user)die("User not found");

    $imgErr="";
    $nameErr="";
    $mobileErr="";
    $uploadPath=null;

    if($_SERVER['REQUEST_METHOD']==='POST'){
        $name=trim($_POST['name']);
        $mobile=trim($_POST['mobile']);
        $status=$_POST['status'];

        if($name==='' || !preg_match("/^[a-zA-Z-' ]{3,50}$/", $name)){
            $nameErr = "Invalid name";
        }

        $mobile = preg_replace('/[\s\-\(\)]/', '', $mobile);
        if($mobile===''){
            $mobileErr="Mobile number is required";
        }elseif(!preg_match('/^\+?[1-9]\d{7,14}$/',$mobile)){
            $mobileErr="Invalid mobile number";
        }

        if(!empty($_FILES['uploadImg']['name'])){
            $ext=strtolower(pathinfo($_FILES['uploadImg']['name'],PATHINFO_EXTENSION));
            if(!in_array($ext,['jpg','jpeg','png'])){
                $imgErr="Invalid image type";
            }elseif($_FILES['uploadImg']['size']>2097152){
                $imgErr="Image too large";
            }elseif(!getimagesize($_FILES['uploadImg']['tmp_name'])){
                $imgErr="Invalid image file";
            }elseif((int)($_FILES['uploadImg']['error'] ?? 0) !== UPLOAD_ERR_OK){
                $imgErr = "Image upload failed";
            }else{
                $dir=__DIR__."/../public/uploads/";
                if(!is_dir($dir))mkdir($dir,0755,true);

                $fileName=uniqid('img_',true).".".$ext;
                move_uploaded_file($_FILES['uploadImg']['tmp_name'],$dir.$fileName);
                $uploadPath="uploads/".$fileName;
            }
        }
        
        if(!$nameErr && !$imgErr && !$mobileErr){
            $pdo->prepare("UPDATE users SET name=:name, mobile=:mobile, status=:status WHERE id=:id")->execute([
                ':name'=>$name,
                ':mobile'=>$mobile,
                ':status'=>$status,
                ':id'=>$id
            ]);
            if($uploadPath){
                $pdo->prepare("
                    INSERT INTO user_files(user_id,file_name,file_path,file_type)
                    VALUES(:uid,:fn,:fp,:ft)
                ")->execute([
                    ':uid'=>$id,
                    ':fn'=>basename($uploadPath),
                    ':fp'=>$uploadPath,
                    ':ft'=>$ext
                ]);
            }

            header("Location: show_users.php");
            exit;
        }
    }
?>

<!DOCTYPE html>
<html>
<head>
<title>Edit User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
<style>
    .iti{width:100%}
    .iti input.form-control{padding-left:90px!important}
</style>
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h3 class="text-center mb-4">Edit User Details</h3>

                <div class="text-center mb-3">
                    <?php if($user['file_path']): ?>
                        <img src="../public/<?=htmlspecialchars($user['file_path'])?>" class="rounded-circle" style="width:100px;height:100px;object-fit:cover;">
                    <?php else: ?>
                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width:100px;height:100px;">
                        <?=strtoupper(substr($user['name'],0,1))?>
                    </div>
                    <?php endif; ?>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <div class="mb-2">
                        <label class="form-label" for="name">Name</label>
                        <input class="form-control" name="name" id="name" value="<?=htmlspecialchars($user['name'])?>" required>
                        <span class="text-danger" id="nameClientErr"></span>
                        <span class="text-danger d-block"><?=$nameErr?></span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="email">Email</label>
                        <input id="email" class="form-control" value="<?=htmlspecialchars($user['email'])?>" disabled>
                    </div>

                    <div class="mb-2">
                        <label class="form-label" for="mobile">Mobile</label>
                        <input type="tel" class="form-control" name="mobile" id="mobile" value="<?=htmlspecialchars($user['mobile'])?>" required>
                        <span class="text-danger" id="mobileClientErr"></span>
                        <span class="text-danger d-block"><?=$mobileErr?></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-control" name="status" id="status">
                        <option value="active" <?=$user['status']=='active'?'selected':''?>>Active</option>
                        <option value="inactive" <?=$user['status']=='inactive'?'selected':''?>>Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Change Profile Image</label>
                        <input type="file" name="uploadImg" id="uploadImg" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <small class="text-muted">Allowed file extensions: .jpg, .jpeg and .png. File size cannot exceed 2 MB.</small>
                        <span class="text-danger" id="imgClientErr"></span>
                        <span class="text-danger"><?=$imgErr?></span>
                    </div>
                    <button class="btn btn-warning w-100">Update</button>
                </form>
                <div class="text-center mt-2">
                    <a href="show_users.php" class='btn btn-primary w-100'>Back</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
    const nameInput = document.getElementById('name');
    const nameClientErr = document.getElementById('nameClientErr');
    const mobileInput = document.getElementById('mobile');
    const mobileClientErr = document.getElementById('mobileClientErr');
    const imgInput = document.getElementById('uploadImg');
    const imgClientErr = document.getElementById('imgClientErr');
    function setErr(el, errEl, msg){
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }
    const iti = window.intlTelInput(mobileInput,{
        initialCountry:"in",
        separateDialCode:true,
        nationalMode:false,
        autoPlaceholder:"off",
        utilsScript:"https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
    });

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

    function validateMobile(){
        const v = (mobileInput.value || '').trim();
        if (v.length === 0) {
            return setErr(mobileInput, mobileClientErr, 'Mobile number is required.');
        }
        if (typeof iti.isValidNumber === 'function' && !iti.isValidNumber()) {
            return setErr(mobileInput, mobileClientErr, 'Enter a valid mobile number.');
        }
        return setErr(mobileInput, mobileClientErr, '');
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
    mobileInput.addEventListener('blur', validateMobile);
    imgInput.addEventListener('change', validateImage);
    document.querySelector('form').addEventListener('submit', function(e){
        const okName = validateName();
        const okMobile = validateMobile();
        const okImg = validateImage();
        if (!okName || !okMobile || !okImg) {
            e.preventDefault();
            e.stopPropagation();
            if (!okName) nameInput.focus();
            else if (!okMobile) mobileInput.focus();
            else imgInput.focus();
            return;
        }

        mobileInput.value = iti.getNumber();
    });
</script>
