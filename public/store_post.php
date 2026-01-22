<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $errors = [];
    $uploadedFiles = [];

    if($title === '' || strlen($title) < 3 || strlen($title) > 150){
        $errors['title'] = "Title must be between 3 and 150 characters.";
    }
    if(strlen($description) > 1000){
        $errors['description'] = "Description cannot exceed 1000 characters.";
    }

    if(empty($_FILES['post_images']) || empty($_FILES['post_images']['name'][0])){
        $errors['post_images'] = "At least one image is required.";
    }elseif(count($_FILES['post_images']['name']) > 5){
        $errors['post_images'] = "You can upload a maximum of 5 images.";
    }
    if($errors) {
        $_SESSION['post_errors'] = $errors;
        $_SESSION['old_post'] = [
            'title' => $title,
            'description' => $description
        ];
        header("Location: create_post.php");
        exit;
    }

    $allowedMime = ['image/jpeg', 'image/png'];
    $allowedExt  = ['jpg', 'jpeg', 'png'];
    $maxSize = 2 * 1024 * 1024;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, description) VALUES (:uid, :title, :description)");
        $stmt->execute([
            ':uid' => $userId,
            ':title' => $title,
            ':description' => $description
        ]);

        $postId = (int)$pdo->lastInsertId();

        $baseDir = "../public/uploads/posts/$userId/$postId/";
        if(!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $files = $_FILES['post_images'];
        $position = 1;

        for ($i = 0; $i < count($files['name']); $i++) {

            if($files['error'][$i] !== UPLOAD_ERR_OK) {
                throw new Exception("Upload error");
            }

            $tmp  = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];
            $mime = mime_content_type($tmp);
            $ext  = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));

            if(!getimagesize($tmp)) {
                throw new Exception("Invalid image file");
            }

            if(!in_array($mime, $allowedMime, true)) {
                throw new Exception("Invalid image type");
            }

            if(!in_array($ext, $allowedExt, true)) {
                throw new Exception("Invalid image extension");
            }

            if($size > $maxSize) {
                throw new Exception("Image too large");
            }

            $fileName = uniqid('img_', true) . '.' . ($mime === 'image/png' ? 'png' : 'jpg');
            $destination = $baseDir . $fileName;

            if(!move_uploaded_file($tmp, $destination)) {
                throw new Exception("Failed to save image");
            }

            $uploadedFiles[] = $destination;

            $pdo->prepare("INSERT INTO post_images (post_id, file_path, mime, size_bytes, position) VALUES (:pid, :path, :mime, :size, :pos)")->execute([
                ':pid'  => $postId,
                ':path' => "uploads/posts/$userId/$postId/$fileName",
                ':mime' => $mime,
                ':size' => $size,
                ':pos'  => $position++
            ]);
        }

        $pdo->commit();
        unset($_SESSION['old_post']);
        header("Location: my_posts.php");
        exit;

    } catch (Throwable $e) {

        if($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        foreach ($uploadedFiles as $f) {
            if(file_exists($f)) {
                @unlink($f);
            }
        }

        $_SESSION['post_errors'] = ['general' => 'Something went wrong. Please try again.'];
        header("Location: create_post.php");
        exit;
    }
?>