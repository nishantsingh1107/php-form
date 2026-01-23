<?php
    session_start();
    require_once "../config/db.php";

    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $postId = (int)($_POST['post_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if($postId <= 0){
        header("Location: my_posts.php");
        exit;
    }

    $errors = [];

    if($title === '' || mb_strlen($title) < 3 || mb_strlen($title) > 150){
        $errors['title'] = "Title must be 3–150 characters";
    }

    if(mb_strlen($description) > 1000){
        $errors['description'] = "Description too long";
    }

    if($errors){
        $_SESSION['edit_post_errors'] = $errors;
        header("Location: edit_post.php?id=$postId");
        exit;
    }

    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("SELECT 1 FROM posts WHERE id = :pid AND user_id = :uid");
    $stmt->execute([
        ':pid' => $postId,
        ':uid' => $userId
    ]);

    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        header("Location: my_posts.php");
        exit;
    }

    $pdo->prepare("UPDATE posts SET title = :title, description = :des WHERE id = :pid AND user_id = :uid")->execute([
        ':title' => $title,
        ':des'   => $description,
        ':pid'   => $postId,
        ':uid'   => $userId
    ]);

    if (!empty($_POST['delete_images'])) {
        $stmt = $pdo->prepare("SELECT id, file_path FROM post_images WHERE id = :imgId AND post_id = :pid");
        foreach ($_POST['delete_images'] as $imgId) {
            $imgId = (int)$imgId;
            if ($imgId <= 0) continue;

            $stmt->execute([
                ':imgId' => $imgId,
                ':pid'   => $postId
            ]);

            if ($img = $stmt->fetch(PDO::FETCH_ASSOC)) {
                @unlink("../public/" . $img['file_path']);
                $pdo->prepare("DELETE FROM post_images WHERE id = :id")
                    ->execute([':id' => $imgId]);
            }
        }
    }

    // Reindexing
    $stmt = $pdo->prepare("SELECT id FROM post_images WHERE post_id = :pid ORDER BY position ASC");
    $stmt->execute([':pid' => $postId]);

    $imageIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pos = 1;
    $upd = $pdo->prepare("UPDATE post_images SET position = :pos WHERE id = :id");

    foreach ($imageIds as $imgId) {
        $upd->execute([
            ':pos' => $pos++,
            ':id'  => $imgId
        ]);
    }

    // Replacing Existing Images
    if(!empty($_FILES['replace_image'])){
        $allowedMime = ['image/jpeg', 'image/png'];
        $maxSize = 2 * 1024 * 1024;
        $baseDir = "../public/uploads/posts/$userId/$postId/";

        foreach($_FILES['replace_image']['name'] as $imgId => $name){
            if (empty($name)) continue;
            $imgId = (int)$imgId;
            if (!isset($_FILES['replace_image']['error'][$imgId]) || $_FILES['replace_image']['error'][$imgId] !== UPLOAD_ERR_OK) {
                continue;
            }

            $stmt = $pdo->prepare("SELECT file_path FROM post_images WHERE id = :id AND post_id = :pid");
            $stmt->execute([
                ':id'  => $imgId,
                ':pid' => $postId
            ]);

            $img = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$img) continue;

            $tmp  = $_FILES['replace_image']['tmp_name'][$imgId] ?? '';
            $size = (int)($_FILES['replace_image']['size'][$imgId] ?? 0);
            if ($tmp === '' || !is_file($tmp)) {
                continue;
            }
            $mime = mime_content_type($tmp) ?: '';

            if (!getimagesize($tmp) || !in_array($mime, $allowedMime, true) || $size > $maxSize) {
                continue;
            }
            $ext = ($mime === 'image/png') ? 'png' : 'jpg';
            $newName = uniqid('img_', true) . '.' . $ext;

            if(!is_dir($baseDir)){
                mkdir($baseDir, 0755, true);
            }
            if (!move_uploaded_file($tmp, $baseDir . $newName)) {
                continue;
            }

            $pdo->prepare("UPDATE post_images SET file_path = :path, mime = :mime, size_bytes = :size WHERE id = :id")->execute([
                ':path' => "uploads/posts/$userId/$postId/$newName",
                ':mime' => $mime,
                ':size' => $size,
                ':id'   => $imgId
            ]);
            @unlink("../public/" . $img['file_path']);
        }
    }

    // Adding the new Images
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) FROM post_images WHERE post_id = :pid");
    $stmt->execute([':pid' => $postId]);
    $position = (int)$stmt->fetchColumn() + 1;

    $skipNewUploads = false;
    if (!empty($_FILES['new_images']['name'][0])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_images WHERE post_id = :pid");
        $stmt->execute([':pid' => $postId]);
        $currentCount = (int)$stmt->fetchColumn();
        $newCount = is_array($_FILES['new_images']['name']) ? count(array_filter($_FILES['new_images']['name'], fn($n) => (string)$n !== '')) : 0;

        if ($currentCount + $newCount > 5) {
            $skipNewUploads = true;
            $_SESSION['edit_post_errors'] = ['images' => 'You already have 5 images. Delete some first to upload new ones.'];
        }
    }

    if (!$skipNewUploads && !empty($_FILES['new_images']['name'][0])) {

        $allowedMime = ['image/jpeg', 'image/png'];
        $maxSize = 2 * 1024 * 1024;
        $files = $_FILES['new_images'];
        $baseDir = "../public/uploads/posts/$userId/$postId/";

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        // (Max count already validated above; keep this path focused on saving files.)

        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $tmp  = $files['tmp_name'][$i];
            $size = (int)$files['size'][$i];
            $mime = mime_content_type($tmp);

            if (!getimagesize($tmp) ||
                !in_array($mime, $allowedMime, true) ||
                $size > $maxSize) {
                continue;
            }

            $ext = ($mime === 'image/png') ? 'png' : 'jpg';
            $name = uniqid('img_', true) . '.' . $ext;

            move_uploaded_file($tmp, $baseDir . $name);

            $pdo->prepare("INSERT INTO post_images (post_id, file_path, mime, size_bytes, position) VALUES (:pid, :path, :mime, :size, :pos)")->execute([
                ':pid'  => $postId,
                ':path' => "uploads/posts/$userId/$postId/$name",
                ':mime' => $mime,
                ':size' => $size,
                ':pos'  => $position++
            ]);
        }
    }

    $pdo->commit();
    if ($skipNewUploads) {
        header("Location: edit_post.php?id=$postId");
        exit;
    }
    header("Location: view_post.php?id=$postId");
    exit;
?>