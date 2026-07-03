<?php
// ===========================================
// ✅ Session Cookie Settings (HARUS sebelum session_start())
// ===========================================
session_set_cookie_params([
    'httponly' => true,
    'secure'   => isset($_SERVER['HTTPS']),
    'samesite' => 'Lax'
]);

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===========================================
// ✅ Brute Force Protector
// ===========================================
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

define("MAX_LOGIN_ATTEMPTS", 8);
define("LOCKOUT_TIME", 300);

if (isset($_SESSION['locked_until']) && time() < $_SESSION['locked_until']) {
    $remain = $_SESSION['locked_until'] - time();
    echo "<script>alert('Terlalu banyak percobaan login. Coba lagi dalam {$remain} detik.');</script>";
    exit;
}

// ===========================================
// ✅ CSRF Token Generator
// ===========================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===========================================
// ✅ PROSES LOGIN
// ===========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ✅ Cek CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "<script>alert('Invalid Request (CSRF mismatch)'); window.location='login.php';</script>";
        exit;
    }

    // ✅ Cek Brute Force
    if ($_SESSION['login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
        $_SESSION['locked_until'] = time() + LOCKOUT_TIME;
        echo "<script>alert('Terlalu banyak percobaan, coba lagi nanti.');</script>";
        exit;
    }

    // ✅ Ambil Input
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === "" || $password === "") {
        $_SESSION['login_attempts']++;
        echo "<script>alert('Username / Password tidak boleh kosong'); window.location='login.php';</script>";
        exit;
    }

    // ✅ Koneksi Database
    require_once 'config.php';

    $conn = new mysqli(
        getenv('DB_HOST'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        getenv('DB_NAME')
    );

    if ($conn->connect_error) {
        die("Database Error");
    }

    // ✅ Query Aman SQL Injection (Prepared Statement)
    $sql = "SELECT userid, username, password, bagian, jabatan, location, aktif 
            FROM me 
            WHERE username = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Query Error");
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    // ✅ Jika user ditemukan
    if ($row = $result->fetch_assoc()) {

        // Cek password hash
        if (!password_verify($password, $row['password'])) {
            $_SESSION['login_attempts']++;
            echo "<script>alert('Username atau password salah'); window.location='login.php';</script>";
            exit;
        }

        // Cek status aktif jika ada
        if ($row['aktif'] !== null && strtolower($row['aktif']) != "1") {
            echo "<script>alert('Akun tidak aktif'); window.location='login.php';</script>";
            exit;
        }

        // ✅ LOGIN SUKSES
        $_SESSION['login_attempts'] = 0;
        session_regenerate_id(true);

        $_SESSION['userid']   = $row['userid'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['bagian']   = $row['bagian'];
        $_SESSION['jabatan']  = $row['jabatan'];
        $_SESSION['location'] = $row['location'];

        header("Location: home.php");
        exit;
    }

    // Jika user TIDAK ditemukan
    $_SESSION['login_attempts']++;
    echo "<script>alert('Username atau password salah'); window.location='login.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href="logo.png" type="image/png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <title>symotech.id</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-image: url('background.jpg');
            background-size: cover;
            background-position: center;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        form {
            background-color: rgba(255,255,255,0.85);
            padding: 20px;
            border-radius: 10px;
            width: 300px;
            box-shadow: 0 0 12px rgba(0,0,0,0.3);
        }
        input[type=text], input[type=password] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #cccccc;
            border-radius: 5px;
        }
        input[type=submit] {
            width: 100%;
            padding: 11px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        input[type=submit]:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

    <img src="assets/img/logo.png" alt="" style="width: 300px; border-radius: 50%; display: block; margin-bottom: 20px;">
<form action="" method="POST">
    <h1 style="text-align:center;">PT BFB</h1>

    <input type="text" name="username" placeholder="Masukkan username" required>

    <div style="position: relative;">
        <input type="password" name="password" placeholder="Password" required>
        <span onclick="togglePasswordVisibility()" style="position: absolute; top: 50%; right: 5px; transform: translateY(-50%); cursor: pointer;">
            <i class="fas fa-eye" id="password-toggle"></i>
        </span>
    </div>

    <!-- ✅ CSRF TOKEN -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <input type="submit" value="LOGIN">
</form>

<script>
function togglePasswordVisibility() {
    const input = document.querySelector("input[name=password]");
    const icon = document.getElementById("password-toggle");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

</body>
</html>
