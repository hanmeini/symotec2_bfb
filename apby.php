<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start([
'cookie_lifetime'=>86400,
'cookie_httponly'=>true,
'cookie_secure'=>isset($_SERVER['HTTPS']),
'use_only_cookies'=>true,
'use_strict_mode'=>true,
]);





if(!isset($_SESSION['username'])){
header("Location:index.html");
exit();
}

require_once 'config1.php';





/* ================= FILTER ================= */

$filter=$_GET['filter'] ?? '';
$filter=trim($filter);

$status=$_GET['status'] ?? 'dibayar';

$cutoff=$_GET['cutoff'] ?? date('Y-m-d');
$cutoff=trim($cutoff);

if($cutoff==""){
$cutoff=date('Y-m-d');
}

$filter_sql="";

if($filter!=""){

$escaped=$conn->real_escape_string(
strtolower($filter)
);

$filter_sql=" AND (

LOWER(COALESCE(p.inv,'')) LIKE '%$escaped%' OR
LOWER(COALESCE(p.j,'')) LIKE '%$escaped%' OR
LOWER(COALESCE(s.nama,'')) LIKE '%$escaped%' OR
LOWER(COALESCE(p.sup,'')) LIKE '%$escaped%'

)";

}

/* ================= STATUS ================= */

/*
STATUS:
dibayar = bayar > 0 DAN masih ada sisa
lunas   = bayar > 0 DAN sisa <= 0
semua   = semua yang pernah dibayar
*/

$status_sql="";

if($status=="lunas"){

$status_sql="

AND COALESCE(p.bayar,0) > 0

AND (

    p.hargat_m - (

        COALESCE(p.bayar,0) +
        COALESCE(p.pph,0)

    )

) <= 0

";

}
elseif($status=="dibayar"){

$status_sql="

AND COALESCE(p.bayar,0) > 0

AND (

    p.hargat_m - (

        COALESCE(p.bayar,0) +
        COALESCE(p.pph,0)

    )

) > 0

AND p.j NOT LIKE 'co%'

";

}
else{

$status_sql="

AND COALESCE(p.bayar,0) > 0

";

}

/* ================= CUTOFF ================= */

$cutoff_escape=$conn->real_escape_string($cutoff);

/* ================= QUERY ================= */

$sql="

SELECT

p.id_transaksi,
p.tanggal_transaksi,
p.inv,
p.j,
p.sup,

s.nama AS supplier,

p.harga_m,
p.ppn_m,
p.hargat_m,

COALESCE(p.bayar,0) AS bayar,

p.jenispph,

COALESCE(p.pph,0) AS pph,

(

    p.hargat_m - (

        COALESCE(p.bayar,0) +
        COALESCE(p.pph,0)

    )

) AS sisa

FROM pembelianho1 p

LEFT JOIN sup s
ON p.sup=s.kode

WHERE 1=1

AND p.tanggal_transaksi <= '$cutoff_escape'

$status_sql

$filter_sql

ORDER BY p.tanggal_transaksi DESC

";

$result=$conn->query($sql);

if(!$result){
die("Query Error : ".$conn->error);
}

/* ================= TOTAL ================= */

$total_tagihan=0;
$total_bayar=0;
$total_sisa=0;
$total_pph=0;

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Laporan Supplier Sudah Dibayar</title>

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
max-height:650px;
overflow-y:auto;
}

h2{
text-align:center;
margin-bottom:20px;
}

form{
margin-bottom:20px;
text-align:center;
}

input[type=text],
input[type=date]{
padding:8px;
width:220px;
border:1px solid #ccc;
border-radius:6px;
}

select{
padding:8px;
border-radius:6px;
}

button{
padding:8px 16px;
border:none;
background:#4CAF50;
color:white;
border-radius:6px;
cursor:pointer;
}

button:hover{
background:#45a049;
}

table{
border-collapse:collapse;
width:100%;
margin-top:20px;
}

th,td{
padding:10px;
border:1px solid #ddd;
font-size:14px;
}

th{
background:blue;
position:sticky;
top:0;
z-index:2;
color:white;
text-align:center;
}

td{
text-align:right;
}

tr:hover{
background:#f1f1f1;
}

.action-icon button{
margin:2px;
padding:5px 10px;
background:#007BFF;
border:none;
color:white;
border-radius:5px;
cursor:pointer;
}

.action-icon button:hover{
background:#0056b3;
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

.badge-lunas{
background:#28a745;
color:#fff;
padding:4px 8px;
border-radius:5px;
font-size:12px;
}

.badge-belum{
background:#ff9800;
color:#fff;
padding:4px 8px;
border-radius:5px;
font-size:12px;
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

<h2>
Laporan Supplier Sudah Dibayar
</h2>

<form method="get">

<input
type="text"
name="filter"
placeholder="Filter invoice / jurnal / supplier"
value="<?=htmlspecialchars($filter ?? '')?>">

<input
type="date"
name="cutoff"
value="<?=htmlspecialchars($cutoff ?? '')?>">

<select name="status">

<option
value=""
<?= $status=="" ? "selected":"" ?>>
-- Semua Yang Pernah Dibayar --
</option>

<option
value="lunas"
<?= $status=="lunas" ? "selected":"" ?>>
Sudah Lunas
</option>

<option
value="dibayar"
<?= $status=="dibayar" ? "selected":"" ?>>
Dibayar Sebagian
</option>

</select>

<button type="submit">
Filter
</button>

<button
type="button"
onclick="
window.location.href=
'export_excelapdone.php?
filter=<?=urlencode($filter ?? '')?>
&status=<?=urlencode($status ?? '')?>
&cutoff=<?=urlencode($cutoff ?? '')?>'
">
Export Excel
</button>

</form>

<div class="table-container">

<table>

<tr>

<th>No</th>
<th>Tanggal</th>
<th>Invoice</th>
<th>No Jurnal</th>
<th>Supplier</th>

<th>DPP</th>
<th>PPN</th>
<th>Total</th>

<th>Bayar</th>

<th>Jenis PPH</th>
<th>PPH</th>

<th>Sisa</th>

<th>Status</th>

<th>Action</th>

</tr>

<?php

$no=1;

while($row=$result->fetch_assoc()){

$tagihan=(float)$row['hargat_m'];
$bayar=(float)$row['bayar'];
$pph=(float)$row['pph'];
$sisa=(float)$row['sisa'];

$total_tagihan += $tagihan;
$total_bayar += $bayar;
$total_sisa += $sisa;
$total_pph += $pph;

$statusLabel='';

if($sisa<=0){

$statusLabel="
<span class='badge-lunas'>
LUNAS
</span>
";

}else{

$statusLabel="
<span class='badge-belum'>
BELUM LUNAS
</span>
";

}

echo "

<tr>

<td style='text-align:center'>
".$no."
</td>

<td style='text-align:center'>
".htmlspecialchars($row['tanggal_transaksi'] ?? '')."
</td>

<td style='text-align:left'>
".htmlspecialchars($row['inv'] ?? '')."
</td>

<td style='text-align:left'>
".htmlspecialchars($row['j'] ?? '')."
</td>

<td style='text-align:left'>
".htmlspecialchars($row['supplier'] ?? '')."
</td>

<td>
".number_format((float)$row['harga_m'],0,',','.')."
</td>

<td>
".number_format((float)$row['ppn_m'],0,',','.')."
</td>

<td>
".number_format($tagihan,0,',','.')."
</td>

<td>
".number_format($bayar,0,',','.')."
</td>

<td style='text-align:center'>
".htmlspecialchars($row['jenispph'] ?? '')."
</td>

<td>
".number_format($pph,0,',','.')."
</td>

<td>
".number_format($sisa,0,',','.')."
</td>

<td style='text-align:center'>
$statusLabel
</td>

<td class='action-icon'>

<form
method='get'
action='detail_pembelian.php'
onsubmit='return false;'>

<input
type='hidden'
name='inv'
value='".htmlspecialchars(
$row['inv'] ?? '',
ENT_QUOTES
)."'>


<button
type='button'
onclick='openDetail(\"".addslashes($row['j'] ?? '')."\")'>

<i class='fa fa-eye'></i>
Detail

</button>

</form>

</td>

</tr>

";

$no++;

}

?>

<tr>

<th colspan="7">
TOTAL
</th>

<th>
<?=number_format($total_tagihan,0,',','.')?>
</th>

<th>
<?=number_format($total_bayar,0,',','.')?>
</th>

<th></th>

<th>
<?=number_format($total_pph,0,',','.')?>
</th>

<th>
<?=number_format($total_sisa,0,',','.')?>
</th>

<th></th>

<th></th>

</tr>

</table>

</div>

<script>

function openDetail(inv){

window.open(

'detail_pembelian.php?inv='+
encodeURIComponent(inv),

'DetailPembelian',

'width=900,height=700,scrollbars=yes,resizable=yes'

);

}

</script>

</body>
</html>

<?php
$conn->close();
?>