<?php
date_default_timezone_set('UTC');


$projectRoot = dirname(__DIR__);
if (!defined('APP_ENV_LOADED')) {
    define('APP_ENV_LOADED', true);
    $autoloadPath = $projectRoot . '/vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (class_exists('Dotenv\\Dotenv') && is_dir($projectRoot)) {
        try {
            Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
        } catch (Throwable $e) {
            // If .env is missing/invalid, fall back to defaults below.
        }
    }
}

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'organization';
$username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
$password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
