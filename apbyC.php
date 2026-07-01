<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start([
    'cookie_lifetime'   => 86400,
    'cookie_httponly'   => true,
    'cookie_secure'     => isset($_SERVER['HTTPS']),
    'use_only_cookies'  => true,
    'use_strict_mode'   => true,
]);





if(!isset($_SESSION['username'])){
    header("Location:index.html");
    exit();
}

require_once 'config1.php';







/* =========================================================
   FILTER
========================================================= */

$filter = trim($_GET['filter'] ?? '');

$start = $_GET['start'] ?? date('Y-m-d');
$end   = $_GET['end'] ?? date('Y-m-d');

if($start==''){
    $start = date('Y-m-d');
}

if($end==''){
    $end = date('Y-m-d');
}

/* =========================================================
   VALIDASI TANGGAL
========================================================= */

$startDate = DateTime::createFromFormat('Y-m-d',$start);
$endDate   = DateTime::createFromFormat('Y-m-d',$end);

if(!$startDate || !$endDate){
    die("Format tanggal tidak valid");
}

/* =========================================================
   WHERE
========================================================= */

$where = [];

/* hanya voucher claim */

$where[] = "LOWER(p.j) LIKE 'co%'";

/* hanya yang sudah ada pembayaran */

$where[] = "(
    COALESCE(p.bayar,0) > 0
    OR
    COALESCE(p.pph,0) > 0
)";

/*
    gunakan tanggal jurnal supaya tidak dobel
    ambil dari tabel jurnal berdasarkan kode_booking
*/

$where[] = "
DATE(j.tanggal)
BETWEEN ?
AND ?
";

/* =========================================================
   FILTER TEXT
========================================================= */

$params = [];
$types  = '';

$params[] = $start;
$params[] = $end;
$types   .= 'ss';

if($filter != ''){

    $where[] = "(
        LOWER(p.inv) LIKE ?
        OR
        LOWER(p.j) LIKE ?
        OR
        LOWER(p.sup) LIKE ?
        OR
        LOWER(s.nama) LIKE ?
    )";

    $search = '%'.strtolower($filter).'%';

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

    $types .= 'ssss';
}

$where_sql = implode(" AND ",$where);

/* =========================================================
   QUERY
========================================================= */

/*
    DISTINCT + GROUP BY
    supaya tidak dobel saat join jurnal
*/

$sql = "
SELECT
    p.id_transaksi,
    DATE(j.tanggal) AS tanggal_jurnal,
    p.inv,
    p.j,
    p.sup,
    COALESCE(s.nama,'-') AS nama_supplier,
    p.harga_m,
    p.ppn_m,
    p.hargat_m,
    p.bayar,
    p.sisa

FROM pembelianho1 p

LEFT JOIN sup s
    ON s.kode = p.sup

INNER JOIN jurnal j
    ON j.kode_booking = p.j

WHERE
    $where_sql
    AND p.cancel IS NULL AND j.journal_number IS NULL

GROUP BY
    p.id_transaksi

ORDER BY
    tanggal_jurnal DESC,
    p.id_transaksi DESC
";

$stmt = $conn->prepare($sql);

if(!$stmt){
    die("Prepare failed : ".$conn->error);
}

$stmt->bind_param($types,...$params);

$stmt->execute();

$result = $stmt->get_result();

if(!$result){
    die("Query Error : ".$conn->error);
}

/* =========================================================
   TOTAL
========================================================= */

$total_tagihan = 0;
$total_bayar   = 0;
$total_sisa    = 0;

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Rekap Voucher pembelianho1 Claim Cash</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{
    font-family:Arial;
    background:#f2f2f2;
    margin:0;
    padding:20px;
}

.table-container{
    background:#fff;
    padding:20px;
    border-radius:12px;
    box-shadow:0 0 8px rgba(0,0,0,0.1);
    max-height:700px;
    overflow:auto;
}

h2{
    text-align:center;
    margin-bottom:20px;
}

form{
    margin-bottom:20px;
}

.filter-row{
    display:flex;
    gap:10px;
    justify-content:center;
    align-items:center;
    flex-wrap:wrap;
}

input[type=text],
input[type=date]{
    padding:8px;
    border:1px solid #ccc;
    border-radius:6px;
}

input[type=text]{
    width:320px;
}

button{
    padding:8px 16px;
    border:none;
    background:#1976d2;
    color:white;
    border-radius:6px;
    cursor:pointer;
    font-weight:bold;
}

button:hover{
    background:#1565c0;
}

button:disabled{
    background:#999;
    cursor:not-allowed;
}

table{
    border-collapse:collapse;
    width:100%;
    margin-top:20px;
}

th,
td{
    border:1px solid #ddd;
    padding:10px;
    font-size:13px;
}

th{
    background:#003c8f;
    color:white;
    position:sticky;
    top:0;
    z-index:2;
    text-align:center;
}

td{
    text-align:right;
}

tr:hover{
    background:#f5f5f5;
}

.text-left{
    text-align:left;
}

.text-center{
    text-align:center;
}

.total-row{
    background:#efefef;
    font-weight:bold;
}

.badge{
    padding:4px 8px;
    border-radius:6px;
    color:#fff;
    font-size:12px;
    font-weight:bold;
}

.badge-lunas{
    background:#28a745;
}

.badge-belum{
    background:#dc3545;
}

.home-icon i{
    color:maroon;
    font-size:36px;
    float:left;
}

.left-icon i{
    color:maroon;
    font-size:36px;
    float:right;
}

.print-btn{
    margin-top:20px;
}

.loading{
    opacity:0.6;
    pointer-events:none;
}

</style>

</head>

<body>

<a href="home.php" class="home-icon">
    <i class="fa fa-home"></i>
</a>

<a href="ap.php" class="left-icon">
    <i class="fa fa-arrow-left"></i>
</a>

<h2>Rekap Voucher pembelianho1 Claim Cash</h2>

<form method="get" id="filterForm">

<div class="filter-row">

<input
type="text"
name="filter"
placeholder="Filter invoice / jurnal / supplier"
value="<?= htmlspecialchars($filter) ?>">

<label>Tanggal Awal</label>

<input
type="date"
name="start"
value="<?= htmlspecialchars($start) ?>">

<label>Tanggal Akhir</label>

<input
type="date"
name="end"
value="<?= htmlspecialchars($end) ?>">

<button type="submit" id="submitBtn">
Filter
</button>

<button
type="button"
id="exportBtn"
onclick="exportExcel()">
Export Excel
</button>

</div>

</form>

<div class="table-container">

<table>

<tr>
    <th>No</th>
    <th>Tanggal Jurnal</th>
    <th>Invoice</th>
    <th>Jurnal</th>
    <th>Nama Supplier</th>
    <th>DPP</th>
    <th>PPN</th>
    <th>Total</th>
    <th>Bayar</th>
    <th>Sisa</th>
    <th>Status</th>
</tr>

<?php

$no = 1;

while($row = $result->fetch_assoc()){

    $total_tagihan += (float)$row['hargat_m'];
    $total_bayar   += (float)$row['bayar'];
    $total_sisa    += (float)$row['sisa'];

    $statusText  = 'Belum Lunas';
    $statusClass = 'badge-belum';

    if((float)$row['sisa'] <= 0){
        $statusText  = 'Lunas';
        $statusClass = 'badge-lunas';
    }

    echo "

    <tr>

        <td class='text-center'>{$no}</td>

        <td class='text-center'>
            ".htmlspecialchars($row['tanggal_jurnal'])."
        </td>

        <td class='text-left'>
            ".htmlspecialchars($row['inv'])."
        </td>

        <td class='text-left'>
            ".htmlspecialchars($row['j'])."
        </td>

        <td class='text-left'>
            ".htmlspecialchars($row['nama_supplier'])."
        </td>

        <td>
            ".number_format($row['harga_m'],0,',','.')."
        </td>

        <td>
            ".number_format($row['ppn_m'],0,',','.')."
        </td>

        <td>
            ".number_format($row['hargat_m'],0,',','.')."
        </td>

        <td>
            ".number_format($row['bayar'],0,',','.')."
        </td>

        <td>
            ".number_format($row['sisa'],0,',','.')."
        </td>

        <td class='text-center'>
            <span class='badge {$statusClass}'>
                {$statusText}
            </span>
        </td>

    </tr>

    ";

    $no++;
}
?>

<tr class="total-row">

<th colspan="7">
TOTAL
</th>

<th>
<?= number_format($total_tagihan,0,',','.') ?>
</th>

<th>
<?= number_format($total_bayar,0,',','.') ?>
</th>

<th>
<?= number_format($total_sisa,0,',','.') ?>
</th>

<th>-</th>

</tr>

</table>

</div>

<button
type="button"
class="print-btn"
id="printBtn"
onclick="printTableOnly()">

Print

</button>

<script>

/* =========================================================
   ANTI DOUBLE SUBMIT
========================================================= */

const form = document.getElementById('filterForm');
const submitBtn = document.getElementById('submitBtn');

form.addEventListener('submit',function(){

    submitBtn.disabled = true;
    submitBtn.innerText = 'Loading...';

    document.body.classList.add('loading');
});

/* =========================================================
   EXPORT EXCEL
========================================================= */

function exportExcel(){

    const btn = document.getElementById('exportBtn');

    btn.disabled = true;
    btn.innerText = 'Loading...';

    window.location.href =
        'export_excelapdone.php?filter=<?= urlencode($filter) ?>&start=<?= urlencode($start) ?>&end=<?= urlencode($end) ?>';

    setTimeout(()=>{
        btn.disabled = false;
        btn.innerText = 'Export Excel';
    },3000);
}

/* =========================================================
   PRINT
========================================================= */

let printing = false;

function printTableOnly(){

    if(printing){
        return;
    }

    printing = true;

    const btn = document.getElementById('printBtn');

    btn.disabled = true;
    btn.innerText = 'Printing...';

    const printContents =
        document.querySelector('.table-container').innerHTML;

    const printWindow =
        window.open('','','width=1400,height=900');

    printWindow.document.write(`
        <html>
        <head>

        <title>
            Rekap Voucher pembelianho1 Claim Cash
        </title>

        <style>

        body{
            font-family:Arial;
            padding:20px;
            color:#000;
        }

        h2{
            text-align:center;
            margin-bottom:20px;
        }

        table{
            border-collapse:collapse;
            width:100%;
        }

        th,td{
            border:1px solid #000;
            padding:6px;
            font-size:11px;
        }

        th{
            background:#ddd !important;
            color:#000 !important;
            text-align:center;
        }

        td{
            text-align:right;
        }

        .text-left{
            text-align:left;
        }

        .text-center{
            text-align:center;
        }

        .ttd-table{
            width:100%;
            margin-top:70px;
            border-collapse:collapse;
        }

        .ttd-table td{
            border:none;
            text-align:center;
            vertical-align:top;
            width:20%;
            font-size:12px;
        }

        .ttd-space{
            height:90px;
        }

        </style>

        </head>

        <body>

        <h2>
            Rekap Voucher pembelianho1 Claim Cash
        </h2>

        ${printContents}

        <table class="ttd-table">

            <tr>
                <td>Admin Pabrik</td>
                <td>Spv Pabrik</td>
                <td>Manager Pabrik</td>
                <td>Finance HO</td>
                <td>Direktur</td>
            </tr>

            <tr>
                <td class="ttd-space"></td>
                <td class="ttd-space"></td>
                <td class="ttd-space"></td>
                <td class="ttd-space"></td>
                <td class="ttd-space"></td>
            </tr>

            <tr>
                <td>(.......................)</td>
                <td>(.......................)</td>
                <td>(.......................)</td>
                <td>(.......................)</td>
                <td>(.......................)</td>
            </tr>

        </table>

        </body>
        </html>
    `);

    printWindow.document.close();

    printWindow.focus();

    setTimeout(()=>{

        printWindow.print();

        printWindow.close();

        printing = false;

        btn.disabled = false;
        btn.innerText = 'Print';

    },500);
}

</script>

</body>
</html>

<?php

$stmt->close();

$conn->close();

?>