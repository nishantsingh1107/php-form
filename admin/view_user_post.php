<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    $postId = (int)($_GET['post_id'] ?? 0);
    if ($postId <= 0) {
        die("Invalid post");
    }

    $stmt = $pdo->prepare("SELECT p.id, p.title, p.description, p.status, p.admin_status, p.created_at, p.user_id, u.name AS user_name FROM posts p JOIN users u ON u.id = p.user_id WHERE p.id = :pid");
    $stmt->execute([':pid' => $postId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        die("Post not found");
    }

    $imgStmt = $pdo->prepare("SELECT file_path FROM post_images WHERE post_id = :pid ORDER BY position ASC");
    $imgStmt->execute([':pid' => $postId]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" rel="stylesheet"/>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Post Details</h4>
                <a href="user_posts.php?user_id=<?= $post['user_id'] ?>" class="btn btn-secondary btn-sm">← Back</a>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-3">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h3 class="mb-1"><?= htmlspecialchars($post['title']) ?></h3>
                            <small class="text-muted"> By <strong><?= htmlspecialchars($post['user_name']) ?></strong> | <?= date('d M Y, h:i A', strtotime($post['created_at'])) ?>
                            </small>
                        </div>

                        <div class="text-end">
                            <span class="badge fs-6 <?= $post['status'] === 'public' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= ucfirst($post['status']) ?>
                            </span>
                            <span class="badge fs-6 <?= $post['admin_status'] === 'approved' ? 'bg-success' : 'bg-danger' ?>">
                                Admin: <?= ucfirst($post['admin_status']) ?>
                            </span>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted mb-2">Description</h6>
                        <?php if ($post['description']): ?>
                            <p><?= nl2br(htmlspecialchars($post['description'])) ?></p>
                        <?php else: ?>
                            <p class="text-muted fst-italic">No description provided.</p>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div>
                        <h6 class="text-uppercase text-muted mb-3">Images</h6>
                        <?php if (!$images): ?>
                            <div class="alert alert-info">No images available.</div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($images as $img): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <a href="../public/<?= htmlspecialchars($img['file_path']) ?>" data-fancybox="gallery">
                                            <img src="../public/<?= htmlspecialchars($img['file_path']) ?>" class="img-fluid rounded w-100" style="height:220px;object-fit:cover;">
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-end gap-2">
                        <?php if ($post['admin_status'] === 'blocked'): ?>
                            <form method="post" action="unblock_post.php">
                                <input type="hidden" name="user_id" value="<?= $post['user_id'] ?>">
                                <input type="hidden" name="post_ids[]" value="<?= $post['id'] ?>">
                                <button class="btn btn-success btn-sm" onclick="return confirm('Are you sure you want to unblock this post?');">Unblock the post</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="bulk_block_posts.php">
                                <input type="hidden" name="user_id" value="<?= $post['user_id'] ?>">
                                <input type="hidden" name="post_ids[]" value="<?= $post['id'] ?>">
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Block this post?');">Block the post</button>
                            </form>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
<script>
Fancybox.bind('[data-fancybox="gallery"]', {});
</script>

</body>
</html>