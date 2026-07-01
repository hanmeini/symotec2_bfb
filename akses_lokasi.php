<?php








require_once 'config.php';

// ===============================
// ✅ KONEKSI DATABASE
// ===============================
$servername = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME');

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4",
        $db_username, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
} catch (PDOException $e) {
    die("Koneksi DB gagal: " . $e->getMessage());
}

// ===============================
// ✅ Ambil menu aktif
// ===============================
$menu = $pdo->query("SELECT * FROM menu WHERE aktif=1 ORDER BY urutan")->fetchAll();

// ===============================
// ✅ SIMPAN AKSES LOKASI
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? '';
    $menu_id = $_POST['menu_id'] ?? [];

    if (!in_array($type, ['HO', 'CABANG'])) {
        die("Tipe lokasi tidak valid.");
    }

    // Hapus akses lama
    $pdo->prepare("DELETE FROM akses_location_menu WHERE type=?")->execute([$type]);

    // Insert baru
    $stmt = $pdo->prepare("INSERT INTO akses_location_menu (type, id_menu) VALUES (?, ?)");
    foreach ($menu_id as $m) {
        $stmt->execute([$type, intval($m)]);
    }

    echo "<script>alert('Akses lokasi berhasil diperbarui.');</script>";
}

// ===============================
// ✅ Ambil akses HO & CABANG
// ===============================
$akses_db = $pdo->query("SELECT type, id_menu FROM akses_location_menu")->fetchAll();

$akses = [
    "HO"     => [],
    "CABANG" => []
];

foreach ($akses_db as $a) {
    $akses[$a['type']][] = $a['id_menu'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Manajemen Akses Lokasi</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: #f4f6f9;
    margin: 0;
    padding: 0;
}
header {
    background: #004085;
    color: white;
    padding: 1rem;
    text-align: center;
    position: relative;
}
header a.home-btn {
    position: absolute;
    left: 1rem;
    top: 0.8rem;
    font-size: 1.6rem;
    color: white;
    text-decoration: none;
}
header a.home-btn:hover { color: #ffce00; }

.container {
    max-width: 700px;
    background: #fff;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 8px 18px rgba(0,0,0,0.1);
}
h2 {
    text-align: center;
    color: #004085;
}
label {
    display: block;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: .5rem;
}
select, button {
    width: 100%;
    padding: .7rem;
    border-radius: 6px;
    border: 1px solid #bbb;
}
button {
    margin-top: 1.2rem;
    background: #007bff;
    color: white;
    cursor: pointer;
    font-weight: bold;
}
button:hover { background: #0056b3; }

.menu-list {
    border: 1px solid #ddd;
    padding: 1rem;
    background: #fafafa;
    border-radius: 8px;
    max-height: 350px;
    overflow-y: auto;
}
.menu-item {
    display: flex;
    align-items: center;
    gap: .7rem;
    padding: .4rem .6rem;
    background: white;
    border-radius: 8px;
    margin-bottom: .4rem;
}
.menu-item:hover { background: #eaf2ff; }
.menu-item input { transform: scale(1.2); cursor: pointer; }

footer {
    text-align: center;
    padding: 1rem;
    color: #777;
}
</style>
</head>

<body>

<header>
    <a href="home.php" class="home-btn"><i class="fa-solid fa-house"></i></a>
    <h1>Manajemen Akses Lokasi</h1>
</header>

<div class="container">
    <h2>Atur Akses Berdasarkan Lokasi</h2>

    <form method="post">

        <label>Pilih Lokasi:</label>
        <select name="type" id="type" required onchange="loadAksesLokasi()">
            <option value="">-- Pilih Lokasi --</option>
            <option value="HO">HO (Pusat)</option>
            <option value="CABANG">CABANG</option>
        </select>

        <label>Pilih Menu:</label>
        <div class="menu-list" id="menuList">
            <?php foreach ($menu as $m): ?>
                <div class="menu-item">
                    <input type="checkbox" name="menu_id[]" class="menu-checkbox"
                           value="<?= $m['id_menu'] ?>" id="menu_<?= $m['id_menu'] ?>">
                    <i class="<?= htmlspecialchars($m['icon_menu']) ?>"></i>
                    <label for="menu_<?= $m['id_menu'] ?>"><?= htmlspecialchars($m['nama_menu']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit"><i class="fa-solid fa-save"></i> Simpan Akses</button>
    </form>
</div>

<footer>
    &copy; <?= date('Y') ?> Symotech | Sistem Akses Lokasi
</footer>


<script>
// ===============================
// ✅ LOAD AKSES SAAT PILIH LOKASI
// ===============================
const akses = <?= json_encode($akses) ?>;

function loadAksesLokasi() {
    let type = document.getElementById('type').value;

    document.querySelectorAll('.menu-checkbox').forEach(cb => cb.checked = false);

    if (akses[type]) {
        akses[type].forEach(id => {
            let cb = document.getElementById('menu_' + id);
            if (cb) cb.checked = true;
        });
    }
}
</script>

</body>
</html>
