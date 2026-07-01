<?php




require_once 'config1.php';

$conn->set_charset("utf8mb4");

// Ambil ID Gudang dari parameter URL
$id_gudang = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ================= QUERY SJ OUT ================= */
$sql = "
SELECT
    s.sj AS nomor_sj,
    a.pengirim AS gudang_asal_id,
    a.penerima AS gudang_tujuan_id,
    ga.nama_gudang AS nama_asal,
    gt.nama_gudang AS nama_tujuan,
    DATE(s.tanggal_transaksi) AS tanggal,
    COUNT(*) AS total_item,
    IFNULL(SUM(s.jumlah_k),0) AS total_qty,
    a.notrim AS status_pengiriman
FROM stock s
LEFT JOIN antar a
    ON a.sj = s.sj
LEFT JOIN master_gudang ga 
    ON ga.id_gudang = a.pengirim
LEFT JOIN master_gudang gt 
    ON gt.id_gudang = a.penerima
WHERE s.sj IS NOT NULL
AND s.jumlah_k > 0
";

if ($id_gudang > 0) {
    $sql .= " AND a.pengirim = ? ";
}

$sql .= "
GROUP BY 
    s.sj,
    a.pengirim,
    a.penerima,
    DATE(s.tanggal_transaksi),
    a.notrim
ORDER BY s.tanggal_transaksi DESC
";

$stmt = $conn->prepare($sql);
if ($id_gudang > 0) {
    $stmt->bind_param("i", $id_gudang);
}
$stmt->execute();

$stmt->bind_result(
    $nomor_sj,
    $gudang_asal_id,
    $gudang_tujuan_id,
    $nama_asal,
    $nama_tujuan,
    $tanggal,
    $total_item,
    $total_qty,
    $status_pengiriman
);

function h($v){
    return htmlspecialchars($v ?? '',ENT_QUOTES,'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Rekap SJ OUT Antar Gudang</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
body {
    font-family: Arial, sans-serif;
    background: #f4f6f9;
    padding: 20px;
    margin: 0;
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
    margin-top: 60px;
}
h1 {
    color: #2e7d32;
    margin: 0;
    padding: 10px 0;
    font-size: 28px;
}
.filter-box {
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
.filter-item input, .filter-item select {
    padding: 6px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    outline: none;
}
.btn-green {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    background: #2e7d32;
    color: white;
    cursor: pointer;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    font-size: 13px;
}
.btn-green:hover {
    background: #256628;
}
.action-top {
    width: 100%;
    margin-bottom: 10px;
}
table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
th, td {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: center;
    font-size: 14px;
}
th {
    background: #2e7d32;
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

<a href="gudang/home.php<?= $id_gudang > 0 ? '?id='.$id_gudang : '' ?>" class="home-icon"><i class="fas fa-home"></i></a>

<div class="main-container">
    <h1>Rekap SJ OUT - Gudang <?= $id_gudang > 0 ? $id_gudang : 'Semua' ?></h1>

<div class="filter-box">
    <div class="action-top">
        <button class="btn-green" onclick="location.href='antar.php<?= $id_gudang > 0 ? '?id='.$id_gudang : '' ?>'">Buat SJ Antar Gudang</button>
    </div>
    
    <div class="filter-item">
        <label>Dari:</label>
        <input type="date" name="tgl_dari">
    </div>
    
    <div class="filter-item">
        <label>Sampai:</label>
        <input type="date" name="tgl_sampai">
    </div>
    
    <div class="filter-item">
        <label>Nomor SJ:</label>
        <input type="text" name="no_sj" placeholder="Cari SJ...">
    </div>
    
    <div class="filter-item">
        <label>Status Terima:</label>
        <select name="status_terima">
            <option value="belum">Belum Diterima</option>
            <option value="sudah">Sudah Diterima</option>
            <option value="">Semua</option>
        </select>
    </div>
    
    <button class="btn-green">Filter</button>
    <button class="btn-green" style="background:#555;">Reset</button>
</div>

<table>
<tr>
<th>Tanggal</th>
<th>Nomor SJ</th>
<th>Gudang Asal</th>
<th>Gudang Tujuan</th>
<th>Total Item</th>
<th>Total Qty</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php
$adaData=false;

while($stmt->fetch()):
$adaData=true;

$label_status = "";
$tombol_cetak = "Cetak SJ";
$badge_style = "padding: 4px 10px; border-radius: 12px; font-weight: bold; font-size: 12px; display: inline-block;";

if ($status_pengiriman === 'Draft') {
    $label_status = "<span style='{$badge_style} background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;'>Belum Dicetak</span>";
} elseif ($status_pengiriman === 'Sudah') {
    $label_status = "<span style='{$badge_style} background: #d4edda; color: #155724; border: 1px solid #c3e6cb;'>Sudah Diterima</span>";
    $tombol_cetak = "Cetak Ulang";
} else {
    $label_status = "<span style='{$badge_style} background: #fff3cd; color: #856404; border: 1px solid #ffeeba;'>Sedang Dikirim</span>";
    $tombol_cetak = "Cetak Ulang";
}
?>
<tr>
<td><?=h($tanggal)?></td>
<td><?=h($nomor_sj)?></td>
<td><?=h($nama_asal)?></td>
<td><?=h($nama_tujuan)?></td>
<td><?=h($total_item)?></td>
<td><?=h($total_qty)?></td>
<td><?= $label_status ?></td>
<td>
<button class="btn-green" style="padding: 5px 10px;" onclick="openPopup(
'sja.php?tgl=<?=urlencode($tanggal)?>&asal=<?=urlencode($gudang_asal_id)?>&tujuan=<?=urlencode($gudang_tujuan_id)?>&sj=<?=urlencode($nomor_sj)?>'
)">
<?= $tombol_cetak ?>
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
function openPopup(url){
    const w=900;
    const h=650;
    const left=(screen.width-w)/2;
    const top=(screen.height-h)/2;

    window.open(
        url,
        "SJWindow",
        `width=${w},height=${h},top=${top},left=${left},scrollbars=yes`
    );
}
</script>

</body>
</html>
