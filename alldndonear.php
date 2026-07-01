<?php
session_start([
    'cookie_lifetime'=>86400,'cookie_httponly'=>true,
    'cookie_secure'=>isset($_SERVER['HTTPS']),
    'use_only_cookies'=>true,'use_strict_mode'=>true,
]);

if(!isset($_SESSION['username'])){ header("Location:index.html");exit();}
require_once 'config1.php';


if($conn->connect_error||$conn->connect_error) die("DB error");

$filter=trim($_GET['filter_customer'] ?? '');
$where="inv IS NOT NULL AND dn > 0";
if($filter!==''){
    $esc=$conn->real_escape_string($filter);
    $res=$conn->query("SELECT id FROM customer WHERE customer LIKE '%$esc%'");
    $ids=[];
    while($r=$res->fetch_assoc()) $ids[]=intval($r['id']);
    if(count($ids)>0) $where.=" AND id_cust IN (".implode(',',$ids).")"; else $where.=" AND 0";
}

$sql="SELECT idn,no_cn_dn,kode_booking,dn,inv,tanggal,id_parent,id_cust
      FROM cndnar WHERE $where ORDER BY tanggal,idn";
$res=$conn->query($sql);
$total_dn=0;
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<title>DN yang sudah digunakan</title>
<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; background-color: #f9f9f9; color: #333; margin: 20px; }
h1 { text-align: center; color: #4CAF50; }
form { text-align: center; margin-bottom: 20px; }
label { font-weight: bold; margin-right: 10px; }
input[type="text"] { padding: 6px; width: 200px; }
button, a.btn { padding: 6px 12px; margin-left: 5px; border-radius: 4px; text-decoration: none; color: white; }
button { background-color: #4CAF50; border: none; cursor: pointer; }
button:hover { background-color: #45a049; }
a.btn-secondary { background-color: #6c757d; }
a.btn-secondary:hover { background-color: #5a6268; }
a.btn-success { background-color: #28a745; }
a.btn-success:hover { background-color: #218838; }
table { width: 100%; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
th { background-color: #4CAF50; color: white; }
th:first-child, td:first-child { text-align: center; }
td.action { text-align: center; }
tr:nth-child(even) { background-color: #f2f2f2; }
tr:hover { background-color: #ddd; }

 .home-icon1 {
            position: absolute;
            left: 0;
            top: 0;
            padding-left: 10px;
            color: maroon;
            font-size: 24px;
        }

        .left-icon {
            position: absolute;
            right: 0;
            top: 0;
            padding-right: 10px;
            color: maroon;
            font-size: 24px;
        }
      
@media (max-width: 768px) {
    th, td { padding: 6px; font-size: 12px; }
    input[type="text"] { width: 140px; }
}
</style>
<body>
     <div class="table-container">
        <a href="home.php" class="home-icon1">
            <i class="fas fa-home"></i>
        </a>
        <a href="alldnar.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
<title>DN yang sudah digunakan</title></head><body>
<h1>DN AR yang sudah digunakan</h1>
<form method="GET" style="text-align:center;" class="mb-3">
    <input name="filter_customer" value="<?= htmlspecialchars($filter, ENT_QUOTES) ?>" class="form-control d-inline-block" style="width: 200px; display: inline-block; margin-right: 10px;">
    
    <button type="submit" class="btn btn-primary">Tampilkan</button>
    
    <a href="alldndonear.php" class="btn btn-secondary">Reset</a>
    
    <a href="exportdndonear_excel.php?filter_customer=<?= urlencode($filter) ?>" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export Excel
    </a>
</form>

<table border="1"><tr>
<th>ID</th><th>No DN</th><th>Kode Booking</th><th>Tanggal</th><th>Nominal</th>
<th>INV</th><th>Parent</th><th>Customer</th></tr>
<?php if($res && $res->num_rows>0): while($row=$res->fetch_assoc()):
    $cust=''; $rc=$conn->query("SELECT customer FROM customer WHERE id=".intval($row['id_cust']));
    if($r2=$rc->fetch_assoc()) $cust=$r2['customer'];
    $total_dn += $row['dn'];
?>
<tr>
<td><?=htmlspecialchars($row['idn'])?></td>
<td><?=htmlspecialchars($row['no_cn_dn'])?></td>
<td><?=htmlspecialchars($row['kode_booking'])?></td>
<td><?=htmlspecialchars($row['tanggal'])?></td>
<td align="right"><?=number_format($row['dn'],2)?></td>
<td><?=htmlspecialchars($row['inv'])?></td>
<td><?=htmlspecialchars($row['id_parent'])?></td>
<td><?=htmlspecialchars($cust)?></td>
</tr>
<?php endwhile;?>
<tr><td colspan="4"><b>Total DN</b></td><td align="right"><?=number_format($total_dn,2)?></td><td colspan="3"></td></tr>
<?php else: ?>
<tr><td colspan="8">Tidak ada data.</td></tr>
<?php endif;?>
</table>
</body></html>
<?php $conn->close(); ?>
