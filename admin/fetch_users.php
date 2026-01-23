<?php
    require_once "../config/db.php";
    $draw  = intval($_POST['draw']);
    $start  = intval($_POST['start']);
    $length  = intval($_POST['length']);
    $search  = $_POST['search']['value'] ?? "";

    $orderColIdx = $_POST['order'][0]['column'];
    $orderDir = strtolower($_POST['order'][0]['dir']) === 'desc' ? 'DESC' : 'ASC';

    $columns = ['id', 'file_path', 'name', 'email', 'mobile', 'status'];
    $orderColumn = $columns[$orderColIdx] ?? 'id';

    $where = "WHERE u.role = 'user'";
    $params = [];

    if($search !== ''){
        $where .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.mobile LIKE :search OR u.status LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $totalRecords = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();

    $sqlFiltered = "SELECT COUNT(*) FROM users u $where";
    $stmt = $pdo->prepare($sqlFiltered);
    $stmt->execute($params);
    $filteredRecords = $stmt->fetchColumn();

    $sql = "SELECT u.id, u.name, u.email, u.mobile, u.status, (SELECT file_path FROM profile_photos WHERE user_id = u.id ORDER BY id DESC LIMIT 1) as file_path, (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id) AS post_count FROM users u $where ORDER BY $orderColumn $orderDir LIMIT :start, :length";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    $i = $start + 1;

    foreach ($users as $u) {
        $profile = $u['file_path']
            ? '<img src="../public/'.$u['file_path'].'" class="rounded-circle" style="width:40px;height:40px;">'
            : '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width:40px;height:40px;">'.strtoupper($u['name'][0]).'</div>';

        $status = '<span class="badge '.($u['status']=='active'?'bg-success':'bg-danger').'">'.ucfirst($u['status']).'</span>';
        $actions = '
            <a href="user_edit.php?id='.$u['id'].'" class="btn btn-sm btn-warning">Edit</a>
            <a href="user_delete.php?id='.$u['id'].'" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete the user?\')">Delete</a>
        ';

        $postCount = (int)$u['post_count'];
        if($postCount>0){
            $viewPostsBtn = '<a href="user_posts.php?user_id='.$u['id'].'" class="btn btn-sm btn-primary"> Posts ('.$postCount.')</a>';
        }else{
            $viewPostsBtn = '<button class="btn btn-sm btn-secondary" disabled> Posts (0)</button>';
        }

        $data[] = [
            $i++,
            $profile,
            htmlspecialchars($u['name']),
            htmlspecialchars($u['email']),
            htmlspecialchars($u['mobile']),
            $postCount,
            $status,
            $actions,
            $viewPostsBtn
        ];
    }

    echo json_encode([
        "draw" => $draw,
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $filteredRecords,
        "data" => $data
    ]);
?>