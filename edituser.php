<?php
require_once 'config1.php';

// Pastikan user sudah login
if (!isset($_SESSION['userid'])) {
    header("Location: index.html");
    exit();
}

// --- Buat CSRF Token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$id = (int) $_SESSION['userid'];

// --- Proses Update Password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Permintaan tidak valid (CSRF terdeteksi).");
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validasi panjang password
    if (strlen($new_password) < 8) {
        $message = "Password baru minimal 8 karakter.";
    } else {
        // Ambil password lama
        $stmt = $conn->prepare("SELECT password FROM me WHERE userid = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $conn->prepare("UPDATE me SET password = ? WHERE userid = ?");
                $update->bind_param("si", $hashed, $id);
                $update->execute();

                if ($update->affected_rows > 0) {
                    $message = "✅ Password berhasil diperbarui!";
                } else {
                    $message = "⚠️ Tidak ada perubahan pada password.";
                }
                $update->close();
            } else {
                $message = "❌ Konfirmasi password tidak cocok.";
            }
        } else {
            $message = "❌ Password lama salah.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Password</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f4f6f8;
        margin: 0;
        padding: 0;
    }
    .container {
        max-width: 700px;
        margin: 40px auto;
        background: #fff;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    h1 {
        text-align: center;
        color: #333;
        margin-bottom: 30px;
    }
    label {
        font-weight: bold;
        display: block;
        margin-bottom: 5px;
    }
    input[type="password"] {
        width: 100%;
        padding: 10px;
        margin-bottom: 20px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    .btn {
        background-color: #007bff;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
        font-size: 16px;
    }
    .btn:hover {
        background-color: #0056b3;
    }
    .message {
        text-align: center;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .message.success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .message.error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    a.btn-secondary {
        display: block;
        text-align: center;
        margin-top: 20px;
        text-decoration: none;
        background: #6c757d;
        color: white;
        padding: 10px;
        border-radius: 5px;
    }
    a.btn-secondary:hover {
        background: #5a6268;
    }
</style>
</head>
<body>

<div class="container">
    <h1><i class="fa-solid fa-lock"></i> Update Password</h1>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo (strpos($message, 'berhasil') !== false) ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
        <label for="current_password">Password Lama</label>
        <input type="password" id="current_password" name="current_password" required>

        <label for="new_password">Password Baru</label>
        <input type="password" id="new_password" name="new_password" required>

        <label for="confirm_password">Konfirmasi Password Baru</label>
        <input type="password" id="confirm_password" name="confirm_password" required>

        <button type="submit" class="btn">Update Password</button>
    </form>

    <a href="home.php" class="btn-secondary"><i class="fa fa-home"></i> Kembali ke Home</a>
</div>

</body>
</html>
