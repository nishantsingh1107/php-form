<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }

    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_posts, SUM(status = 'public') AS public_posts, SUM(status = 'hidden') AS hidden_posts FROM posts WHERE user_id = :uid");
    $stmt->execute([
        ":uid" => $userId
    ]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);


    $stmt = $pdo->prepare("SELECT p.id, p.title, p.description, p.status, p.created_at, MIN(pi.file_path) AS thumbnail FROM posts p LEFT JOIN post_images pi ON pi.post_id = p.id WHERE p.user_id = :uid GROUP BY p.id ORDER BY p.created_at DESC LIMIT 6");
    $stmt->execute([":uid" => $userId]);
    $recentPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include "partials/navbar.php"; ?>
<div class="container-fluid">
    <div class="row min-vh-100">
        <?php include "partials/sidebar.php"; ?>
        <div class="col-md-10 p-4">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="text-muted">Total Posts</div>
                            <div class="fs-3 fw-bold"><?= (int)$stats['total_posts'] ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="text-muted">Published Posts</div>
                            <div class="fs-3 fw-bold text-success">
                                <?= (int)$stats['public_posts'] ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="text-muted">Hidden Posts</div>
                            <div class="fs-3 fw-bold text-warning">
                                <?= (int)$stats['hidden_posts'] ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <h3 class="mb-3">Recent Posts</h3>

            <?php if (empty($recentPosts)): ?>
                <div class="alert alert-info">No posts created yet.</div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($recentPosts as $post): ?>
                        <div class="col-md-4">
                            <a href="view_post.php?id=<?= (int)$post['id'] ?>" class="text-decoration-none text-dark">
                                <div class="card h-100 shadow-sm">
                                    <?php if ($post['thumbnail']): ?>
                                        <img src="../public/<?= htmlspecialchars($post['thumbnail']) ?>" class="card-img-top" style="height:180px;object-fit:cover;">
                                    <?php else: ?>
                                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" style="height:180px;">
                                            No Image
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-body">
                                        <h6 class="mb-1"><?= htmlspecialchars($post['title']) ?></h6>
                                        <p class="text-muted small"><?= htmlspecialchars(mb_strimwidth($post['description'], 0, 120, '...')) ?></p>
                                        <span class="badge <?= $post['status'] === 'public' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                            <?= ucfirst($post['status']) ?>
                                        </span>
                                        <div class="text-muted small mt-1">
                                            <?= date('d M Y', strtotime($post['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>