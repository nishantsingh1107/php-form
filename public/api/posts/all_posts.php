<?php
    require_once __DIR__ . "/../helpers/_bootstrap.php";
    $jwtData = require_jwt_auth();

    $userId = (int)$jwtData['sub'];

    $data = json_decode(file_get_contents("php://input"), true);
    $postId = isset($data['post_id']) ? (int) $data['post_id'] : 0;

    if ($postId > 0) {
        $stmt = $pdo->prepare("SELECT p.id AS post_id, p.title, p.description, p.status, p.created_at, pi.file_path, pi.position FROM posts p LEFT JOIN post_images pi ON pi.post_id = p.id WHERE p.id = :pid AND p.user_id = :uid AND p.admin_status = :status ORDER BY pi.position ASC");
        
        $stmt->execute([
            ":uid" => $userId,
            ":pid" => $postId,
            ':status' => 'approved'
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows){
            api_error("Post not found or not authorised", 404);
        }

        $post = [
            'id' => $postId,
            'title' => $rows[0]['title'],
            'description' => $rows[0]['description'],
            'status' => $rows[0]['status'],
            'created_at' => $rows[0]['created_at'],
            'images' => []
        ];

        foreach($rows as $row){
            if($row['file_path'] !== null){
                $post['images'][] = [
                    'position' => $row['position'],
                    'file_path' => $row['file_path']
                ];
            }
        }
        api_success([
            "post" => $post
        ]);
    }

    $stmt = $pdo->prepare("SELECT p.id AS post_id, p.title, p.description, p.status, p.created_at, pi.file_path, pi.position FROM posts p LEFT JOIN post_images pi ON pi.post_id = p.id WHERE p.user_id = :uid ORDER BY p.created_at DESC, pi.position ASC");
    $stmt->execute([":uid" => $userId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $posts = [];

    foreach($rows as $row){
        $pid = $row['post_id'];
        if(!isset($posts[$pid])){
            $posts[$pid] = [
                'id' => $pid,
                'title' => $row['title'],
                'description' => $row['description'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'images' => []
            ];
        }

        if($row['file_path'] !== null){
            $posts[$pid]['images'][] = [
                'position' => $row['position'],
                'file_path' => $row['file_path']
            ];
        }
    }

    api_success([
        "posts" => array_values($posts)
    ]);
?>