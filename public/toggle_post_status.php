<?php
    session_start();
    require_once "../config/db.php";

    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: my_posts.php");
        exit;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $postId = (int)($_POST['post_id'] ?? 0);
    if ($userId <= 0 || $postId <= 0) {
        header("Location: my_posts.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, user_id, status FROM posts WHERE id = :pid LIMIT 1");
        $stmt->execute([':pid' => $postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$post || (int)$post['user_id'] !== $userId) {
            header("Location: my_posts.php");
            exit;
        }

        $currentStatus = (string)($post['status'] ?? 'public');
        $newStatus = ($currentStatus === 'public') ? 'hidden' : 'public';

        $update = $pdo->prepare("UPDATE posts SET status = :status WHERE id = :pid AND user_id = :uid");
        $update->execute([
            ':status' => $newStatus,
            ':pid' => $postId,
            ':uid' => $userId,
        ]);

        header("Location: my_posts.php?tab=" . urlencode($newStatus === 'hidden' ? 'hidden' : 'public'));
        exit;
    } catch (Throwable $e) {
        header("Location: my_posts.php");
        exit;
    }
?>