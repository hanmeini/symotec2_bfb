<?php





require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=".getenv('DB_HOST').";dbname=".getenv('DB_NAME').";charset=utf8mb4",
        getenv('DB_USER'), getenv('DB_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$bagian = $pdo->query("SELECT * FROM bagian ORDER BY nama_bagian")->fetchAll();
$jabatan = $pdo->query("SELECT * FROM jabatan ORDER BY jabatan")->fetchAll();

// Simpan akses
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_bagian'], $_POST['id_jabatan'])) {
    $id_bagian = $_POST['id_bagian'];
    $id_jabatan = $_POST['id_jabatan'];
    $menu_ids = $_POST['menu_id'] ?? [];

    $pdo->prepare("DELETE FROM akses_jabatan WHERE id_bagian=? AND id_jabatan=?")
        ->execute([$id_bagian, $id_jabatan]);

    $stmt = $pdo->prepare("INSERT INTO akses_jabatan (id_bagian, id_jabatan, id_menu) VALUES (?, ?, ?)");
    foreach ($menu_ids as $id_menu) {
        $stmt->execute([$id_bagian, $id_jabatan, $id_menu]);
    }

    echo "<script>alert('Akses jabatan berhasil diperbarui');window.location='akses_jabatan.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Atur Akses Jabatan per Bagian</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background: #f3f6fa;
    margin: 0;
    padding: 0;
    color: #333;
}
header {
    background: #004085;
    color: white;
    text-align: center;
    padding: 1rem;
    position: relative;
}
header a {
    position: absolute;
    left: 1rem;
    top: 1rem;
    color: white;
    text-decoration: none;
    font-size: 1.4rem;
}
.container {
    background: white;
    max-width: 800px;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}
h2 { text-align: center; color: #004085; }
label { display:block; margin-top:1rem; font-weight:bold; }
select {
    width:100%; padding:10px; border-radius:6px; border:1px solid #ccc;
}
.menu-container {
    margin-top:1.5rem;
    border:1px solid #ddd;
    border-radius:8px;
    background:#fafafa;
    padding:1rem;
    max-height:350px;
    overflow-y:auto;
}
table { width:100%; border-collapse:collapse; }
th, td {
    padding:8px; border-bottom:1px solid #ddd;
}
th { background:#007bff; color:white; text-align:left; }
tr:hover { background:#eef4ff; }
button {
    margin-top:1rem;
    padding:10px 16px;
    border:none;
    border-radius:8px;
    background:#007bff;
    color:white;
    cursor:pointer;
    font-weight:bold;
}
button:hover { background:#0056b3; }
 @media (max-width: 720px) {
            .container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
</style>
</head>
<body>

<header>
    <a href="home.php"><i class="fa-solid fa-house"></i></a>
    <h1>Manajemen Akses Jabatan per Bagian</h1>
</header>

<div class="container">
    <h2>Pengaturan Akses</h2>
    <form method="post" id="aksesForm">
        <label>Pilih Bagian:</label>
        <select name="id_bagian" id="id_bagian" required>
            <option value="">-- Pilih Bagian --</option>
            <?php foreach ($bagian as $b): ?>
                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_bagian']) ?></option>
            <?php endforeach; ?>
        </select>

        <label>Pilih Jabatan:</label>
        <select name="id_jabatan" id="id_jabatan" required disabled>
            <option value="">-- Pilih Jabatan --</option>
            <?php foreach ($jabatan as $j): ?>
                <option value="<?= $j['idj'] ?>"><?= htmlspecialchars($j['jabatan']) ?></option>
            <?php endforeach; ?>
        </select>

        <div id="menuContainer" class="menu-container">
            <p style="text-align:center;color:#777;">Silakan pilih bagian & jabatan terlebih dahulu</p>
        </div>

        <button type="submit"><i class="fa-solid fa-save"></i> Simpan Akses</button>
    </form>
</div>

<script>
// Ketika bagian dipilih
document.getElementById('id_bagian').addEventListener('change', function() {
    const bagianId = this.value;
    const jabatanSelect = document.getElementById('id_jabatan');
    document.getElementById('menuContainer').innerHTML = '<p style="text-align:center;">Silakan pilih jabatan...</p>';
    jabatanSelect.disabled = bagianId === "";
});

// Ketika jabatan dipilih
document.getElementById('id_jabatan').addEventListener('change', function() {
    const bagianId = document.getElementById('id_bagian').value;
    const jabatanId = this.value;
    const menuContainer = document.getElementById('menuContainer');

    if (bagianId && jabatanId) {
        menuContainer.innerHTML = '<p style="text-align:center;">Memuat menu...</p>';
        fetch(`get_menu_akses.php?bagian=${bagianId}&jabatan=${jabatanId}`)
            .then(res => res.text())
            .then(html => { menuContainer.innerHTML = html; })
            .catch(() => { menuContainer.innerHTML = '<p style="color:red;text-align:center;">Gagal memuat data.</p>'; });
    }
});
</script>
</body>
</html>
