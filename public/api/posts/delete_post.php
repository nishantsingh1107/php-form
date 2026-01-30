<?php
    require_once __DIR__ . '/../helpers/_bootstrap.php';
    require_login();

    $userId = (int)$_SESSION['user_id'];

    $input  = json_decode(file_get_contents("php://input"), true);
    $postId = (int)($input['post_id'] ?? 0);

    if($postId <= 0){
        api_error("Invalid post ID", 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = :pid AND user_id = :uid AND admin_status = :status LIMIT 1");
    $stmt->execute([
        ':pid' => $postId,
        ':uid' => $userId,
        ':status' => 'approved'
    ]);

    $post = $stmt->fetch();
    if(!$post){
        api_error("Post not found or access denied", 404);
    }

    $imgStmt = $pdo->prepare("SELECT file_path FROM post_images WHERE post_id = :pid");
    $imgStmt->execute([':pid' => $postId]);
    $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

    try {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM posts WHERE id = :pid")->execute([':pid' => $postId]);
        $pdo->commit();

        foreach ($images as $path){
            $fullPath = __DIR__ . '/../../' . $path;
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $postDir = __DIR__ . "/../../uploads/posts/{$userId}/{$postId}";
        if(is_dir($postDir)){
            @rmdir($postDir);
        }

        api_success([
            "message" => "Post deleted successfully"
        ]);

    } catch(Throwable $e){
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        api_error("Failed to delete post. Please try again.", 500);
    }
?>