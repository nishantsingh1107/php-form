<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login_user.php");
    exit;
}
?>
<div class="d-none d-md-block col-md-2"></div>

<aside class="d-none d-md-flex bg-dark text-white p-3 flex-column overflow-auto position-fixed" style="top:56px;left:0;height:calc(100vh - 56px);width:16.666667%; z-index:1020;">
    <ul class="nav flex-column">
        <li class="nav-item mb-2">
            <a href="user_dashboard.php" class="nav-link text-white">Dashboard</a>
        </li>
        <li class="nav-item mb-2">
            <a href="my_posts.php" class="nav-link text-white">My Posts</a>
        </li>
        <li class="nav-item mb-3 mt-3">
            <a href="create_post.php" class="btn btn-success btn-sm w-100">+ Create Post</a>
        </li>
        <hr class="text-secondary mt-auto">
    </ul>
</aside>
