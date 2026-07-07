<?php
session_start();

if(!isset($_SESSION['userid'])){
    header("Location:../index.php");
    exit();
}

/* ===============================
  KONEKSI DATABASE & INISIALISASI
=============================== */

// Load konfigurasi dari direktori root
require_once '../config.php';

$host = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$dbname = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");
$username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$password = getenv('DB_PASS'); 

// Ambil ID Gudang dari parameter URL (?id=...), default ke 8
$id_gudang = isset($_GET['id']) ? (int)$_GET['id'] : 8;

// Kondisi: HANYA ada 1 gudang yang memiliki menu Alih Status.
// Silakan ganti angka 0 di bawah ini dengan ID gudang yang diperbolehkan.
$gudang_dengan_alih_status = 0; 
$show_alih_status = ($id_gudang === $gudang_dengan_alih_status);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ===============================
    // VALIDASI AKSES SALES
    // ===============================
    $jabatan = $_SESSION['jabatan'] ?? null;
    $userid = $_SESSION['userid'] ?? null;
    $bagian = $_SESSION['bagian'] ?? null;
    
    if ($bagian === 'sales') { // Jika sales
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM master_sales WHERE userid = ? AND id_gudang = ?");
        $stmt_check->execute([$userid, $id_gudang]);
        $is_sales = $stmt_check->fetchColumn();
        
        if (!$is_sales) {
            echo "<script>alert('Anda tidak memiliki akses ke halaman sales ini.'); window.location.href='../home.php';</script>";
            exit();
        }
    }

    // Menghitung jumlah kirim belum diproses untuk gudang ini
    $sql1 = "SELECT COUNT(*) FROM antar WHERE (notrim='' OR notrim IS NULL) AND pengirim = ?";
    $stmt1 = $pdo->prepare($sql1);
    $stmt1->execute([$id_gudang]);
    $gudangkirimbelum = $stmt1->fetchColumn();
    
    // Menghitung jumlah terima belum diproses untuk gudang ini
    $sql2 = "SELECT COUNT(*) FROM antar WHERE (notrim='' OR notrim IS NULL) AND penerima = ?";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$id_gudang]);
    $gudangterimabelum = $stmt2->fetchColumn();
    
    // Menghitung jumlah AP Sales yang belum lunas
    $sql3 = "SELECT COUNT(*) FROM penjualanHO1 WHERE sisa > 0";
    if (isset($_SESSION['location']) && $_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
        $sql3 .= " AND userinv = ?";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([$_SESSION['username']]);
        $apsalesbelum = $stmt3->fetchColumn();
    } else {
        $stmt3 = $pdo->query($sql3);
        $apsalesbelum = $stmt3->fetchColumn();
    }
    
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>mkb.symotech.id</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

/* ===== GLOBAL ===== */
body{
background:url('../background.jpg') center/cover no-repeat fixed;
font-family:'Segoe UI',Arial,sans-serif;
margin:0;
padding-top:110px;
display:flex;
justify-content:center;
align-items:flex-start;
min-height:100vh;
text-align:center;
}

body::before{
content:"";
position:fixed;
inset:0;
background:rgba(0,0,0,0.35);
z-index:-1;
}

/* HEADER */
header{
position:fixed;
top:0;
left:0;
width:100%;
height:70px;
display:flex;
align-items:center;
justify-content:flex-end;
padding:0 20px;
z-index:10;
}

.logo{
position:absolute;
left:15px;
top:8px;
width:110px;
}

/* CONTAINER */
.container{
width:95%;
max-width:950px;
background:rgba(255,255,255,0.08);
backdrop-filter:blur(12px);
border-radius:18px;
padding:40px 35px;
box-shadow:0 10px 40px rgba(0,0,0,0.45);
}

/* TITLE */
h1{
color:white;
font-size:40px;
letter-spacing:2px;
margin-bottom:35px;
}

/* GRID */
.icons{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:25px;
}

/* CARD */
.icon{
background:rgba(255,255,255,0.12);
border-radius:14px;
padding:30px 20px;
transition:.3s;
border:1px solid rgba(255,255,255,0.2);
}

.icon:hover{
transform:translateY(-8px) scale(1.03);
background:rgba(255,255,255,0.18);
box-shadow:0 15px 35px rgba(0,0,0,0.5);
}

.icon i{
font-size:38px;
margin-bottom:12px;
color:#ffffff;
}

.icon p{
margin:0;
color:white;
font-weight:600;
font-size:15px;
}

.badge{
color:red;
font-weight:bold;
font-size:22px;
margin-bottom:10px;
}

/* FOOTER */
footer{
position:fixed;
bottom:0;
width:100%;
text-align:center;
color:white;
font-size:12px;
padding:5px;
background:rgba(0,0,0,0.35);
}

/* MOBILE */
@media(max-width:720px){

.container{padding:25px 20px;}

h1{font-size:26px;}

.icons{
grid-template-columns:1fr;
gap:15px;
}

.icon{padding:22px;}

}

/* ===== BADGE OVERLAY ===== */
.icon {
    position: relative;
    overflow: visible;
}

.badge-overlay {
    position: absolute;
    top: 6px;
    right: 6px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    z-index: 50;
}

.badge-overlay span {
    padding: 3px 7px;
    min-width: 26px;
    text-align: center;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}

.badge-red {
    background: #d9534f;  
    color: #fff;
}

</style>
</head>

<body>

<header>

<img src="../assets/img/logo.png" class="logo">

<a href="../home.php" style="position:absolute; right:50px; top:20px; font-size:24px; color:red; text-decoration:none;">
<i class="fa fa-home"></i>

</a>

</header>

<div class="container">

<h1 id="animated-heading">PT. BFB <?= ($id_gudang === 0) ? 'GUDANG PUSAT' : 'SALES ' . htmlspecialchars((string)$id_gudang) ?></h1>

<section id="hero">

<div class="icons">

<!-- KIRIM -->
<div class="icon">
    <?php if($gudangkirimbelum > 0): ?>
    <div class="badge-overlay"><span class="badge-red"><?= $gudangkirimbelum ?></span></div>
    <?php endif; ?>
<a href="../sjrekapout.php?id=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-truck"></i>
<p>Kirim SJ Ke Gudang Lain</p>
</a>
</div>


<!-- TERIMA -->
<div class="icon">
    <?php if($gudangterimabelum > 0): ?>
    <div class="badge-overlay"><span class="badge-red"><?= $gudangterimabelum ?></span></div>
    <?php endif; ?>
<a href="../antarin.php?id=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-cart-arrow-down"></i>
<p>SJ Dari Gudang Lain</p>
</a>
</div>

<?php if ($show_alih_status): ?>
<div class="icon">
<a href="../alih_status.php?id=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa fa-random"></i>
<p>Alih Status</p>
</a>
</div>
<?php endif; ?>

<div class="icon">
<a href="../stock.php?id=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-brands fa-docker"></i>
<p>STOCK</p>
</a>
</div>

<!-- POS -->
<div class="icon">
<a href="../pos.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-cash-register"></i>
<p>POS</p>
</a>
</div>

<!-- AP SALES -->
<div class="icon">
    <?php if($apsalesbelum > 0): ?>
    <div class="badge-overlay"><span class="badge-red"><?= $apsalesbelum ?></span></div>
    <?php endif; ?>
<a href="../ap_sales.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-file-invoice-dollar"></i>
<p>AR Sales</p>
</a>
</div>

<!-- NEW MENUS -->
<div class="icon">
<a href="../retur_penjualan.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-rotate-left"></i>
<p>Retur Penjualan</p>
</a>
</div>

<?php if ($id_gudang == 0): ?>
<div class="icon">
<a href="../retur_pembelian.php" style="text-decoration:none;">
<i class="fa-solid fa-truck-ramp-box"></i>
<p>Retur Pembelian</p>
</a>
</div>
<?php endif; ?>


<div class="icon">
<a href="../reports.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-chart-line"></i>
<p>Report Penjualan</p>
</a>
</div>

<div class="icon">
<a href="../aging.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-calendar-alt"></i>
<p>Laporan Aging</p>
</a>
</div>

<div class="icon">
<a href="../pembayaranreport.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-money-bill-wave"></i>
<p>Report Uang Masuk</p>
</a>
</div>

<div class="icon">
<a href="../reportin.php?id_gudang=<?= $id_gudang ?>" style="text-decoration:none;">
<i class="fa-solid fa-arrow-right-to-bracket"></i>
<p>Report Barang Masuk</p>
</a>
</div>

<!-- BACK -->
<div class="icon">

<a href="../home.php" style="text-decoration:none;">
<i class="fa-solid fa-file-export"></i>
<p>Back To Office</p>
</a>

</div>

</div>

</section>

</div>

<footer>
<p>© 2025 SYMOTECH</p>
</footer>

<script>

/* ANIMASI TEXT */
document.addEventListener("DOMContentLoaded",function(){

var heading=document.getElementById('animated-heading');
var text=heading.textContent.trim();
heading.textContent='';

for(var i=0;i<text.length;i++){

var span=document.createElement('span');
span.textContent=text[i];
span.style.animation="jatuh 1s ease forwards";
span.style.animationDelay=(i*0.1)+"s";

heading.appendChild(span);

}

});

/* DISABLE INSPECT */
document.addEventListener('contextmenu',e=>e.preventDefault());

document.addEventListener('keydown',function(e){

if(e.keyCode==123 ||
(e.ctrlKey && e.shiftKey && ['I','C'].includes(String.fromCharCode(e.keyCode))) ||
(e.ctrlKey && e.keyCode=='U'.charCodeAt(0))
){
e.preventDefault();
}

});

</script>

</body>
</html>