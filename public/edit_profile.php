<?php
    session_start();
    require_once "../config/db.php";

    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT name, mobile FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    $profileImageUrl = null;
    $stmtImg = $pdo->prepare("SELECT file_path FROM profile_photos WHERE user_id = :id ORDER BY id DESC LIMIT 1");
    $stmtImg->execute(['id' => $userId]);
    $file = $stmtImg->fetch();
    if ($file && !empty($file['file_path'])) {
        $p = (string)$file['file_path'];
        if (str_starts_with($p, '../public/')) {
            $p = substr($p, strlen('../public/'));
        } elseif (str_starts_with($p, '../')) {
            $p = substr($p, strlen('../'));
        }
        $profileImageUrl = $p;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <style>
        .iti{width:100%}
        .iti input.form-control{padding-left:90px!important}
    </style>
</head>
<body class="bg-light">

<?php include 'partials/navbar.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="text-center mb-3">Edit Profile</h5>
                    <?php if (!empty($_GET['error'])): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars((string)$_GET['error']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($_GET['success'])): ?>
                        <div class="alert alert-success"><?= htmlspecialchars((string)$_GET['success']) ?></div>
                    <?php endif; ?>

                    <?php if ($profileImageUrl): ?>
                        <div class="text-center mb-3">
                            <img src="<?= htmlspecialchars($profileImageUrl) ?>" class="rounded-circle img-thumbnail" style="width:120px;height:120px;object-fit:cover;" alt="Profile photo">
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-3">
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width:120px;height:120px;font-size:42px;">
                                <?= strtoupper(substr((string)($user['name'] ?? ''), 0, 1)) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="update_profile.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                            <span class="text-danger" id="nameClientErr"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile</label>
                            <input type="tel" name="mobile" id="mobile" class="form-control" value="<?= htmlspecialchars($user['mobile']) ?>" required>
                            <span class="text-danger" id="mobileClientErr"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Profile Image</label>
                            <input type="file" name="profile_img" id="profile_img" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                            <small class="text-muted">Allowed file extensions: .jpg, .jpeg and .png. File size cannot exceed 2 MB.</small>
                            <span class="text-danger" id="imgClientErr"></span>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Update Profile</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="user_profile.php" class='btn btn-primary w-100'>Back to Dashboard</a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>

<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
    const nameInput = document.getElementById('name');
    const mobileInput = document.getElementById('mobile');
    const imgInput = document.getElementById('profile_img');
    const nameErr = document.getElementById('nameClientErr');
    const mobileErr = document.getElementById('mobileClientErr');
    const imgErr = document.getElementById('imgClientErr');

    const iti = window.intlTelInput(mobileInput,{
        initialCountry:"in",
        separateDialCode:true,
        nationalMode:false,
        autoPlaceholder:"off",
        utilsScript:"https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
    });

    function setErr(el, errEl, msg){
        if(!el || !errEl) return true;
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }

    function validateName(){
        const v = (nameInput.value || '').trim();
        if(v.length === 0){
            return setErr(nameInput, nameErr, 'Name cannot be empty or only spaces.');
        }
        if(!/^[a-zA-Z-' ]{3,50}$/.test(v)){
            return setErr(nameInput, nameErr, "Name must be 3-50 characters and contain only letters, spaces, ' and -.");
        }
        return setErr(nameInput, nameErr, '');
    }

    function validateMobile(){
        const v = (mobileInput.value || '').trim();
        if(v.length === 0){
            return setErr(mobileInput, mobileErr, 'Mobile number is required.');
        }
        if (typeof iti.isValidNumber === 'function' && !iti.isValidNumber()) {
            return setErr(mobileInput, mobileErr, 'Enter a valid mobile number.');
        }
        return setErr(mobileInput, mobileErr, '');
    }

    function validateImage(){
        if(!imgInput || !imgInput.files || imgInput.files.length === 0){
            return setErr(imgInput, imgErr, '');
        }
        const f = imgInput.files[0];
        const name = (f.name || '').toLowerCase();
        const ext = name.includes('.') ? name.split('.').pop() : '';
        const allowedExt = ['jpg','jpeg','png'];
        const allowedMime = ['image/jpeg','image/png'];
        if(!allowedExt.includes(ext)){
            return setErr(imgInput, imgErr, 'Only .jpg, .jpeg, .png files are allowed.');
        }
        if(f.type && !allowedMime.includes(f.type)){
            return setErr(imgInput, imgErr, 'Invalid image type.');
        }
        if(f.size > 2097152){
            return setErr(imgInput, imgErr, 'Image too large.');
        }
        return setErr(imgInput, imgErr, '');
    }

    nameInput.addEventListener('blur', validateName);
    mobileInput.addEventListener('blur', validateMobile);
    imgInput.addEventListener('change', validateImage);

    document.querySelector('form').addEventListener('submit', function(e){
        const ok = validateName() & validateMobile() & validateImage();
        if(!ok){
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        mobileInput.value = iti.getNumber();
    });
</script>
