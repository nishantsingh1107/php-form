<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login_user.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php include 'partials/sidebar.php'; ?>
        <div class="col-md-9 col-lg-10 p-4">
            <h4>Welcome</h4>
        </div>
    </div>
</div>

</body>
</html>