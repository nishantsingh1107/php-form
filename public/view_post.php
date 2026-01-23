<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }

    $userId = $_SESSION['user_id'];
    $postId = $_GET['id'] ?? 0;

    if($postId <= 0){
        header("Location: my_post.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT title, description, status, created_at from posts WHERE id = :pid AND user_id = :uid");

    $stmt->execute([
        ":pid" => $postId,
        ":uid" => $userId
    ]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$post){
        header("Location: my_posts.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT file_path FROM post_images WHERE post_id = :pid ORDER BY position ASC");
    $stmt->execute([":pid" => $postId]);
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" rel="stylesheet"/>
    <style>
        .post-image {
            width: 800%;
            height: 220px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .post-image:hover {
            transform: scale(1.03);
        }
    </style>
</head>
<body>
<?php include "partials/navbar.php"; ?>
<div class="container-fluid">
    <div class="row min-vh-100">
        <?php include "partials/sidebar.php"; ?>
        <div class="col-md-10 p-4">
            <a href="my_posts.php" class="btn btn-dark btn-sm mb-3">← Back to My Posts</a>
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h3 class="mb-1"><?= htmlspecialchars($post['title']) ?></h3>
                            <small class="text-muted">
                                Created on <?= date('d M Y, h:i A', strtotime($post['created_at'])) ?>
                            </small>
                        </div>
                        <span class="badge fs-6 ms-auto <?= $post['status'] === 'public' ? 'bg-success' : 'bg-primary' ?>">
                            <?= ucfirst($post['status']) ?>
                        </span>
                        <a class="btn btn-outline-primary btn-sm ms-2" href="edit_post.php?id=<?= (int)$postId ?>">Edit Post</a>
                    </div><hr>
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted mb-2">Description</h6>
                        <?php if (!empty($post['description'])): ?>
                            <p class="mb-0">
                                <?= nl2br(htmlspecialchars($post['description'])) ?>
                            </p>
                        <?php else: ?>
                            <p class="text-muted fst-italic">No description provided.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h6 class="text-uppercase text-muted mb-3">Images</h6>
                        <?php if (empty($images)): ?>
                            <div class="alert alert-info">No images available.</div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($images as $img): ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="border rounded overflow-hidden">
                                            <img src="../public/<?= htmlspecialchars($img['file_path']) ?>" class="img-fluid post-image" alt="Post image">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
</body>
</html>
