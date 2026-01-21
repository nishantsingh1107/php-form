<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";
    
    $stmt = $pdo->prepare("SELECT u.name, u.email, u.role, u.status," . "(SELECT file_path FROM user_files WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS file_path" . " FROM users u WHERE u.id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        die("Admin not found");
    }

    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status = 'active'")->fetchColumn();
    $inactiveUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user' AND status <> 'active'")->fetchColumn();

    $recentUsers = $pdo->query("SELECT id, name, email, role, status FROM users WHERE role = 'user' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

    $recentVerified = [];
    try {
        $recentVerified = $pdo->query("SELECT id, name, email FROM users WHERE  role = 'user' AND email_verified = 1 ORDER BY id DESC LIMIT 5")
            ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentVerified = [];
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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


        <div class="col-md-10 p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="text-muted">Total Users</div>
                            <div class="fs-3 fw-bold"><?= $totalUsers ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="text-muted">Active Users</div>
                            <div class="fs-3 fw-bold text-success"><?= $activeUsers ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="text-muted">Inactive Users</div>
                            <div class="fs-3 fw-bold text-danger"><?= $inactiveUsers ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white fw-semibold">Admin</div>
                        <div class="card-body text-center">
                            <div class="mb-2">
                                <?php if (!empty($admin['file_path'])): ?>
                                    <img src="../public/<?= htmlspecialchars($admin['file_path']) ?>" class="rounded-circle" style="width:80px;height:80px;object-fit:cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width:80px;height:80px;">
                                        <?= strtoupper(substr($admin['name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h5><?= htmlspecialchars($admin['name']) ?></h5>
                            <p class="text-muted mb-1"><?= htmlspecialchars($admin['email']) ?></p>
                            <div class="mt-2 d-flex justify-content-center align-items-center gap-2">
                                <span class="badge bg-info text-dark fs-6">
                                    <?= ucfirst($admin['role']) ?>
                                </span>
                                <span class="badge <?= $admin['status'] === 'active' ? 'bg-success' : 'bg-danger' ?> fs-6">
                                    <?= ucfirst($admin['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold">Recently Registered Users</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($recentUsers)): foreach ($recentUsers as $u): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($u['name']) ?></td>
                                            <td><?= htmlspecialchars($u['email']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
                                            <td>
                                                <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= htmlspecialchars(ucfirst($u['status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No users found</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white fw-semibold">Recently Verified Emails</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!empty($recentVerified)): foreach ($recentVerified as $v): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($v['name']) ?></td>
                                            <td><?= htmlspecialchars($v['email']) ?></td>
                                        </tr>
                                    <?php endforeach; else: ?>
                                        <tr><td colspan="2" class="text-center text-muted">No verified emails found</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
