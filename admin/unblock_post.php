<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: show_users.php");
        exit;
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $postIds = $_POST['post_ids'] ?? [];

    if ($userId <= 0) {
        header("Location: show_users.php");
        exit;
    }

    $uStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id");
    $uStmt->execute([':id' => $userId]);
    if (!$uStmt->fetch(PDO::FETCH_ASSOC)) {
        header("Location: show_users.php");
        exit;
    }

    if (empty($postIds) || !is_array($postIds)) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Please select at least one post.'];
        header("Location: user_posts.php?user_id=" . $userId);
        exit;
    }

    $validPostIds = [];
    foreach ($postIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $validPostIds[] = $id;
        }
    }

    if (empty($validPostIds)) {
        $_SESSION['flash'] = ['type' => 'warning', 'message' => 'Please select at least one post.'];
        header("Location: user_posts.php?user_id=" . $userId);
        exit;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($validPostIds), '?'));
        // Verify all posts belong to the specified user
        $verifyStmt = $pdo->prepare("SELECT COUNT(*) as count FROM posts WHERE id IN ($placeholders) AND user_id = ?");
        $params = array_merge($validPostIds, [$userId]);
        $verifyStmt->execute($params);
        $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] != count($validPostIds)) {
            $_SESSION['flash'] = ['type' => 'danger', 'message' => 'One or more selected posts are invalid.'];
            header("Location: user_posts.php?user_id=" . $userId);
            exit;
        }

        // Unblock posts (set admin_status = 'approved')
        $unblockStmt = $pdo->prepare("UPDATE posts SET admin_status = 'approved' WHERE id IN ($placeholders)");
        $unblockStmt->execute($validPostIds);

        // Determine message based on count
        $count = count($validPostIds);
        $message = $count === 1 ? 'Post unblocked successfully.' : 'Selected posts unblocked successfully.';
        $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
        
        // Redirect back to view_user_post if single, user_posts if bulk
        if ($count === 1) {
            header("Location: view_user_post.php?post_id=" . $validPostIds[0]);
        } else {
            header("Location: user_posts.php?user_id=" . $userId);
        }
        exit;
    } catch (Throwable $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Database error, please try again.'];
        header("Location: user_posts.php?user_id=" . $userId);
        exit;
    }
?>
