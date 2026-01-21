<?php
    session_start();
    require_once "../config/db.php";

    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    $userId = $_SESSION['user_id'];

    $name   = trim((string)($_POST['name'] ?? ''));
    $mobile = trim((string)($_POST['mobile'] ?? ''));

    if ($name === '' || !preg_match("/^[a-zA-Z-' ]{3,50}$/", $name)) {
        header("Location: edit_profile.php?error=" . urlencode('Invalid name'));
        exit;
    }

    if ($mobile === '' || !preg_match('/^\+?[1-9]\d{7,14}$/', $mobile)) {
        header("Location: edit_profile.php?error=" . urlencode('Invalid mobile number'));
        exit;
    }

    $pdo->prepare(
        "UPDATE users SET name = :n, mobile = :m WHERE id = :id"
    )->execute([
        'n' => $name,
        'm' => $mobile,
        'id' => $userId
    ]);

    if (!empty($_FILES['profile_img']['name'])) {

        $ext = strtolower(pathinfo($_FILES['profile_img']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg','jpeg','png'];

        if (!in_array($ext, $allowed, true)) {
            header("Location: edit_profile.php?error=" . urlencode('Invalid image type'));
            exit;
        }

        if (!empty($_FILES['profile_img']['tmp_name']) && !@getimagesize($_FILES['profile_img']['tmp_name'])) {
            header("Location: edit_profile.php?error=" . urlencode('Invalid image file'));
            exit;
        }

        if (!empty($_FILES['profile_img']['size']) && (int)$_FILES['profile_img']['size'] > 2097152) {
            header("Location: edit_profile.php?error=" . urlencode('Image too large (max 2MB)'));
            exit;
        }

        if ((int)($_FILES['profile_img']['error'] ?? 0) !== UPLOAD_ERR_OK) {
            header("Location: edit_profile.php?error=" . urlencode('Image upload failed'));
            exit;
        }

        {
            $dir = "uploads/";
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $fileName = uniqid("img_", true).".".$ext;
            $path = $dir.$fileName;

            if (!move_uploaded_file($_FILES['profile_img']['tmp_name'], $path)) {
                header("Location: edit_profile.php?error=" . urlencode('Unable to save uploaded image'));
                exit;
            }

            $pdo->prepare( "INSERT INTO user_files (user_id, file_name, file_path, file_type) VALUES (:uid,:fn,:fp,:ft)" )->execute(['uid' => $userId, 'fn'  => $fileName, 'fp'  => $path, 'ft'  => $ext]);
        }
    }

    header("Location: edit_profile.php?success=" . urlencode('Profile updated successfully'));
    exit;
?>