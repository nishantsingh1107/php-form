<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    $adminId = (int)($_SESSION['user_id'] ?? 0);
    if ($adminId <= 0) {
        header("Location: ../public/login_user.php");
        exit;
    }

    $error = '';
    $success = '';

    $stmtImg = $pdo->prepare("SELECT id, file_path FROM profile_photos WHERE user_id = :id ORDER BY id DESC LIMIT 1");
    $stmtImg->execute([':id' => $adminId]);
    $fileRow = $stmtImg->fetch(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
        if ($fileRow) {
            $p = trim((string)($fileRow['file_path'] ?? ''));
            $fullPath = '../public/' . $p;
            if ($p !== '' && file_exists($fullPath)) {
                @unlink($fullPath);
            }

            $pdo->prepare("DELETE FROM profile_photos WHERE id = :id")->execute([':id' => (int)$fileRow['id']]);
        }
        header("Location: admin_profile.php?success=" . urlencode('Profile photo deleted'));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $name = trim((string)($_POST['name'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));

        if ($name === '' || !preg_match("/^[a-zA-Z-' ]{3,50}$/", $name)) {
            $error = 'Invalid name';
        } elseif ($mobile === '' || !preg_match('/^\+?[1-9]\d{7,14}$/', $mobile)) {
            $error = 'Invalid mobile number';
        } else {
            $pdo->prepare("UPDATE users SET name = :n, mobile = :m WHERE id = :id")
                ->execute([':n' => $name, ':m' => $mobile, ':id' => $adminId]);

            if (!empty($_FILES['profile_img']['name'])) {
                $ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png'];

                if (!in_array($ext, $allowed, true)) {
                    $error = 'Invalid image type';
                } elseif (!empty($_FILES['profile_img']['tmp_name']) && !@getimagesize($_FILES['profile_img']['tmp_name'])) {
                    $error = 'Invalid image file';
                } elseif (!empty($_FILES['profile_img']['size']) && (int)$_FILES['profile_img']['size'] > 2097152) {
                    $error = 'Image too large (max 2MB)';
                } elseif ((int)($_FILES['profile_img']['error'] ?? 0) !== UPLOAD_ERR_OK) {
                    $error = 'Image upload failed';
                } else {
                    $fileName = uniqid('img_', true) . '.' . $ext;
                    $dbPath = 'uploads/' . $fileName;
                    $uploadDir = '../public/uploads';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $fullPath = $uploadDir . '/' . $fileName;

                    if (!move_uploaded_file($_FILES['profile_img']['tmp_name'], $fullPath)) {
                        $error = 'Unable to save uploaded image';
                    } else {
                        $pdo->prepare("INSERT INTO profile_photos (user_id, file_name, file_path, file_type) VALUES (:uid,:fn,:fp,:ft)")
                            ->execute([':uid'=>$adminId, ':fn'=>$fileName, ':fp'=>$dbPath, ':ft'=>$ext]);
                    }
                }
            }

            if ($error === '') {
                header("Location: admin_profile.php?success=" . urlencode('Profile updated successfully'));
                exit;
            }
        }
    }

    if (!empty($_GET['error'])) {
        $error = (string)$_GET['error'];
    }
    if (!empty($_GET['success'])) {
        $success = (string)$_GET['success'];
    }

    $stmt = $pdo->prepare("SELECT name, email, mobile, role, status FROM users WHERE id = :id");
    $stmt->execute([':id' => $adminId]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        die("Admin not found");
    }

    $stmtImg->execute([':id' => $adminId]);
    $fileRow = $stmtImg->fetch(PDO::FETCH_ASSOC);
    $profileImageUrl = null;
    if ($fileRow && !empty($fileRow['file_path'])) {
        $profileImageUrl = '../public/' . (string)$fileRow['file_path'];
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <style>
        .iti{width:100%}
        .iti input.form-control{padding-left:90px!important}
    </style>
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="row min-vh-100">
        <div class="col-md-2 bg-dark text-white p-3 d-flex flex-column">
            <h5 class="text-center mb-4">Admin Panel</h5>
            <hr class="text-secondary">

            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a href="admin_dashboard.php" class="nav-link text-white">Dashboard</a>
                </li>

                <li class="nav-item mb-2">
                    <a href="admin_profile.php" class="nav-link text-white">Manage Profile</a>
                </li>

                <li class="nav-item mb-2">
                    <a href="show_users.php" class="nav-link text-white">Manage Users</a>
                </li>

                <li class="nav-item mb-3 mt-3">
                    <a href="user_create.php" class="btn btn-success btn-sm w-100">+ Add User</a>
                </li>

                <li class="nav-item mb-3">
                    <a href="../public/logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
                </li>
            </ul>
        </div>


        <div class="col-md-10">
            <div class="d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 80px);">
                <div class="card shadow-sm border-0" style="width: 480px;">
                    <div class="card-body text-center p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger text-start"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success text-start"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <?php if ($profileImageUrl): ?>
                            <img src="<?= htmlspecialchars($profileImageUrl) ?>" class="rounded-circle img-thumbnail mb-3" style="width:140px;height:140px;object-fit:cover;" alt="Profile photo">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:140px;height:140px;font-size:48px;">
                                <?= strtoupper(substr((string)$admin['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <h4 class="mb-1"><?= htmlspecialchars($admin['name']) ?></h4>
                        <p class="text-muted mb-1"><?= htmlspecialchars($admin['email']) ?></p>
                        <p class="text-muted mb-2"><?= htmlspecialchars((string)($admin['mobile'] ?? '')) ?></p>
                        

                        <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
                            <?php if ($profileImageUrl): ?>
                                <form method="post" onsubmit="return confirm('Are you sure you want to delete your profile photo?');">
                                    <button type="submit" name="delete_photo" value="1" class="btn btn-warning btn-sm">Delete Profile Photo</button>
                                </form>
                            <?php else: ?>
                                <a href="#profile_img" class="btn btn-warning btn-sm">Add Profile Photo</a>
                            <?php endif; ?>

                            <a href="../public/change_password.php" class="btn btn-primary btn-sm">Change Password</a>
                        </div>

                        <form method="post" enctype="multipart/form-data" class="text-start">
                            <div class="mb-2">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="name" value="<?= htmlspecialchars((string)$admin['name']) ?>" required>
                                <span class="text-danger" id="nameClientErr"></span>
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Mobile</label>
                                <input type="tel" class="form-control" name="mobile" id="mobile" value="<?= htmlspecialchars((string)($admin['mobile'] ?? '')) ?>" required>
                                <span class="text-danger" id="mobileClientErr"></span>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Profile Image</label>
                                <input type="file" class="form-control" name="profile_img" id="profile_img" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <small class="text-muted">Allowed file extensions: .jpg, .jpeg and .png. File size cannot exceed 2 MB.</small>
                                <span class="text-danger" id="imgClientErr"></span>
                            </div>
                            <button type="submit" name="update_profile" value="1" class="btn btn-success w-100">Update Profile</button>
                        </form>
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
            return setErr(imgInput, imgErr, 'Image too large (max 2MB).');
        }
        return setErr(imgInput, imgErr, '');
    }

    nameInput.addEventListener('blur', validateName);
    mobileInput.addEventListener('blur', validateMobile);
    imgInput.addEventListener('change', validateImage);

    document.querySelector('form[enctype="multipart/form-data"]').addEventListener('submit', function(e){
        const ok = validateName() & validateMobile() & validateImage();
        if(!ok){
            e.preventDefault();
            e.stopPropagation();
            return;
        }

        mobileInput.value = iti.getNumber();
    });
</script>
