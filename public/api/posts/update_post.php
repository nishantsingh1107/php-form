<?php
    require_once __DIR__ . '/../helpers/_bootstrap.php';
    $jwtData = require_jwt_auth();
    $userId = (int)$jwtData['sub'];

    $postId = (int)($_POST['post_id'] ?? 0);
    
    $hasTitle       = array_key_exists('title', $_POST);
    $hasDescription = array_key_exists('description', $_POST);

    $title       = $hasTitle ? trim($_POST['title']) : null;
    $description = $hasDescription ? trim($_POST['description']) : null;
    
    if($postId <= 0){
        api_error("Invalid post ID", 400);
    }

    if($hasTitle){
        if($title === '' || strlen($title) < 3 || strlen($title) > 150){
            api_error("Title length should be 3-150 characters", 400);
        }
    }

    if($hasDescription){
        if (strlen($description) > 1000) {
            api_error("Description cannot exceed 1000 characters", 400);
        }
    }

    $stmt = $pdo->prepare("SELECT id FROM posts WHERE id = :pid AND user_id = :uid AND admin_status = :status LIMIT 1");
    $stmt->execute([
        ':pid' => $postId,
        ':uid' => $userId,
        ':status' => 'approved'
    ]);

    if (!$stmt->fetch()) {
        api_error("Post not found or access denied", 404);
    }

    $normalizedFiles = null;
    $validFiles = [];

    if(!empty($_FILES['new_images']) && isset($_FILES['new_images']['name'])){
        $normalizedFiles = $_FILES['new_images'];

        if(!is_array($normalizedFiles['name'])){
            foreach ($normalizedFiles as $key => $value){
                $normalizedFiles[$key] = [$value];
            }
        }

        if(empty($normalizedFiles['name'][0]) || (count($normalizedFiles['error']) === 1 && $normalizedFiles['error'][0] === UPLOAD_ERR_NO_FILE)){
            $normalizedFiles = null;
        }
    }
    
    if($normalizedFiles !== null){
        $allowedMime = ['image/jpeg', 'image/png'];
        $maxSize = 2*1024*1024;

        for($i=0;$i<count($normalizedFiles['name']);$i++){
            if($normalizedFiles['error'][$i] !== UPLOAD_ERR_OK){
                api_error("One or more files failed to upload", 400);
            }

            $tmp  = $normalizedFiles['tmp_name'][$i];
            $size = (int) $normalizedFiles['size'][$i];

            if(!is_uploaded_file($tmp)){
                api_error("Invalid file upload detected", 400);
            }

            if($size > $maxSize){
                api_error("Each image must be under 2MB", 400);
            }

            $mime = mime_content_type($tmp);
            if(!in_array($mime, $allowedMime, true)){
                api_error("Only JPG and PNG images are allowed", 400);
            }

            if(!getimagesize($tmp)){
                api_error("Invalid image file", 400);
            }
            $validFiles[] = ['tmp' => $tmp, 'size' => $size, 'mime' => $mime];
        }
    }

    if (!$hasTitle && !$hasDescription && empty($validFiles)) {
        api_error("Nothing to update", 400);
    }
    $savedFiles = [];
    try{
        $pdo->beginTransaction();
        $fields = [];
        $params = [
            ':pid' => $postId,
            ':uid' => $userId
        ];

        if($hasTitle){
            $fields[] = 'title = :title';
            $params[':title'] = $title;
        }

        if($hasDescription){
            $fields[] = 'description = :description';
            $params[':description'] = $description;
        }

        if (!empty($fields)) {
            $sql = "UPDATE posts SET " . implode(', ', $fields) . " WHERE id = :pid AND user_id = :uid";
            $pdo->prepare($sql)->execute($params);
        }

        if (!empty($validFiles)) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM post_images WHERE post_id = :pid");
            $countStmt->execute([':pid' => $postId]);
            $currentCount = (int) $countStmt->fetchColumn();

            if($currentCount + count($validFiles) > 5){
                api_error("Maximum 5 images allowed per post", 400);
            }

            $baseDir = __DIR__ . "/../../uploads/posts/{$userId}/{$postId}/";
            if (!is_dir($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            $pos = $currentCount + 1;

            foreach ($validFiles as $file) {
                $ext = ($file['mime'] === 'image/png') ? 'png' : 'jpg';
                $fileName = uniqid('img_', true) . '.' . $ext;
                $target = $baseDir . $fileName;

                move_uploaded_file($file['tmp'], $target);
                $savedFiles[] = $target;

                $relative = "uploads/posts/{$userId}/{$postId}/{$fileName}";

                $pdo->prepare("INSERT INTO post_images (post_id, file_path, mime, size_bytes, position) VALUES (:pid, :path, :mime, :size, :pos)")->execute([
                    ':pid'  => $postId,
                    ':path' => $relative,
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

        foreach($savedFiles as $file){
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        api_error("Something went wrong. Please try again.", 500);
    }
?>