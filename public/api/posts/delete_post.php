<?php
    require_once __DIR__ . '/../helpers/_bootstrap.php';
    require_login();

    $userId = (int)$_SESSION['user_id'];
    $input = json_decode(file_get_contents("php://input"), true);
    $postIds = $input['post_ids'] ?? [];

    if(!is_array($postIds) || empty($postIds)){
        api_error("post_ids must be a non-empty array", 400);
    }

    $postIds = array_values(array_unique(array_map('intval', $postIds)));
    $postIds = array_filter($postIds, fn($id) => $id > 0);

    if(empty($postIds)){
        api_error("Invalid post IDs", 400);
    }

    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $checkStmt = $pdo->prepare("SELECT id FROM posts WHERE id IN ($placeholders) AND user_id = ? AND admin_status = 'approved'");
    $checkStmt->execute([...$postIds, $userId]);
    $validPosts = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

    if(count($validPosts) !== count($postIds)){
        api_error("One or more posts not found or access denied", 403);
    }

    $imgStmt = $pdo->prepare("SELECT post_id, file_path FROM post_images WHERE post_id IN ($placeholders)");
    $imgStmt->execute($postIds);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    try{
        $pdo->beginTransaction();
        $delStmt = $pdo->prepare("DELETE FROM posts WHERE id IN ($placeholders)");
        $delStmt->execute($postIds);

        $pdo->commit();

        foreach($images as $img){
            $fullPath = __DIR__ . '/../../' . $img['file_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        foreach($postIds as $pid){
            $postDir = __DIR__ . "/../../uploads/posts/{$userId}/{$pid}";
            if (is_dir($postDir)) {
                @rmdir($postDir);
            }
        }

        api_success([
            "message" => "Posts deleted successfully",
            "deleted_count" => count($postIds)
        ]);

    }catch(Throwable $e){
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        api_error("Failed to delete posts. Please try again.", 500);
    }
?>