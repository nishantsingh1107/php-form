<?php
    declare(strict_types=1);

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    header("content-Type: application/json");
    session_start();
    require_once __DIR__ . '/../../../config/db.php';

    require_once __DIR__ . '/response.php';
    require_once __DIR__ . '/validators.php';
    require_once __DIR__ . '/mailer.php';
    require_once __DIR__ . '/auth.php';
?>