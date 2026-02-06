<?php
    require_once __DIR__ . "/token_guard.php";
    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT post_credits FROM users WHERE id = :uid");
    $stmt->execute([":uid" => $userId]);
    $post_credit = $stmt->fetchColumn();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container">
        <a class="navbar-brand" href="user_dashboard.php">MyApp</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNavbar">
            <ul class="navbar-nav ms-auto gap-2">
                <li class="nav-item d-flex align-items-center">
                    <span class="nav-link text-white small pe-0">
                        Post credits left:
                        <strong class="ms-1" id="postCreditsLeft"><?= htmlspecialchars((string)$post_credit) ?></strong>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="user_profile.php">Profile</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="change_password.php">Change Password</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-bold text-danger" href="logout.php">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div style="height: 56px;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>