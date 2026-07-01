<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'use_only_cookies'=> true,
    'use_strict_mode' => true,
]);

/* =========================================================
   VALIDASI LOGIN
========================================================= */

if (!isset($_SESSION['username'])) {
    header("Location:index.html");
    exit();
}

/* =========================================================
   VALIDASI REFERER
========================================================= */





/* =========================================================
   KONEKSI DATABASE
========================================================= */

require_once 'config1.php';







/* =========================================================
   FILTER
========================================================= */

$filter = trim($_GET['filter'] ?? '');
$cutoff = trim($_GET['cutoff'] ?? date('Y-m-d'));

$today = date('Y-m-d');

/* =========================================================
   QUERY
========================================================= */

$sql = "
SELECT
    p.j,
    MIN(p.id_transaksi) AS id_transaksi,
    MIN(p.tanggal_transaksi) AS tanggal_transaksi,
    MAX(p.inv) AS inv,
    MAX(p.sup) AS sup,
    MAX(s.nama) AS supplier,

    MAX(p.hargat_m) AS tagihan,

    SUM(COALESCE(p.bayar,0)) AS total_bayar,
    SUM(COALESCE(p.pph,0)) AS total_pph,

    GROUP_CONCAT(
        DISTINCT p.jenispph
        ORDER BY p.jenispph ASC
        SEPARATOR ', '
    ) AS jenispph,

    (
        MAX(p.hargat_m)
        -
        (
            SUM(COALESCE(p.bayar,0))
            +
            SUM(COALESCE(p.pph,0))
        )
    ) AS sisa

FROM pembelianho1 p
LEFT JOIN sup s
    ON p.sup = s.kode

WHERE
    p.tanggal_transaksi <= ?
    AND p.j NOT LIKE 'co%'
";

/* =========================================================
   FILTER SEARCH
========================================================= */

$params = [$cutoff];
$types  = "s";

if ($filter != '') {

    $sql .= "
    AND (
        LOWER(p.inv) LIKE ?
        OR LOWER(p.j) LIKE ?
        OR LOWER(p.sup) LIKE ?
        OR LOWER(s.nama) LIKE ?
    )
    ";

    $search = "%" . strtolower($filter) . "%";

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

    $types .= "ssss";
}

/* =========================================================
   GROUP & HAVING
========================================================= */

$sql .= "
GROUP BY p.j

HAVING sisa > 0

ORDER BY tanggal_transaksi ASC
";

/* =========================================================
   PREPARE
========================================================= */

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare gagal : " . $conn->error);
}

$stmt->bind_param($types, ...$params);

$stmt->execute();

$result = $stmt->get_result();

$rows = [];

while ($row = $result->fetch_assoc()) {

    $hari = 0;

    if (!empty($row['tanggal_transaksi'])) {

        $hari = floor(
            (
                strtotime($today)
                -
                strtotime($row['tanggal_transaksi'])
            ) / 86400
        );
    }

    $row['hari_belum_lunas'] = $hari;

    $rows[] = $row;
}

/* =========================================================
   SORT UMUR TERBESAR
========================================================= */

usort($rows, function ($a, $b) {
    return $b['hari_belum_lunas'] <=> $a['hari_belum_lunas'];
});

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<title>SUPPLIER BELUM LUNAS</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

*{
    box-sizing:border-box;
}

body{
    font-family:Arial, sans-serif;
    background:#f2f2f2;
    margin:0;
    padding:20px;
}

.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

.header a{
    text-decoration:none;
}

.header i{
    font-size:32px;
    color:maroon;
}

.title{
    text-align:center;
    margin-bottom:20px;
    font-size:24px;
    font-weight:bold;
}

.filter-box{
    background:#fff;
    padding:15px;
    border-radius:10px;
    margin-bottom:15px;
    box-shadow:0 0 8px rgba(0,0,0,0.08);
}

.filter-form{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    justify-content:center;
}

input[type=text],
input[type=date]{
    padding:8px;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    padding:8px 12px;
    border:none;
    border-radius:5px;
    cursor:pointer;
    background:#007BFF;
    color:#fff;
    font-size:13px;
}

button:hover{
    background:#0056b3;
}

.table-container{
    background:#fff;
    border-radius:10px;
    padding:15px;
    box-shadow:0 0 8px rgba(0,0,0,0.08);
    overflow:auto;
    max-height:80vh;
}

table{
    border-collapse:collapse;
    width:100%;
    min-width:1400px;
}

th,
td{
    border:1px solid #ddd;
    padding:8px;
    font-size:13px;
}

th{
    background:#003ea8;
    color:#fff;
    text-align:center;
    position:sticky;
    top:0;
    z-index:2;
}

td{
    background:#fff;
}

tr:hover td{
    background:#f7f7f7;
}

.text-left{
    text-align:left;
}

.text-center{
    text-align:center;
}

.text-right{
    text-align:right;
}

.total-row td{
    font-weight:bold;
    background:#efefef !important;
}

.checkbox{
    width:18px;
    height:18px;
}

.action-buttons{
    display:flex;
    gap:5px;
    flex-wrap:wrap;
    justify-content:center;
}

.bulk-buttons{
    margin-top:15px;
    text-align:center;
}

.bulk-buttons button{
    margin:4px;
}

.badge-umur{
    background:#dc3545;
    color:#fff;
    padding:4px 8px;
    border-radius:20px;
    font-size:12px;
    display:inline-block;
}

</style>

</head>

<body>

<div class="header">

<a href="home.php">
    <i class="fas fa-home"></i>
</a>

<a href="home.php">
    <i class="fa-solid fa-circle-left"></i>
</a>

</div>

<div class="title">
    SUPPLIER BELUM LUNAS
</div>

<div class="filter-box">

<form method="GET" class="filter-form">

    <input
        type="text"
        name="filter"
        placeholder="Filter invoice / jurnal / supplier"
        value="<?= htmlspecialchars($filter ?? '') ?>">

    <input
        type="date"
        name="cutoff"
        value="<?= htmlspecialchars($cutoff ?? '') ?>">

    <button type="submit">
        Filter
    </button>

    <button
        type="button"
        onclick="location.href='export_excelap.php?filter=<?= urlencode($filter) ?>&cutoff=<?= urlencode($cutoff) ?>'">
        Export Excel
    </button>

    <button
        type="button"
        onclick="location.href='apumur.php'">
        Berdasarkan Umur
    </button>

    <button
        type="button"
        onclick="location.href='apby.php'">
        Sudah Dibayar
    </button>

</form>

</div>

<div class="table-container">

<?php if (count($rows) > 0): ?>

<table>

<tr>

    <th>
        <input type="checkbox" id="select-all">
    </th>

   Tanggal</th> <th>
    <th>Invoice</th>
    <th>No Jurnal</th>
    <th>Supplier</th>
    <th>Tagihan</th>
    <th>Total Bayar</th>
    <th>Jenis PPH</th>
    <th>Total PPH</th>
    <th>Sisa</th>
    <th>Umur</th>
    <th>Aksi</th>

</tr>

<?php

$total_tagihan = 0;
$total_bayar   = 0;
$total_pph     = 0;
$total_sisa    = 0;

foreach ($rows as $row):

    $tagihan = (float)$row['tagihan'];
    $bayar   = (float)$row['total_bayar'];
    $pph     = (float)$row['total_pph'];
    $sisa    = (float)$row['sisa'];

    $total_tagihan += $tagihan;
    $total_bayar   += $bayar;
    $total_pph     += $pph;
    $total_sisa    += $sisa;

    $jurnal = htmlspecialchars($row['j'] ?? '');

?>

<tr>

    <td class="text-center">

        <input
            type="checkbox"
            class="checkbox"
            name="selected_ids[]"
            value="<?= $jurnal ?>">

    </td>

    <td class="text-center">
        <?= htmlspecialchars($row['tanggal_transaksi'] ?? '') ?>
    </td>

    <td class="text-left">
        <?= htmlspecialchars($row['inv'] ?? '') ?>
    </td>

    <td class="text-left">
        <?= $jurnal ?>
    </td>

    <td class="text-left">
        <?= htmlspecialchars($row['supplier'] ?? '') ?>
    </td>

    <td class="text-right">
        <?= number_format($tagihan, 2) ?>
    </td>

    <td class="text-right">
        <?= number_format($bayar, 2) ?>
    </td>

    <td class="text-center">
        <?= htmlspecialchars($row['jenispph'] ?? '') ?>
    </td>

    <td class="text-right">
        <?= number_format($pph, 2) ?>
    </td>

    <td class="text-right">
        <?= number_format($sisa, 2) ?>
    </td>

    <td class="text-center">
        <span class="badge-umur">
            <?= $row['hari_belum_lunas'] ?> Hari
        </span>
    </td>

    <td class="text-center">

        <div class="action-buttons">

            <button
                type="button"
                onclick="openPopup('apc.php?J=<?= urlencode($jurnal) ?>')">
                Cash
            </button>

            <button
                type="button"
                onclick="openPopup('apb.php?J=<?= urlencode($jurnal) ?>')">
                Bank
            </button>

            <button
                type="button"
                onclick="openPopup('apt.php?J=<?= urlencode($jurnal) ?>')">
                Titipan
            </button>

            <button
                type="button"
                onclick="openPopup('apcn.php?J=<?= urlencode($jurnal) ?>')">
                CN
            </button>

            <button
                type="button"
                onclick="openPopup('apdn.php?J=<?= urlencode($jurnal) ?>')">
                DN
            </button>

        </div>

    </td>

</tr>

<?php endforeach; ?>

<tr class="total-row">

    <td colspan="5" class="text-center">
        TOTAL
    </td>

    <td class="text-right">
        <?= number_format($total_tagihan, 2) ?>
    </td>

    <td class="text-right">
        <?= number_format($total_bayar, 2) ?>
    </td>

    <td></td>

    <td class="text-right">
        <?= number_format($total_pph, 2) ?>
    </td>

    <td class="text-right">
        <?= number_format($total_sisa, 2) ?>
    </td>

    <td colspan="2"></td>

</tr>

</table>

<?php else: ?>

<p style="text-align:center;">
    Tidak ada data ditemukan.
</p>

<?php endif; ?>

</div>

<script>

function openPopup(url){

    const width  = 900;
    const height = 900;

    const left = (screen.width - width) / 2;
    const top  = (screen.height - height) / 2;

    window.open(
        url,
        'PopupWindow',
        `
        width=${width},
        height=${height},
        left=${left},
        top=${top},
        resizable=yes,
        scrollbars=yes
        `
    );
}

/* =========================================================
   SELECT ALL
========================================================= */

const selectAll = document.getElementById('select-all');

if(selectAll){

    selectAll.addEventListener('change', function(){

        document.querySelectorAll(
            'input[name="selected_ids[]"]'
        ).forEach(cb => {

            cb.checked = this.checked;

        });

    });

}

// Bulk process removed

</script>

</body>
</html>