<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        $_SESSION['flash_error'] = 'Invalid user id.';
        header("Location: show_users.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Remove related uploaded file records first (prevents FK constraint failures).
        $pdo->prepare("DELETE FROM user_files WHERE user_id = :id")->execute([':id' => $id]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            $_SESSION['flash_success'] = 'User deleted successfully.';
        } else {
            $pdo->rollBack();
            $_SESSION['flash_error'] = 'User not found or already deleted.';
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_error'] = 'Error deleting user. Please try again.';
    }

    header("Location: show_users.php");
    exit;