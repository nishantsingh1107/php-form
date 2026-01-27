<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT p.id, p.title, p.description, p.status, p.admin_status, p.created_at, MIN(pi.file_path) AS thumbnail FROM posts p LEFT JOIN post_images pi ON pi.post_id = p.id WHERE p.user_id = :uid GROUP BY p.id ORDER BY p.created_at DESC");
    $stmt->execute([':uid' => $userId]);

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $public = array_filter($posts, fn($p) => $p['status'] === 'public');
    $hidden = array_filter($posts, fn($p) => $p['status'] === 'hidden');

    $activeTab = (string)($_GET['tab'] ?? 'public');
    if ($activeTab !== 'public' && $activeTab !== 'hidden') {
        $activeTab = 'public';
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Posts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include "partials/navbar.php"; ?>

<div class="container-fluid">
    <div class="row min-vh-100">
        <?php include "partials/sidebar.php"; ?>
        <div class="col-md-10 p-4">
            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-3">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            <h4 class="mb-4">My Posts</h4>
            <ul class="nav nav-tabs mb-4" id="postTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link text-dark <?= $activeTab === 'public' ? 'active' : '' ?>" id="public-tab" data-bs-toggle="tab" data-bs-target="#public" type="button">
                        Public (<?= count($public) ?>)
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link text-dark <?= $activeTab === 'hidden' ? 'active' : '' ?>" id="hidden-tab" data-bs-toggle="tab" data-bs-target="#hidden" type="button">
                        Hidden (<?= count($hidden) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade <?= $activeTab === 'public' ? 'show active' : '' ?>" id="public">
                    <?php if (empty($public)): ?>
                        <div class="alert alert-info">No public posts.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($public as $post): ?>
                                <?php include "partials/post_cards.php"; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade <?= $activeTab === 'hidden' ? 'show active' : '' ?>" id="hidden">
                    <?php if (empty($hidden)): ?>
                        <div class="alert alert-warning">No hidden posts.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <?php foreach ($hidden as $post): ?>
                                <?php include "partials/post_cards.php"; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>