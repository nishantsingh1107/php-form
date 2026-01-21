<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login_user.php");
    exit;
}

require_once "../config/db.php";

$userId = (int)$_SESSION['user_id'];
if ($userId <= 0) {
    session_destroy();
    header("Location: ../public/login_user.php");
    exit;
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || (($row['role'] ?? '') !== 'admin')) {
    header("Location: ../public/login_user.php");
    exit;
}
