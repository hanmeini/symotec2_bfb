<?php











require_once 'config.php';

$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("Kesalahan: DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

// Buat koneksi PDO
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Koneksi ke database gagal: " . $e->getMessage());
}

// Ambil data bagian & menu aktif
$bagian = $pdo->query("SELECT * FROM bagian ORDER BY nama_bagian")->fetchAll();
$menu = $pdo->query("SELECT * FROM menu WHERE aktif=1 ORDER BY urutan")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_bagian = $_POST['id_bagian'];
    $menu_ids = $_POST['menu_id'] ?? [];

    // Hapus akses lama
    $pdo->prepare("DELETE FROM akses_bagian WHERE id_bagian = ?")->execute([$id_bagian]);

    // Tambahkan akses baru
    $stmt = $pdo->prepare("INSERT INTO akses_bagian (id_bagian, id_menu) VALUES (?, ?)");
    foreach ($menu_ids as $id_menu) {
        $stmt->execute([$id_bagian, $id_menu]);
    }

    echo "<script>alert('Akses bagian diperbarui');</script>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Akses per Bagian</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
            margin: 0;
            padding: 0;
        }

        header {
            background: #004085;
            color: white;
            text-align: center;
            padding: 1.2rem 0;
            position: relative;
        }

        header a.home-btn {
            position: absolute;
            left: 1.2rem;
            top: 1rem;
            color: white;
            text-decoration: none;
            font-size: 1.6rem;
            transition: color 0.3s ease;
        }

        header a.home-btn:hover {
            color: #ffce00;
        }

        .container {
            max-width: 700px;
            background: #fff;
            margin: 2rem auto;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            color: #004085;
            margin-bottom: 1rem;
        }

        label {
            display: block;
            font-weight: 600;
            margin-top: 1rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        select, button {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: 0.3s;
        }

        select:focus, button:focus {
            outline: none;
            border-color: #007bff;
        }

        .menu-list {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            background: #fafafa;
            max-height: 350px;
            overflow-y: auto;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.4rem 0.6rem;
            background: #fff;
            border-radius: 6px;
            margin-bottom: 6px;
            transition: background 0.2s;
        }

        .menu-item:hover {
            background: #eaf2ff;
        }

        .menu-item input {
            transform: scale(1.2);
            cursor: pointer;
        }

        .menu-item label {
            margin: 0;
            font-weight: 500;
            flex: 1;
            cursor: pointer;
        }

        .menu-item i {
            color: #007bff;
            width: 20px;
        }

        button {
            background: #007bff;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: 1rem;
            padding: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #0056b3;
        }

        footer {
            text-align: center;
            padding: 1rem;
            color: #777;
            font-size: 0.9rem;
            margin-top: 2rem;
        }

        @media (max-width: 600px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<header>
    <a href="home.php" class="home-btn" title="Kembali ke Home">
        <i class="fa-solid fa-house"></i>
    </a>
    <h1>Manajemen Akses Bagian</h1>
</header>

<div class="container">
    <h2>Atur Akses per Bagian</h2>

    <form method="post">
        <label for="id_bagian">Pilih Bagian:</label>
        <select name="id_bagian" id="id_bagian" required>
            <option value="">-- Pilih Bagian --</option>
            <?php foreach ($bagian as $b): ?>
                <option value="<?= htmlspecialchars($b['id']) ?>">
                    <?= htmlspecialchars($b['nama_bagian']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="menu">Pilih Menu:</label>
        <div class="menu-list">
            <?php foreach ($menu as $m): ?>
                <div class="menu-item">
                    <input type="checkbox" name="menu_id[]" value="<?= $m['id_menu'] ?>" id="menu_<?= $m['id_menu'] ?>">
                    <i class="<?= htmlspecialchars($m['icon_menu']) ?>"></i>
                    <label for="menu_<?= $m['id_menu'] ?>"><?= htmlspecialchars($m['nama_menu']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit">
            <i class="fa-solid fa-save"></i> Simpan Akses
        </button>
    </form>
</div>

<footer>
    &copy; <?= date('Y') ?> Symotech | Sistem Manajemen Akses
</footer>

</body>
</html>
