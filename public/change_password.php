<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT password, must_change_password FROM users WHERE id = :id");
    $stmt->execute([":id" => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if(!$user){
        session_destroy();
        header("Location: login_user.php");
        exit;
    }

    $userRole = 'user';
    try {
        $stmtRole = $pdo->prepare("SELECT role FROM users WHERE id = :id");
        $stmtRole->execute([":id" => $_SESSION['user_id']]);
        $roleRow = $stmtRole->fetch(PDO::FETCH_ASSOC);
        if ($roleRow && isset($roleRow['role'])) {
            $userRole = (string)$roleRow['role'];
        }
    } catch (Throwable $e) {
        $userRole = 'user';
    }

    $error = $success = "";

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $old = (string)($_POST['old_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $cnf = (string)($_POST['confirm_password'] ?? '');

        if(!$user['must_change_password'] && trim($old) === ''){
            $error = "Current password is required.";
        }

        if(!$error && !preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/", $new)){
            $error = "Invalid password format.";
        }
        elseif(!$error && $new !== $cnf){
            $error = "Passwords do not match.";
        }

        elseif(!$error && !$user['must_change_password']){
            $hash = trim((string)($user['password'] ?? ''));

            if(!preg_match('/^\$2[aby]\$\d{2}\$/', $hash) || strlen($hash) < 55){
                $error = "Your saved password looks invalid. Please reset your password.";
            } elseif(!password_verify($old, $hash)){
                $error = "Current password incorrect.";
            }
        }

        if(!$error){
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = :p, must_change_password = 0 WHERE id = :id")->execute([':p' => $hash,':id' => $_SESSION['user_id']]);

            $_SESSION['password_change'] = true;
            $success = "Password updated successfully.";
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
<title>Change Password</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'partials/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row min-vh-100">
            <?php if ($userRole !== 'admin'): ?>
                <?php include 'partials/sidebar.php'; ?>
                <div class="col-md-10 p-4">
            <?php else: ?>
                <div class="col-12 p-4">
            <?php endif; ?>

                <div class="container mt-3" style="max-width: 420px;">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                        <h5 class="text-center mb-3 fw-semibold">Change Password</h5>

                <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if($user['must_change_password']): ?>
                    <div class="alert alert-info text-center">Your account was created by the administrator. Please change the default password to continue.</div>
                <?php endif; ?>

                <form method="post">
                    <?php if(!$user['must_change_password']): ?>
                        <div class="mb-2">
                            <label class="form-label" for="old_password">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="old_password" id="old_password" class="form-control" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw('old_password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <span class="text-danger" id="oldClientErr"></span>
                        </div>
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label" for="new_password">New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePw('new_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block">Use 8 characters with one uppercase, one lowercase, one number, and one special character.</small>
                        <span class="text-danger" id="newClientErr"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePw('confirm_password', this)">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <span class="text-danger" id="cnfClientErr"></span>
                    </div>
                    
                    <button class="btn btn-primary w-100">Update Password</button>
                </form>
                
                            <div class="text-center mt-3">
                                <a href="<?= ($userRole === 'admin') ? '../admin/admin_profile.php' : 'user_profile.php' ?>" class='btn btn-primary w-100'>Back</a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

<?php if (isset($_SESSION['password_change'])): ?>
<script>
setTimeout(function(){
    if(confirm("Password changed successfully.\nLogout now?")){
        window.location.href="logout.php";
    } else {
        window.location.href = <?= json_encode(($userRole === 'admin') ? '../admin/admin_dashboard.php' : 'user_dashboard.php') ?>;
    }
},300);
</script>
<?php unset($_SESSION['password_change']); endif; ?>

<script>
    const oldInput = document.getElementById('old_password');
    const newInput = document.getElementById('new_password');
    const cnfInput = document.getElementById('confirm_password');
    const oldErr = document.getElementById('oldClientErr');
    const newErr = document.getElementById('newClientErr');
    const cnfErr = document.getElementById('cnfClientErr');

    function setErr(el, errEl, msg){
        if(!el || !errEl) return true;
        errEl.textContent = msg || '';
        if (msg) el.classList.add('is-invalid');
        else el.classList.remove('is-invalid');
        return !msg;
    }

    function validatePasswords(){
        let ok = true;

        if(oldInput){
            const oldVal = (oldInput.value || '').trim();
            ok = setErr(oldInput, oldErr, oldVal.length === 0 ? 'Current password is required.' : '') && ok;
        }

        const newVal = (newInput.value || '');
        if(newVal.trim().length === 0){
            ok = setErr(newInput, newErr, 'New password is required.') && ok;
        }else{
            const patternOk = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d])[^\s]{8,}$/.test(newVal);
            ok = setErr(newInput, newErr, patternOk ? '' : 'Invalid password format.') && ok;
        }

        const cnfVal = (cnfInput.value || '');
        if(cnfVal.trim().length === 0){
            ok = setErr(cnfInput, cnfErr, 'Confirm password is required.') && ok;
        }else{
            ok = setErr(cnfInput, cnfErr, cnfVal === newVal ? '' : 'Passwords do not match.') && ok;
        }
        return ok;
    }

    if(oldInput) oldInput.addEventListener('blur', validatePasswords);
    newInput.addEventListener('blur', validatePasswords);
    cnfInput.addEventListener('blur', validatePasswords);

    document.querySelector('form').addEventListener('submit', function(e){
        if(!validatePasswords()){
            e.preventDefault();
            e.stopPropagation();
        }
    });

    function togglePw(id, btn){
        const input = document.getElementById(id);
        if(!input) return;
        const icon = btn.querySelector('i');
        if(input.type === 'password'){
            input.type = 'text';
            if(icon) icon.classList.replace('bi-eye','bi-eye-slash');
        }else{
            input.type = 'password';
            if(icon) icon.classList.replace('bi-eye-slash','bi-eye');
        }
    }
</script>

</body>
</html>
