<?php
    require_once __DIR__ . "/../helpers/_bootstrap.php";
    $jwtData = require_access_token();
    $userId = (int)$jwtData['sub'];

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if($title === '' || strlen($title) < 3 || strlen($title) > 150){
        api_error("Title length should be 3-150 characters", 400);
    }

    if(strlen($description) > 1000){
        api_error("Description cannot exceed 1000 characters", 400);
    }

    validate_post_images($_FILES['post_images']);
    $uploadedFiles = [];

    try{
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description) VALUES (:uid, :title, :des)");
        $stmt->execute([
            ":uid" => $userId,
            ":title" => $title, 
            ":des" => $description
        ]);

        $postId = (int)$pdo->lastInsertId();
        $baseDir = __DIR__ . "/../../uploads/posts/{$userId}/{$postId}/";
        if(!is_dir($baseDir)){
            mkdir($baseDir, 0755, true);
        }

        $files = $_FILES['post_images'];
        $position = 1;
        for($i=0;$i<count($files['name']);$i++){
            $tmp = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];
            $mime = mime_content_type($tmp);

            $ext = $mime === 'image/png' ? 'png' : 'jpg';

            $fileName = uniqid('img_', true) . '.' . $ext;
            $destination = $baseDir . $fileName;

            if(!move_uploaded_file($tmp, $destination)){
                throw new Exception("Failed to save image");
            }

            $relativePath = "uploads/posts/{$userId}/{$postId}/{$fileName}";
            $uploadedFiles[] = $destination;

            $stmt = $pdo->prepare("INSERT INTO post_images(post_id, file_path, mime, size_bytes, position) VALUES (:pid, :filePath, :mime, :size, :pos)");
            $stmt->execute([
                ":pid" => $postId,
                ":filePath" => $relativePath,
                ":mime" => $mime,
                ":size" => $size,
                ":pos" => $position++
            ]);

            }
        $pdo->commit();
        
        api_success([
            "message" => "Post created successfully",
            "post_id" => $postId
        ], 201);
    }catch(Throwable $e){
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    
        foreach ($uploadedFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        api_error("Something went wrong. Please try again.", 500);
    }
?>