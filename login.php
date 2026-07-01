<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| SECURE SESSION
|--------------------------------------------------------------------------
*/

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

/*
|--------------------------------------------------------------------------
| FORCE HTTPS
|--------------------------------------------------------------------------
*/

if (
    empty($_SERVER['HTTPS']) ||
    $_SERVER['HTTPS'] === 'off'
) {

    header(
        "Location: https://" .
        $_SERVER['HTTP_HOST'] .
        $_SERVER['REQUEST_URI']
    );

    exit();
}

/*
|--------------------------------------------------------------------------
| SECURITY HEADERS
|--------------------------------------------------------------------------
*/

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline';");

/*
|--------------------------------------------------------------------------
| LOAD CONFIG
|--------------------------------------------------------------------------
*/

require_once 'config.php';

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$servername = getenv('DB_HOST') ?: die("DB_HOST tidak ditemukan");
$db_username = getenv('DB_USER') ?: die("DB_USER tidak ditemukan");
$db_password = getenv('DB_PASS') ?: die("DB_PASS tidak ditemukan");
$database   = getenv('DB_NAME') ?: die("DB_NAME tidak ditemukan");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = new mysqli(
    $servername,
    $db_username,
    $db_password,
    $database
);

$conn->set_charset("utf8mb4");

/*
|--------------------------------------------------------------------------
| ONLY ALLOW POST
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    die("Method Not Allowed");
}

/*
|--------------------------------------------------------------------------
| INPUT
|--------------------------------------------------------------------------
*/

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {

    echo "
    <script>
        alert('Username dan password wajib diisi.');
        window.location.href='index.html';
    </script>
    ";

    exit();
}

/*
|--------------------------------------------------------------------------
| BRUTE FORCE PROTECTION
|--------------------------------------------------------------------------
*/

$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

/*
|--------------------------------------------------------------------------
| CHECK FAILED ATTEMPTS
|--------------------------------------------------------------------------
*/

$check_attempt = "
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_address = ?
    AND userid = ?
    AND attempt_time > (NOW() - INTERVAL 15 MINUTE)
";

$stmt_attempt = $conn->prepare($check_attempt);

$stmt_attempt->bind_param(
    "ss",
    $ip_address,
    $username
);

$stmt_attempt->execute();

$stmt_attempt->bind_result($total_attempt);

$stmt_attempt->fetch();

$stmt_attempt->close();

/*
|--------------------------------------------------------------------------
| BLOCK IF TOO MANY ATTEMPTS
|--------------------------------------------------------------------------
*/

if ($total_attempt >= 5) {

    echo "
    <script>
        alert('Terlalu banyak percobaan login. Coba lagi 15 menit lagi.');
        window.location.href='index.html';
    </script>
    ";

    exit();
}

/*
|--------------------------------------------------------------------------
| LOGIN QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        userid,
        username,
        password,
        location,
        jabatan
    FROM me
    WHERE username = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $username);

$stmt->execute();

$stmt->bind_result(
    $userid,
    $db_username,
    $stored_password,
    $location,
    $jabatan
);

$user_found = $stmt->fetch();

$stmt->close();

/*
|--------------------------------------------------------------------------
| USER FOUND
|--------------------------------------------------------------------------
*/

if ($user_found) {

    /*
    |--------------------------------------------------------------------------
    | VERIFY PASSWORD
    |--------------------------------------------------------------------------
    */

    if (password_verify($password, $stored_password)) {

        /*
        |--------------------------------------------------------------------------
        | DELETE FAILED ATTEMPTS
        |--------------------------------------------------------------------------
        */

        $delete_attempt = "
            DELETE FROM login_attempts
            WHERE ip_address = ?
            AND userid = ?
        ";

        $stmt_delete = $conn->prepare($delete_attempt);

        $stmt_delete->bind_param(
            "ss",
            $ip_address,
            $username
        );

        $stmt_delete->execute();

        $stmt_delete->close();

        /*
        |--------------------------------------------------------------------------
        | REGENERATE SESSION
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(true);

        /*
        |--------------------------------------------------------------------------
        | SET SESSION
        |--------------------------------------------------------------------------
        */

        $_SESSION['userid'] = $userid;
        $_SESSION['username'] = $db_username;
        $_SESSION['location'] = $location ?? null;
        $_SESSION['jabatan'] = $jabatan ?? null;

        /*
        |--------------------------------------------------------------------------
        | SAFE REDIRECT
        |--------------------------------------------------------------------------
        */

        $allowed_locations = [
            'HO',
            'HO1',
            'LMD',
            'SK',
            'SEPEDA',
            'Furniture',
            'NK'
        ];

        if (!in_array($location, $allowed_locations)) {

            session_destroy();

            echo "
            <script>
                alert('Location tidak valid.');
                window.location.href='index.html';
            </script>
            ";

            exit();
        }

        echo "
        <script>
            window.location.href='{$location}/home.php';
        </script>
        ";

        exit();

    } else {

        /*
        |--------------------------------------------------------------------------
        | WRONG PASSWORD
        |--------------------------------------------------------------------------
        */

        $insert_attempt = "
            INSERT INTO login_attempts
            (ip_address, userid, attempt_time)
            VALUES (?, ?, NOW())
        ";

        $stmt_insert = $conn->prepare($insert_attempt);

        $stmt_insert->bind_param(
            "ss",
            $ip_address,
            $username
        );

        $stmt_insert->execute();

        $stmt_insert->close();

        /*
        |--------------------------------------------------------------------------
        | RECHECK TOTAL ATTEMPTS
        |--------------------------------------------------------------------------
        */

        $stmt_attempt = $conn->prepare($check_attempt);

        $stmt_attempt->bind_param(
            "ss",
            $ip_address,
            $username
        );

        $stmt_attempt->execute();

        $stmt_attempt->bind_result($total_attempt);

        $stmt_attempt->fetch();

        $stmt_attempt->close();

        sleep(1);

        if ($total_attempt >= 5) {

            echo "
            <script>
                alert('Terlalu banyak percobaan login. Coba lagi 15 menit lagi.');
                window.location.href='index.html';
            </script>
            ";

        } else {

            echo "
            <script>
                alert('Invalid username or password.');
                window.location.href='index.html';
            </script>
            ";
        }

        exit();
    }

} else {

    /*
    |--------------------------------------------------------------------------
    | USERNAME NOT FOUND
    |--------------------------------------------------------------------------
    */

    $insert_attempt = "
        INSERT INTO login_attempts
        (ip_address, userid, attempt_time)
        VALUES (?, ?, NOW())
    ";

    $stmt_insert = $conn->prepare($insert_attempt);

    $stmt_insert->bind_param(
        "ss",
        $ip_address,
        $username
    );

    $stmt_insert->execute();

    $stmt_insert->close();

    /*
    |--------------------------------------------------------------------------
    | RECHECK TOTAL ATTEMPTS
    |--------------------------------------------------------------------------
    */

    $stmt_attempt = $conn->prepare($check_attempt);

    $stmt_attempt->bind_param(
        "ss",
        $ip_address,
        $username
    );

    $stmt_attempt->execute();

    $stmt_attempt->bind_result($total_attempt);

    $stmt_attempt->fetch();

    $stmt_attempt->close();

    sleep(1);

    if ($total_attempt >= 5) {

        echo "
        <script>
            alert('Terlalu banyak percobaan login. Coba lagi 15 menit lagi.');
            window.location.href='index.html';
        </script>
        ";

    } else {

        echo "
        <script>
            alert('Invalid username or password.');
            window.location.href='index.html';
        </script>
        ";
    }

    exit();
}

/*
|--------------------------------------------------------------------------
| CLOSE CONNECTION
|--------------------------------------------------------------------------
*/

$conn->close();

?>
