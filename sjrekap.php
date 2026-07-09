<?php
session_start();
require_once 'config1.php';

error_reporting(E_ALL);
ini_set('display_errors',1);

if(!isset($_SESSION['userid'])){
    header("Location:index.html");
    exit();
}

function db_connect(){
    $c=new mysqli(
        getenv('DB_HOST'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        getenv('DB_NAME')
    );

    if($c->connect_error){
        die($c->connect_error);
    }

    $c->set_charset('utf8mb4');
    return $c;
}

function e($s){
    return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
}

/* ================= FILTER ================= */

$today=date('Y-m-d');

$filter_dari=$_GET['tanggal_dari'] ?? $today;
$filter_sampai=$_GET['tanggal_sampai'] ?? $today;
$filter_sj=$_GET['sj'] ?? '';

$list=[];

$c=db_connect();

$where=" WHERE tr.notrim IS NOT NULL ";
$params=[];
$types="";

/* FILTER TANGGAL */

if(!empty($filter_dari) && !empty($filter_sampai)){
    $where.=" AND DATE(tr.tanggal) BETWEEN ? AND ? ";
    $params[]=$filter_dari;
    $params[]=$filter_sampai;
    $types.="ss";
}

/* FILTER SJ */

if(!empty($filter_sj)){
    $where.=" AND t1.sj LIKE ? ";
    $params[]="%".$filter_sj."%";
    $types.="s";
}

$sql="

SELECT
t1.sj,
tr.notrim,
DATE(tr.tanggal),

COALESCE(ga.nama_gudang,'-'),
COALESCE(gt.nama_gudang,'-')

FROM transaksiho1 t1

LEFT JOIN transaksiho1 t2
ON t2.sj=t1.sj
AND t2.jumlah_m>0

LEFT JOIN master_gudang ga
ON ga.id_gudang=t1.id_gudang

LEFT JOIN master_gudang gt
ON gt.id_gudang=t2.id_gudang

LEFT JOIN terima tr
ON tr.sj=t1.sj

$where

GROUP BY
t1.sj,
tr.notrim,
DATE(tr.tanggal),
ga.nama_gudang,
gt.nama_gudang

ORDER BY tr.tanggal DESC

";

$stmt=$c->prepare($sql);

if(!empty($params)){
    $stmt->bind_param($types,...$params);
}

$stmt->execute();

$stmt->bind_result(
    $sj,
    $notrim,
    $tanggal,
    $pengirim,
    $penerima
);

while($stmt->fetch()){
    $list[]=[
        'sj'=>$sj,
        'notrim'=>$notrim,
        'tanggal'=>$tanggal,
        'pengirim'=>$pengirim,
        'penerima'=>$penerima
    ];
}

$stmt->close();
$c->close();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>Rekap SJ Diterima</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{
font-family:Arial;
background:#f4f6f8;
padding:20px;
}

.wrap{
max-width:1100px;
margin:auto;
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

h2{
margin-bottom:15px;
}

table{
width:100%;
border-collapse:collapse;
margin-top:20px;
}

th,td{
padding:10px;
border-bottom:1px solid #ddd;
text-align:center;
}

th{
background:#6c757d;
color:white;
}

tr:hover{
background:#f1f1f1;
}

button{
padding:7px 14px;
border:none;
border-radius:6px;
background:#6c757d;
color:white;
cursor:pointer;
}

button:hover{
background:#5a6268;
}

.filter-box{
background:#f9f9f9;
padding:15px;
border-radius:8px;
margin-bottom:15px;
}

input{
padding:6px;
border:1px solid #ccc;
border-radius:5px;
margin-right:10px;
}

.home{
font-size:22px;
color:#333;
text-decoration:none;
}

.home:hover{
color:#28a745;
}

</style>

</head>

<body>

<a href="home.php" class="home">
<i class="fas fa-home"></i>
</a>

<div class="wrap">

<h2>Rekap SJ Sudah Diterima</h2>

<div style="margin-bottom:15px;">
<a href="antarin.php">
<button>Kembali</button>
</a>
</div>

<div class="filter-box">

<form method="GET">

<label>Dari:</label>
<input type="date" name="tanggal_dari" value="<?=e($filter_dari)?>">

<label>Sampai:</label>
<input type="date" name="tanggal_sampai" value="<?=e($filter_sampai)?>">

<label>No SJ:</label>
<input type="text" name="sj" value="<?=e($filter_sj)?>" placeholder="Cari SJ">

<button type="submit">Filter</button>

<button type="button" onclick="location.href='sjrekap.php'">
Reset
</button>

</form>

</div>

<table>

<tr>
<th>No SJ</th>
<th>No TERIMA</th>
<th>Gudang Asal</th>
<th>Gudang Tujuan</th>
<th>Tanggal Terima</th>
</tr>

<?php if(empty($list)): ?>

<tr>
<td colspan="5">Data tidak ditemukan</td>
</tr>

<?php else: foreach($list as $s): ?>

<tr>

<td><?=e($s['sj'])?></td>
<td><?=e($s['notrim'])?></td>
<td><?=e($s['pengirim'])?></td>
<td><?=e($s['penerima'])?></td>
<td><?=e($s['tanggal'])?></td>

</tr>

<?php endforeach; endif; ?>

</table>

</div>

</body>
</html>