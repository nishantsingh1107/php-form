<?php
    session_start();
    require_once "../config/db.php";

    if (!isset($_SESSION['user_id'])) {
        header("Location: login_user.php");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: my_posts.php");
        exit;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $postId = (int)($_POST['post_id'] ?? 0);
    if($postId<=0){
        header("Location: my_posts.php");
        exit;
    }

    $stmt=$pdo->prepare("SELECT id, user_id, admin_status FROM posts WHERE id=:pid LIMIT 1");
    $stmt->execute([':pid'=>$postId]);
    $post=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$post || (int)$post['user_id'] !== $userId){
        header("Location: my_posts.php");
        exit;
    }

    // Prevent deleting blocked posts
    if ($post['admin_status'] === 'blocked') {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'You cannot delete a blocked post.'];
        header("Location: my_posts.php");
        exit;
    }

    $files=[];
    try{
        $pdo->beginTransaction();
        $stmtFiles=$pdo->prepare("SELECT file_path FROM post_images WHERE post_id=:pid");
        $stmtFiles->execute([':pid'=>$postId]);
        $files=$stmtFiles->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM post_images WHERE post_id=:pid")->execute([':pid'=>$postId]);
        $pdo->prepare("DELETE FROM posts WHERE id=:pid AND user_id=:uid")->execute([
            ':pid'=>$postId,
            ':uid'=>$userId
        ]);
        $pdo->commit();
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Post deleted successfully.'];
    }catch(Throwable $e){
        if($pdo->inTransaction()){
            $pdo->rollBack();
        }
        $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Failed to delete post. Please try again.'];
        header("Location: my_posts.php");
        exit;
    }

    foreach((array)$files as $p){
        $p = trim($p);
        if($p === ''){
            continue;
        }
        if(str_starts_with($p,'../public/')){
            $p=substr($p,strlen('../public/'));
        }
        elseif(str_starts_with($p,'../')){
            $p=substr($p,strlen('../'));
        }
        $p=ltrim($p,'/\\');
        if(str_contains($p,'..'))continue;

        $full=__DIR__.DIRECTORY_SEPARATOR.$p;
        if(file_exists($full)){
            @unlink($full);
        }
    }

    header("Location: my_posts.php");
    exit;
?>