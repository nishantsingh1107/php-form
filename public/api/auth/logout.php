<?php
    require __DIR__ . '/../helpers/_bootstrap.php';
    $jwtData = require_jwt_auth();

    api_success([
        "message" => "Logged out successfully, Please delete the token on client side."
    ]);
?>