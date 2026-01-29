<?php
    require __DIR__ . '/../helpers/_bootstrap.php';
    require_login();

    $input = json_decode(file_get_contents("php://input"), true);
    $password = $input['password'] ?? '';

    if ($password === '') {
        api_error("Password is required", 400);
    }

    $userId = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        api_error("User not found", 404);
    }

    if (!password_verify($password, (string)$user['password'])) {
        api_error("Incorrect password", 401);
    }

    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $userId]);

    session_destroy();
    
    api_success([
        "message" => "Account deleted successfully"
    ]);
?>