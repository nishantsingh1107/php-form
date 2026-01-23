<?php
    if(!isset($_SESSION['user_id'])){
        header("Location: login_user.php");
        exit;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar</title>
    <style>
    .navbar-spacer {
        height: 56px;
    }
</style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="user_dashboard.php">MyApp</a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="userNavbar">
                <ul class="navbar-nav ms-auto gap-2">
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
    <div class="navbar-spacer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>