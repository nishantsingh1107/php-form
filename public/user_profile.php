<?php
    session_start();
    require_once "../config/db.php";
    
    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }
    
    $userId    = $_SESSION['user_id'];
    $userName = '';
    $userEmail = '';
    $userMobile = '';
    $userRole = 'user';
    $profileImage = null;
    
    $stmt = $pdo->prepare("SELECT name, email, mobile, role FROM users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login_user.php");
        exit;
    }

    $userName = (string)($user['name'] ?? '');
    $userEmail = (string)($user['email'] ?? '');
    $userMobile = (string)($user['mobile'] ?? '');
    $userRole = (string)($user['role'] ?? 'user');

    if ($userRole === 'admin') {
        header("Location: ../admin/admin_dashboard.php");
        exit;
    }
    
    $stmtImg = $pdo->prepare(
        "SELECT file_path FROM user_files WHERE user_id = :id  ORDER BY id DESC LIMIT 1"
    );
    $stmtImg->execute(['id' => $userId]);
    $file = $stmtImg->fetch();
    
    if ($file) {
        $profileImage = $file['file_path'];
    }

    $profileImageUrl = null;
    if ($profileImage) {
        $p = (string)$profileImage;
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
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- <div class="container mt-3">
    <div class="d-flex justify-content-end gap-2">
        <a href="change_password.php" class="btn btn-outline-primary btn-sm">Change Password</a>
        <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>
</div> -->
<?php include 'partials/navbar.php'; ?>

<div class="container-fluid">
    <div class="row min-vh-100">
        <?php include 'partials/sidebar.php'; ?>
        <div class="col-md-10 p-4">
            <div class="container mt-3">
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-body text-center">
                                <?php if ($profileImageUrl): ?>
                                    <img src="<?= htmlspecialchars($profileImageUrl) ?>" class="rounded-circle img-thumbnail mb-3" style="width:140px;height:140px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width:140px;height:140px;font-size:48px;">
                                        <?= strtoupper(substr($userName, 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <h4 class="mb-1"><?= htmlspecialchars($userName) ?></h4>
                                <p class="text-muted mb-1"><?= htmlspecialchars($userEmail) ?></p>
                                <p class="text-muted mb-3"><?= htmlspecialchars($userMobile) ?></p>
                                <div class="d-flex justify-content-center gap-2 flex-wrap">
                                    <a href="edit_profile.php" class="btn btn-primary btn-sm">Edit Profile</a>
                                    <?php if ($profileImageUrl): ?>
                                        <form method="post" action="delete_profile_photo.php" onsubmit="return confirm('Are you sure you want to delete your profile photo?');">
                                            <button type="submit" class="btn btn-warning btn-sm">Delete Photo</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="edit_profile.php#profile_img" class="btn btn-warning btn-sm">Add Profile Photo</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
