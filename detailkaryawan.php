<?php
require_once 'functions.php'; // db_connect(), e()
$conn = db_connect();

// Validasi ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID tidak valid.");
}
$id = intval($_GET['id']);

// Ambil data karyawan (tanpa get_result)
$sql = "SELECT no_staff, nama, LP, dept, jabatan, jenis_gaji,
               tgl_masuk, tgl_lahir, alamat,
               foto, nik, foto_ktp, kk, foto_kk, status_menikah, jumlah_tanggungan,
               no_telp, pendidikan, nama_darurat, no_darurat,
               bpjs_kes, bpjs_tk, gaji_pokok, upah_lembur, aktive
        FROM data_karyawan WHERE no_staff = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    $stmt->close();
    die("Data tidak ditemukan.");
}

// Bind hasil
$stmt->bind_result(
    $no_staff, $nama, $LP, $dept_id, $jab_id, $jenis_gaji,
    $tgl_masuk, $tgl_lahir, $alamat,
    $foto, $nik, $foto_ktp, $kk, $foto_kk, $status_menikah, $jumlah_tanggungan,
    $no_telp, $pendidikan, $nama_darurat, $no_darurat,
    $bpjs_kes, $bpjs_tk, $gaji_pokok, $upah_lembur, $aktive
);
$stmt->fetch();
$stmt->close();

// Ambil nama departemen
$depName = "-";
$dq = $conn->prepare("SELECT nama_bagian FROM bagian WHERE id=? LIMIT 1");
$dq->bind_param("i", $dept_id);
$dq->execute();
$dq->store_result();
$dq->bind_result($depName);
$dq->fetch();
$dq->close();

// Ambil nama jabatan
$jabName = "-";
$jq = $conn->prepare("SELECT jabatan FROM jabatan WHERE idj=? LIMIT 1");
$jq->bind_param("i", $jab_id);
$jq->execute();
$jq->store_result();
$jq->bind_result($jabName);
$jq->fetch();
$jq->close();

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Detail Karyawan</title>

<style>
body{
    font-family:Segoe UI,sans-serif;
    background:#eef2f7;
    padding:20px;
}
.container{
    max-width:950px;
    margin:auto;
    background:white;
    padding:25px;
    border-radius:12px;
    position:relative;
}
h2{text-align:center;margin-bottom:25px}
.table-info td{padding:8px 4px;font-size:15px;vertical-align:top}

.foto-box{
    position:absolute;
    top:20px;
    right:20px;
    text-align:center;
}
.foto-box img{
    width:150px;
    height:150px;
    object-fit:cover;
    border-radius:10px;
    border:2px solid #ddd;
    cursor:pointer;
    transition:transform 0.2s;
}
.foto-box img:hover{
    transform:scale(1.05);
}
.btn{
    padding:8px 14px;
    background:#17a2b8;
    text-decoration:none;
    color:#fff;
    border-radius:6px;
    font-size:14px;
}
.btn-back{
    background:#6c757d;
}
.btn-small{
    padding:6px 10px;
    font-size:13px;
}

/* ===== MODAL FOTO ===== */
.modal{
    display:none;
    position:fixed;
    z-index:9999;
    left:0; top:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.85);
    justify-content:center;
    align-items:center;
}
.modal.active{
    display:flex;
}
.modal-content{
    position:relative;
    background:none;
    border:none;
    box-shadow:none;
    text-align:center;
}
.modal-content img{
    max-width:90vw;
    max-height:90vh;
    border-radius:10px;
    object-fit:contain;
}
.close{
    position:absolute;
    top:-30px;
    right:0;
    color:white;
    font-size:35px;
    font-weight:bold;
    cursor:pointer;
}
</style>

<script>
function showModal(id){
    document.getElementById(id).classList.add("active");
}
function closeModal(id){
    document.getElementById(id).classList.remove("active");
}
// Tutup modal jika klik di luar gambar
window.onclick = function(e){
    document.querySelectorAll(".modal").forEach(m=>{
        if(e.target === m){ m.classList.remove("active"); }
    });
}
</script>
</head>
<body>

<div class="container">

<h2>Detail Data Karyawan</h2>

<!-- FOTO PROFIL DI KANAN -->
<div class="foto-box">
    <?php if($foto){ ?>
        <img src="uploads/<?= esc($foto) ?>" alt="Foto Profil" onclick="showModal('modalProfil')">
    <?php } else { ?>
        <img src="noimage.png" alt="Tidak ada foto">
    <?php } ?>
    <br><br>

    <?php if($foto_ktp){ ?>
        <button class="btn btn-small" onclick="showModal('modalKTP')">Lihat KTP</button><br><br>
    <?php } ?>

    <?php if($foto_kk){ ?>
        <button class="btn btn-small" onclick="showModal('modalKK')">Lihat KK</button>
    <?php } ?>
</div>

<table class="table-info">
<tr><td><b>No Staff</b></td><td>: <?= esc($no_staff) ?></td></tr>
<tr><td><b>Nama</b></td><td>: <?= esc($nama) ?></td></tr>
<tr><td><b>Jenis Kelamin</b></td><td>: <?= esc($LP) ?></td></tr>
<tr><td><b>Departemen</b></td><td>: <?= esc($depName) ?></td></tr>
<tr><td><b>Jabatan</b></td><td>: <?= esc($jabName) ?></td></tr>
<tr><td><b>Tgl Masuk</b></td><td>: <?= esc($tgl_masuk) ?></td></tr>
<tr><td><b>Tgl Lahir</b></td><td>: <?= esc($tgl_lahir) ?></td></tr>
<tr><td><b>NIK</b></td><td>: <?= esc($nik) ?></td></tr>
<tr><td><b>KK</b></td><td>: <?= esc($kk) ?></td></tr>
<tr><td><b>Status Menikah</b></td><td>: <?= esc($status_menikah) ?></td></tr>
<tr><td><b>Jumlah Tanggungan</b></td><td>: <?= esc($jumlah_tanggungan) ?></td></tr>
<tr><td><b>No Telp</b></td><td>: <?= esc($no_telp) ?></td></tr>
<tr><td><b>Pendidikan</b></td><td>: <?= esc($pendidikan) ?></td></tr>
<tr><td><b>Nama Darurat</b></td><td>: <?= esc($nama_darurat) ?></td></tr>
<tr><td><b>No Darurat</b></td><td>: <?= esc($no_darurat) ?></td></tr>
<tr><td><b>BPJS Kesehatan</b></td><td>: <?= esc($bpjs_kes) ?></td></tr>
<tr><td><b>BPJS Ketenagakerjaan</b></td><td>: <?= esc($bpjs_tk) ?></td></tr>
<tr><td><b>Gaji Pokok</b></td><td>: Rp <?= number_format($gaji_pokok,0,',','.') ?></td></tr>
<tr><td><b>Jenis Gaji</b></td><td>: <?= esc(ucfirst($jenis_gaji)) ?></td></tr>
<tr><td><b>Upah Lembur</b></td><td>: Rp <?= number_format($upah_lembur,0,',','.') ?></td></tr>
<tr><td><b>Alamat</b></td><td>: <?= nl2br(esc($alamat)) ?></td></tr>
<tr><td><b>Status</b></td>
<td>:
    <?php if($aktive=='aktive'){ ?>
        <span style="color:green;font-weight:bold">AKTIVE</span>
    <?php } else { ?>
        <span style="color:red;font-weight:bold">NONAKTIVE</span>
    <?php } ?>
</td></tr>
</table>

<br><br>
<a href="karyawan.php" class="btn btn-back">Kembali</a>
<a href="edit_karyawan.php?id=<?= esc($id) ?>" class="btn">Edit</a>

</div>

<!-- MODAL FOTO PROFIL -->
<div class="modal" id="modalProfil">
  <div class="modal-content">
    <span class="close" onclick="closeModal('modalProfil')">&times;</span>
    <img src="uploads/<?= esc($foto) ?>" alt="Foto Profil">
  </div>
</div>

<!-- MODAL KTP -->
<div class="modal" id="modalKTP">
  <div class="modal-content">
    <span class="close" onclick="closeModal('modalKTP')">&times;</span>
    <img src="uploads/<?= esc($foto_ktp) ?>" alt="Foto KTP">
  </div>
</div>

<!-- MODAL KK -->
<div class="modal" id="modalKK">
  <div class="modal-content">
    <span class="close" onclick="closeModal('modalKK')">&times;</span>
    <img src="uploads/<?= esc($foto_kk) ?>" alt="Foto KK">
  </div>
</div>

</body>
</html>
