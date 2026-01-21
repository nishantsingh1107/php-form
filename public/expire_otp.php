<?php
    session_start();
    require_once "../config/db.php";

    $uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
    if($uid > 0){
        $pdo->prepare("UPDATE users SET otp_expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE id = :id AND status = 'inactive' AND email_verified = 0")->execute(['id' => $uid]);
    }

    http_response_code(204);
exit;
