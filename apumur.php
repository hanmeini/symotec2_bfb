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





$filter=$_GET['filter'] ?? '';
$filter=trim($filter);

$filter_sql="";

if($filter!=""){

$escaped=$conn->real_escape_string(strtolower($filter));

$filter_sql=" AND (
LOWER(p.inv) LIKE '%$escaped%' OR
LOWER(p.j) LIKE '%$escaped%' OR
LOWER(s.nama) LIKE '%$escaped%' OR
LOWER(p.sup) LIKE '%$escaped%'
)";
}

?>
<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<title>AGING HUTANG SUPPLIER</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{
font-family:Arial;
padding:20px;
background:#f2f2f2;
}

h1{
text-align:center;
color:#4CAF50;
}

form{
text-align:center;
margin-bottom:20px;
}

input[type=text]{
padding:8px;
width:300px;
border-radius:4px;
border:1px solid #ccc;
}

button{
padding:8px 16px;
border:none;
background:#4CAF50;
color:white;
border-radius:4px;
cursor:pointer;
}

button:hover{
background:#45a049;
}

table{
width:100%;
border-collapse:collapse;
background:white;
box-shadow:0 2px 5px rgba(0,0,0,0.1);
}

th,td{
border:1px solid #ddd;
padding:10px;
text-align:right;
}

th{
background:#4CAF50;
color:white;
text-align:center;
}

tr:nth-child(even){
background:#f2f2f2;
}

tr:hover{
background:#ddd;
}

.total-row{
font-weight:bold;
background:#e0ffe0;
}

.home-icon{
position:absolute;
left:10px;
top:10px;
font-size:24px;
color:maroon;
}

.left-icon{
position:absolute;
right:10px;
top:10px;
font-size:24px;
color:maroon;
}

</style>
</head>

<body>

<a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
<a href="ap.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<h1>AGING HUTANG SUPPLIER</h1>

<form method="get">

<input type="text"
name="filter"
placeholder="Filter invoice / jurnal / supplier"
value="<?=htmlspecialchars($filter)?>">

<button type="submit">Filter</button>

<button type="button"
onclick="location.href='export_excelapu.php?filter=<?=urlencode($filter)?>'">
Export Excel
</button>

</form>

<?php

$today=date('Y-m-d');

$sql="
SELECT
p.id_transaksi,
p.tanggal_transaksi,
p.inv,
p.j,
s.nama AS supplier,
p.sisa,
p.hargat_m,
p.bayar,
DATEDIFF(CURDATE(),p.tanggal_transaksi) AS umur,
p.userid
FROM pembelianho1 p
LEFT JOIN sup s ON p.sup=s.kode
WHERE p.sisa>0
$filter_sql
";

$result=$conn->query($sql);

$rows=[];

while($row=$result->fetch_assoc()){
$rows[]=$row;
}

usort($rows,function($a,$b){
return $b['umur'] <=> $a['umur'];
});

$total_sisa=0;
$total_1_30=0;
$total_31_60=0;
$total_61_90=0;
$total_90_plus=0;

if(count($rows)>0){

echo "<table>

<tr>

<th>ID</th>
<th>Tanggal</th>
<th>Invoice</th>
<th>No Jurnal</th>
<th>Supplier</th>
<th>Sisa</th>
<th>1-30 Hari</th>
<th>31-60 Hari</th>
<th>61-90 Hari</th>
<th>>90 Hari</th>
<th>User</th>

</tr>";

foreach($rows as $row){

$hari=intval($row['umur']);
$sisa=floatval($row['sisa']);

$sisa_1_30=($hari>=1 && $hari<=30)?$sisa:0;
$sisa_31_60=($hari>=31 && $hari<=60)?$sisa:0;
$sisa_61_90=($hari>=61 && $hari<=90)?$sisa:0;
$sisa_90_plus=($hari>90)?$sisa:0;

$total_sisa+=$sisa;
$total_1_30+=$sisa_1_30;
$total_31_60+=$sisa_31_60;
$total_61_90+=$sisa_61_90;
$total_90_plus+=$sisa_90_plus;

echo "<tr>

<td>{$row['id_transaksi']}</td>

<td>{$row['tanggal_transaksi']}</td>

<td>{$row['inv']}</td>

<td>{$row['j']}</td>

<td style='text-align:left'>{$row['supplier']}</td>

<td>".number_format($sisa,2)."</td>

<td>".number_format($sisa_1_30,2)."</td>

<td>".number_format($sisa_31_60,2)."</td>

<td>".number_format($sisa_61_90,2)."</td>

<td>".number_format($sisa_90_plus,2)."</td>

<td>{$row['userid']}</td>

</tr>";

}

echo "<tr class='total-row'>

<td colspan='5' style='text-align:center'>TOTAL</td>

<td>".number_format($total_sisa,2)."</td>

<td>".number_format($total_1_30,2)."</td>

<td>".number_format($total_31_60,2)."</td>

<td>".number_format($total_61_90,2)."</td>

<td>".number_format($total_90_plus,2)."</td>

<td></td>

</tr>";

echo "</table>";

}else{

echo "<p style='text-align:center'>Tidak ada data ditemukan.</p>";

}

$conn->close();

?>

</body>
</html>