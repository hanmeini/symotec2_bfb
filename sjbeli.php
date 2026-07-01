<?php
// sjbeli.php - tampilkan SJ unik (jumlah_m>0 dan harga_m=0)
// Unik berdasarkan SJ + Supplier
// Proteksi: session secure, CSRF, prepared statements (tanpa get_result)

error_reporting(E_ALL);
ini_set('display_errors', 1);

/* ================= SESSION SECURE ================= */
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

/* ================= CEK LOGIN ================= */
if (!isset($_SESSION['userid'])) {
    header("Location: index.html");
    exit();
}

/* ================= CEK LOKASI ================= */
if (!isset($_SESSION['location']) || !in_array($_SESSION['location'], ['HO','HO1'])) {
    header("Location: index.html");
    exit();
}

/* ================= CSRF ================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/* ================= CONFIG DB ================= */
require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}


/* ================= HANDLE POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die("Token CSRF tidak valid");
    }

    // Refresh
    if (isset($_POST['refresh'])) {
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }

    // Ke detail SJ
    if (!empty($_POST['sj'])) {
        $sj = $_POST['sj'];
        header("Location: sjbeli_detail.php?sj=" . urlencode($sj));
        exit();
    }
}

/* ================= QUERY DATA ================= */
/*
 Unik berdasarkan:
   - SJ
   - Supplier (cus)
*/
$sql = "
    SELECT 
        SJ,
        cus,
        MIN(tanggal_transaksi) AS tanggal,
        SUM(harga_m) AS total_harga
    FROM transaksiHO1
    WHERE jumlah_m > 0
    GROUP BY SJ, cus
    ORDER BY (SUM(harga_m) = 0) DESC, tanggal DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) die("Prepare gagal: " . $conn->error);

$stmt->execute();
$stmt->bind_result($sj, $supplier, $tanggal, $total_harga);

$rows = [];
while ($stmt->fetch()) {
    $rows[] = [
        'sj' => $sj,
        'supplier' => $supplier,
        'tanggal' => $tanggal
    ];
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar SJ Beli</title>

<style>
body{font-family:Arial;background:#f4f6f8;margin:20px}
h1{text-align:center;color:#28a745}
table{width:100%;border-collapse:collapse;background:#fff}
th,td{padding:10px;border:1px solid #ddd}
th{background:#28a745;color:#fff}
tr:nth-child(even){background:#f9f9f9}
.btn{padding:6px 12px;border:0;border-radius:4px;cursor:pointer}
.btn-view{background:#007bff;color:#fff}
.btn-refresh{background:#28a745;color:#fff;float:right}
.home{display:inline-block;margin-bottom:10px;text-decoration:none;color:#333}
.actions{text-align:center}
</style>
</head>

<body>

<a href="home.php" class="home">← Home</a>

<form method="post" style="display:inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <button type="submit" name="refresh" class="btn btn-refresh">Refresh</button>
</form>

<h1>Daftar SJ Beli</h1>

<?php if (!empty($rows)): ?>
<table>
<thead>
<tr>
    <th>No</th>
    <th>SJ</th>
    <th>Tanggal</th>
    <th>Supplier</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php $no=1; foreach($rows as $r): ?>
<tr>
    <td><?= $no++ ?></td>
    <td><?= htmlspecialchars($r['sj']) ?></td>
    <td><?= htmlspecialchars($r['tanggal']) ?></td>
    <td><?= htmlspecialchars($r['supplier']) ?></td>
    <td class="actions">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="sj" value="<?= htmlspecialchars($r['sj']) ?>">
            <button type="submit" class="btn btn-view"> Masukan Harga Beli</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<p>Data tidak ditemukan.</p>
<?php endif; ?>

</body>
</html>

<?php
$conn->close();
?>
