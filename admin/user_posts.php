<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        die("Invalid user");
    }

    $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
    $uStmt->execute([':id' => $userId]);
    $user = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("User not found");
    }

    $stmt = $pdo->prepare("SELECT p.id, p.title, p.description, p.status, p.admin_status, p.created_at, MIN(pi.file_path) AS thumbnail FROM posts p LEFT JOIN post_images pi ON pi.post_id = p.id WHERE p.user_id = :uid GROUP BY p.id ORDER BY p.created_at DESC");
    $stmt->execute([':uid' => $userId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $approved = array_filter($posts, fn($p) => $p['admin_status'] === 'approved');
    $blocked = array_filter($posts, fn($p) => $p['admin_status'] === 'blocked');

    $activeTab = (string)($_GET['tab'] ?? 'approved');
    if (!in_array($activeTab, ['approved', 'blocked'])) {
        $activeTab = 'approved';
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Posts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="row min-vh-100">
        <div class="col-md-2 bg-dark text-white p-3">
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
                <li class="nav-item mt-4">
                    <a href="../public/logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
                </li>
            </ul>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Posts by <?= htmlspecialchars($user['name']) ?></h4>
                <a href="show_users.php" class="btn btn-secondary btn-sm">← Back</a>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-3">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <button class="nav-link <?= $activeTab==='approved'?'active':'' ?>"
                        data-bs-toggle="tab" data-bs-target="#approved">
                        Approved (<?= count($approved) ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?= $activeTab==='blocked'?'active':'' ?>"
                        data-bs-toggle="tab" data-bs-target="#blocked">
                        Blocked (<?= count($blocked) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content">

                <div class="tab-pane fade <?= $activeTab==='approved'?'show active':'' ?>" id="approved">
                    <form method="post" action="bulk_block_posts.php" id="blockForm">
                        <input type="hidden" name="user_id" value="<?= $userId ?>">
                        <?php if (empty($approved)): ?>
                            <div class="alert alert-info">No approved posts.</div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($approved as $post): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="card h-100 shadow-sm">

                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <input type="checkbox" name="post_ids[]" value="<?= $post['id'] ?>">
                                                <span class="badge bg-success">Approved</span>
                                            </div>

                                            <?php if ($post['thumbnail']): ?>
                                                <img src="../public/<?= htmlspecialchars($post['thumbnail']) ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                                            <?php endif; ?>
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($post['title']) ?></h6>
                                                <p class="text-muted small"><?= htmlspecialchars(mb_strimwidth($post['description'],0,90,'...')) ?></p>
                                                <span class="badge <?= $post['status']==='public'?'bg-success':'bg-secondary' ?>"><?= ucfirst($post['status']) ?></span>
                                            </div>
                                            <div class="card-footer bg-white">
                                                <a href="view_user_post.php?post_id=<?= $post['id'] ?>" class="btn btn-primary btn-sm w-100">View Full Post</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-4">
                                <button class="btn btn-danger" onclick="return confirm('Are you sure you want to block selected posts?')">Block Selected Posts</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="tab-pane fade <?= $activeTab==='blocked'?'show active':'' ?>" id="blocked">
                    <?php if (empty($blocked)): ?>
                        <div class="alert alert-info">No blocked posts.</div>
                    <?php else: ?>
                        <form method="post" action="unblock_post.php" id="unblockForm">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                                    <div class="row g-4">
                                    <?php foreach ($blocked as $post): ?>
                                        <div class="col-md-4 col-sm-6">
                                            <div class="card h-100 shadow-sm opacity-75">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <input type="checkbox" name="post_ids[]" value="<?= $post['id'] ?>">
                                                    <span class="badge bg-danger">Blocked</span>
                                                </div>
                                                <?php if ($post['thumbnail']): ?>
                                                    <img src="../public/<?= htmlspecialchars($post['thumbnail']) ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h6><?= htmlspecialchars($post['title']) ?></h6>
                                                    <p class="text-muted small">
                                                        <?= htmlspecialchars(mb_strimwidth($post['description'],0,90,'...')) ?>
                                                    </p>
                                                </div>
                                                <div class="card-footer bg-white">
                                                    <a href="view_user_post.php?post_id=<?= $post['id'] ?>" class="btn btn-primary btn-sm w-100">View Full Post</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4">
                                    <button class="btn btn-success" onclick="return confirm('Are you sure you want to unblock selected posts?')">Unblock Selected Posts</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('blockForm').addEventListener('submit', function (e) {
        if (!document.querySelector('input[name="post_ids[]"]:checked')) {
            e.preventDefault();
            alert('Please select at least one post to block.');
        }
    });

    document.getElementById('unblockForm').addEventListener('submit', function (e) {
        if (!document.querySelector('#unblockForm input[name="post_ids[]"]:checked')) {
            e.preventDefault();
            alert('Please select at least one post to unblock.');
        }
    });
</script>
</body>
</html>