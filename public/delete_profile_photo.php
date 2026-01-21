<?php
    session_start();
    require_once "../config/db.php";

    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: user_dashboard.php");
        exit;
    }

    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id, file_path FROM user_files WHERE user_id = :id ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute(['id' => $userId]);
    $file = $stmt->fetch();

    if ($file) {
        $p = trim((string)($file['file_path'] ?? ''));
        if (str_starts_with($p, '../public/')) {
            $p = substr($p, strlen('../public/'));
        } elseif (str_starts_with($p, '../')) {
            $p = substr($p, strlen('../'));
        }
        $p = ltrim($p, '/\\');
        $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $p;
        if ($p !== '' && file_exists($fullPath)) {
            unlink($fullPath);
        }

        $pdo->prepare("DELETE FROM user_files WHERE id = :id")
            ->execute(['id' => $file['id']]);
    }

    header("Location: user_dashboard.php");
    exit;
?>