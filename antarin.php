<?php
require_once 'config1.php';

$conn->set_charset("utf8mb4");

// Ambil ID Gudang dari parameter URL
$id_gudang = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ================= QUERY SJ IN ================= */
// Mengambil data dari tabel `antar` dimana notrim = '' (belum diterima)
$sql = "
SELECT 
    a.sj AS nomor_sj, 
    ga.nama_gudang AS nama_asal, 
    gt.nama_gudang AS nama_tujuan, 
    DATE(a.tanggal) AS tanggal
FROM antar a
LEFT JOIN master_gudang ga ON ga.id_gudang = a.pengirim
LEFT JOIN master_gudang gt ON gt.id_gudang = a.penerima
WHERE a.notrim = ''
";

if ($id_gudang > 0) {
    $sql .= " AND a.penerima = ? ";
}

$sql .= " GROUP BY a.sj, ga.nama_gudang, gt.nama_gudang, DATE(a.tanggal) ORDER BY a.tanggal DESC ";

$stmt = $conn->prepare($sql);
if ($id_gudang > 0) {
    $stmt->bind_param("i", $id_gudang);
}
$stmt->execute();
$stmt->bind_result($nomor_sj, $nama_asal, $nama_tujuan, $tanggal);

function h($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SJ Dari Gudang Lain</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    padding: 20px;
    margin: 0;
}
.header-wrapper {
    position: relative;
    margin-bottom: 20px;
}
.home-icon {
    position: absolute;
    left: 15px;
    top: 5px;
    text-decoration: none;
}
.home-icon i {
    color: white;
    background: #28a745;
    border-radius: 50%;
    padding: 12px;
    font-size: 28px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}
.home-icon i:hover {
    background: #218838;
}
.main-container {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-top: 60px; /* Space for the floating home icon */
}
h1 {
    color: #000;
    margin: 0 0 20px 0;
    font-size: 24px;
}
.action-top {
    margin-bottom: 20px;
}
.filter-box {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}
.filter-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.filter-item input {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    outline: none;
}
.btn-green {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background: #28a745;
    color: white;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    font-size: 13px;
}
.btn-green:hover {
    background: #218838;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    border: 1px solid #ccc;
    padding: 12px 10px;
    text-align: center;
    font-size: 14px;
}
th {
    background: #28a745;
    color: white;
    font-weight: bold;
}
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #e9f5e9; }
.empty-data {
    padding: 10px;
    font-size: 14px;
    color: #333;
}
</style>
</head>
<body>

<a href="gudang/home.php<?= $id_gudang > 0 ? '?id='.$id_gudang : '' ?>" class="home-icon">
    <i class="fas fa-home"></i>
</a>

<div class="main-container">
    <h1>SJ OUT Yang Belum Diterima</h1>

    <div class="action-top">
        <button class="btn-green">SJ Sudah Di Terima</button>
    </div>

    <div class="filter-box">
        <div class="filter-item">
            <label>No SJ</label>
            <input type="text" name="no_sj" placeholder="">
        </div>
        
        <button class="btn-green">Filter</button>
        <button class="btn-green">Reset</button>
    </div>

    <table>
    <tr>
    <th>No SJ</th>
    <th>Gudang Asal</th>
    <th>Gudang Tujuan</th>
    <th>Tanggal</th>
    <th>Aksi</th>
    </tr>

    <?php
    $adaData=false;
    while($stmt->fetch()):
    $adaData=true;
    ?>
    <tr>
    <td><?=h($nomor_sj)?></td>
    <td><?=h($nama_asal)?></td>
    <td><?=h($nama_tujuan)?></td>
    <td><?=h($tanggal)?></td>
    <td>
    <button class="btn-green" style="padding: 6px 20px;" onclick="openPopup('inantar.php?sj=<?=urlencode($nomor_sj)?>')">
    Terima
    </button>
    </td>
    </tr>
    <?php endwhile; ?>
    </table>

    <?php
    if(!$adaData){
        echo "<div class='empty-data'>Data tidak ditemukan.</div>";
    }
    $stmt->close();
    $conn->close();
    ?>
</div>

<script>
function openPopup(url) {
    const w = 900;
    const h = 650;
    const left = (screen.width - w) / 2;
    const top = (screen.height - h) / 2;
    window.open(url, "SJWindow", `width=${w},height=${h},top=${top},left=${left},scrollbars=yes`);
}
</script>

</body>
</html>
