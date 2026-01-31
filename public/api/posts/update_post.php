<?php
    require_once __DIR__ . '/../helpers/_bootstrap.php';
    require_login();

    $userId = (int)$_SESSION['user_id'];

    $postId = (int)($_POST['post_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if($postId <= 0){
        api_error("Invalid post ID", 400);
    }

    if($title === '' || strlen($title) < 3 || strlen($title) > 150){
        api_error("Title length should be 3-150 characters", 400);
    }

    if(strlen($description) > 1000){
        api_error("Description cannot exceed 1000 characters", 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = :pid AND user_id = :uid AND admin_status = :status LIMIT 1");
    $stmt->execute([
        ':pid' => $postId,
        ':uid' => $userId,
        ':status' => 'approved'
    ]);

    if(!$stmt->fetch()){
        api_error("Post not found or access denied", 404);
    }

    $normalizedFiles = null;
    $validFiles = [];
    if (!empty($_FILES['new_images']) && isset($_FILES['new_images']['name'])) {
        $normalizedFiles = $_FILES['new_images'];

        if (!is_array($normalizedFiles['name'])) {
            foreach ($normalizedFiles as $key => $value) {
                $normalizedFiles[$key] = [$value];
            }
        }

        if(empty($normalizedFiles['name'][0]) || (count($normalizedFiles['error']) === 1 && $normalizedFiles['error'][0] === UPLOAD_ERR_NO_FILE)){
            $normalizedFiles = null;
        }
    }

    if ($normalizedFiles !== null){
        $count = count($normalizedFiles['name']);
        // if ($count < 1){
        //     api_error("No images received", 400);
        // }

        $allowedMime = ['image/jpeg', 'image/png'];
        $maxSize = 2 * 1024 * 1024;

        for($i=0;$i<$count;$i++){
            if(!isset($normalizedFiles['tmp_name'][$i]) || !isset($normalizedFiles['size'][$i]) || !isset($normalizedFiles['error'][$i])){
                api_error("Invalid upload payload", 400);
            }

            if($normalizedFiles['error'][$i] !== UPLOAD_ERR_OK){
                api_error("One or more files failed to upload. Please try again.", 400);
            }

            $tmp = $normalizedFiles['tmp_name'][$i];
            $size = (int)$normalizedFiles['size'][$i];

            if(!is_uploaded_file($tmp)){
                api_error("Invalid file upload detected.", 400);
            }
            if($size>$maxSize){
                api_error("Each image must be under 2MB.", 400);
            }

            $mime = mime_content_type($tmp);
            if(!in_array($mime, $allowedMime, true)){
                api_error("Only JPG and PNG images are allowed.", 400);
            }

            if(!getimagesize($tmp)){
                api_error("One or more files are not valid images.", 400);
            }
            $validFiles[] = ['tmp' => $tmp, 'size' => $size, 'mime' => $mime];
        }

        if(!empty($validFiles)){
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM post_images WHERE post_id = :pid");
            $countStmt->execute([':pid' => $postId]);
            $currentCount = (int)$countStmt->fetchColumn();

            $newCount = count($validFiles);
            if ($currentCount + $newCount > 5) {
                $canUpload = max(0, 5 - $currentCount);
                api_error("Maximum 5 images allowed per post. You can upload only $canUpload more image(s).", 400);
            }
        }
    }

    $savedFiles = [];
    try{
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE posts SET title = :title, description = :description WHERE id = :pid AND user_id = :uid")->execute([
            ':title' => $title,
            ':description' => $description,
            ':pid' => $postId,
            ':uid' => $userId
        ]);

        if(!empty($validFiles)){
            $existingStmt = $pdo->prepare("SELECT id FROM post_images WHERE post_id = :pid ORDER BY position ASC, id ASC");
            $existingStmt->execute([':pid' => $postId]);
            $existingIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

            $pos = 1;
            $updatePosStmt = $pdo->prepare("UPDATE post_images SET position = :pos WHERE id = :id");
            foreach ($existingIds as $imgId) {
                $updatePosStmt->execute([
                    ':pos' => $pos++,
                    ':id'  => (int)$imgId
                ]);
            }

            $baseDir = __DIR__ . "/../../uploads/posts/{$userId}/{$postId}/";
            if (!is_dir($baseDir)) {
                if (!mkdir($baseDir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }

            foreach($validFiles as $file){
                if($pos>5){
                    throw new Exception("Image position overflow");
                }

                $ext = ($file['mime'] === 'image/png') ? 'png' : 'jpg';
                $fileName = uniqid('img_', true) . '.' . $ext;
                $targetPath = $baseDir . $fileName;

                if (!move_uploaded_file($file['tmp'], $targetPath)) {
                    throw new Exception("Failed to save image");
                }
                $savedFiles[] = $targetPath;

                $relativePath = "uploads/posts/{$userId}/{$postId}/{$fileName}";

                $insertStmt = $pdo->prepare("INSERT INTO post_images (post_id, file_path, mime, size_bytes, position) VALUES (:pid, :path, :mime, :size, :pos)");
                $insertStmt->execute([
                    ':pid'  => $postId,
                    ':path' => $relativePath,
                    ':mime' => $file['mime'],
                    ':size' => $file['size'],
                    ':pos'  => $pos++
                ]);
            }
        }

        $pdo->commit();

        api_success([
            'message' => 'Post updated successfully',
            'post_id' => $postId
        ]);

    }catch(Throwable $e){
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }

        foreach ($savedFiles as $file) {
            if (is_string($file) && file_exists($file)) {
                @unlink($file);
            }
        }
        api_error("Something went wrong. Please try again.", 500);
    }
?>