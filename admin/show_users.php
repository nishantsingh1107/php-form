<?php
    require_once "../auth/admin_auth.php";
    require_once "../config/db.php";

    $flashSuccess = $_SESSION['flash_success'] ?? '';
    $flashError = $_SESSION['flash_error'] ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);

    // $stmt = $pdo->query(" SELECT u.id, u.name, u.email, u.mobile, u.status, (SELECT file_path FROM user_files WHERE user_id = u.id ORDER BY id DESC LIMIT 1) AS file_path FROM users u ORDER BY u.id DESC");
    // $users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid">
    <div class="row min-vh-100">
        <div class="col-md-2 bg-dark text-white p-3 d-flex flex-column">
            <h5 class="text-center mb-4">Admin Panel</h5>
            <hr class="text-secondary">

            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a href="admin_dashboard.php" class="nav-link text-white">Dashboard</a>
                </li>
                
                <li class="nav-item mb-2">
                    <a href="admin_profile.php" class="nav-link text-white">Manage Profile</a>
                </li>

                <li class="nav-item mb-2">
                    <a href="show_users.php" class="nav-link text-white">Manage Users</a>
                </li>

                <li class="nav-item mb-3 mt-3">
                    <a href="user_create.php" class="btn btn-success btn-sm w-100">+ Add User</a>
                </li>

                <li class="nav-item mb-3">
                    <a href="../public/logout.php" class="btn btn-danger btn-sm w-100">Logout</a>
                </li>
            </ul>
        </div>


        <div class="col-md-10 bg-light p-4">
            <?php if ($flashSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flashSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flashError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <div class="card shadow-sm">
                <div class="card-body table-responsive">
                    <table id="usersTable" class="table table-bordered table-hover align-middle text-center">
                        <thead class="table-dark">
                            <tr>
                                <th>S. No.</th>
                                <th>Profile</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.1.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.1.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    // document.addEventListener('DOMContentLoaded', function () {
    //     if (!document.getElementById('usersTable')) return;

    //     new DataTable('#usersTable', {
    //         pageLength: 10,
    //         lengthMenu: [10, 25, 50, 100],
    //         order: [[0, 'asc']],
    //         columnDefs: [
    //             { orderable: false, searchable: false, targets: [0, 1, 6] }
    //         ],
    //         language: {
    //             search: 'Search:',
    //             lengthMenu: 'Show _MENU_ users',
    //             info: 'Showing _START_ to _END_ of _TOTAL_ users',
    //             infoEmpty: 'Showing 0 to 0 of 0 users'
    //         }
    //     });
    // });

    document.addEventListener("DOMContentLoaded", function () {
        new DataTable('#usersTable', {
            processing: true,
            serverSide: true,
            ajax: {
                url: 'fetch_users.php',
                type: 'POST'
            },
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            columnDefs: [
                {searchable: false, targets:[0, 1, 6]},
                {orderable: false, targets:[0, 1, 2, 3, 4, 5, 6]}
            ]
        });
    });
</script>
</body>
</html>

