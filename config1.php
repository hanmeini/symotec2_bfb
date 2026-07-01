<?php
// config1.php

declare(strict_types=1);

// ==================== ENV ====================
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . '=' . trim($value));
        }
    }
} else {
    die("File .env tidak ditemukan! Silakan buat dari .env.example");
}


// ==================== ERROR ====================
error_reporting(E_ALL);

if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    ini_set('display_errors', '1');
}

// ==================== SESSION ====================
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_httponly' => true,
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'use_only_cookies'=> true,
        'use_strict_mode' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// ==================== VALIDASI LOGIN ====================
if (php_sapi_name() !== 'cli') {
    if (
        empty($_SESSION['username']) ||
        empty($_SESSION['location'])
    ) {
        header("Location: index.html");
        exit();
    }
}

// ==================== VALIDASI REFERER (OPSIONAL) ====================
if (php_sapi_name() !== 'cli') {
    $allowed_host = $_SERVER['SERVER_NAME'] ?? 'localhost'; 

    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        if ($refererHost !== $allowed_host && $refererHost !== ($_SERVER['HTTP_HOST'] ?? '')) {
            if (basename($_SERVER['PHP_SELF']) !== 'home.php') {
                header("Location: home.php");
                exit();
            }
        }
    }
}

// ==================== DATABASE ====================
$servername  = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database    = getenv('DB_NAME');


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    $conn = new mysqli(
        $servername,
        $db_username,
        $db_password,
        $database
    );

    $conn->set_charset('utf8mb4');
    
    // Global PDO Connection
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

} catch (Exception $e) {

    error_log($e->getMessage());

    die('Koneksi database gagal.');
}

// ==================== VALIDASI USER ====================
if (php_sapi_name() !== 'cli') {
    $stmt = $conn->prepare("
        SELECT username
        FROM me
        WHERE username = ?
          AND location = ?
        LIMIT 1
    ");

    $stmt->bind_param(
        "ss",
        $_SESSION['username'],
        $_SESSION['location']
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        session_unset();
        session_destroy();

        header("Location: index.html");
        exit();
    }

    $stmt->close();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
