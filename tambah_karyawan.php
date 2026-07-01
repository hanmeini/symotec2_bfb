<?php




require_once 'functions.php';   // berisi db_connect(), e(), log_activity()
$conn = db_connect();

// Pastikan folder upload ada
if (!is_dir("uploads")) {
    mkdir("uploads", 0777, true);
}

// =========================
//   Fungsi Upload File
// =========================
function uploadFile($field){
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === 4){
        return "";
    }

    $allowed = ['jpg','jpeg','png','pdf'];
    $name = $_FILES[$field]['name'];
    $tmp  = $_FILES[$field]['tmp_name'];

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)){
        return "";
    }

    $newName = $field."_".md5(uniqid()).".".$ext;
    move_uploaded_file($tmp, "uploads/".$newName);

    return $newName;
}

// =========================
//   Simpan Data Karyawan
// =========================
if (isset($_POST['simpan'])) {

    $nama   = $_POST['nama'];
    $LP     = $_POST['LP'];
    $dept   = $_POST['dept'];
    $jabatan= $_POST['jabatan'];
    $tgl_masuk = $_POST['tgl_masuk'];
    $tgl_lahir = $_POST['tgl_lahir'];
    $alamat = $_POST['alamat'];
    $nik    = $_POST['nik'];
    $kk     = $_POST['kk'];
    $status_menikah = $_POST['status_menikah'];
    $jumlah_tanggungan = $_POST['jumlah_tanggungan'];
    $no_telp = $_POST['no_telp'];
    $pendidikan = $_POST['pendidikan'];
    $nama_darurat = $_POST['nama_darurat'];
    $no_darurat = $_POST['no_darurat'];
    $bpjs_kes = $_POST['bpjs_kes'];
    $bpjs_tk  = $_POST['bpjs_tk'];
    $gaji_pokok = $_POST['gaji_pokok'];
    $jenis_gaji = $_POST['jenis_gaji'];
    $upah_lembur = $_POST['upah_lembur'];

    // Upload file
    $foto      = uploadFile("foto");
    $foto_ktp  = uploadFile("foto_ktp");
    $foto_kk   = uploadFile("foto_kk");

    $sql = "INSERT INTO data_karyawan
    (nama, LP, dept, jabatan, jenis_gaji, tgl_masuk, tgl_lahir, alamat, foto,
     nik, foto_ktp, kk, foto_kk, status_menikah, jumlah_tanggungan,
     no_telp, pendidikan, nama_darurat, no_darurat,
     bpjs_kes, bpjs_tk, gaji_pokok, upah_lembur, aktive)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'aktive')";

    $stmt = $conn->prepare($sql);

    // Perbaikan bind_param (harus sesuai tipe data)
    $stmt->bind_param(
        "ssissssssssssisisssssdd",
        $nama, $LP, $dept, $jabatan, $jenis_gaji,
        $tgl_masuk, $tgl_lahir, $alamat, $foto,
        $nik, $foto_ktp, $kk, $foto_kk, $status_menikah, $jumlah_tanggungan,
        $no_telp, $pendidikan, $nama_darurat, $no_darurat,
        $bpjs_kes, $bpjs_tk, $gaji_pokok, $upah_lembur
    );

    $stmt->execute();
    $stmt->close();

    log_activity("tambah", ($_SESSION['user'] ?? 'system'), "Tambah karyawan $nama", $conn);

    header("Location: karyawan.php");
    exit;
}

// Ambil daftar departemen
$depList = $conn->query("SELECT id, nama_bagian FROM bagian ORDER BY nama_bagian ASC");

// Ambil daftar jabatan
$jabList = $conn->query("SELECT idj, jabatan FROM jabatan ORDER BY jabatan ASC");

?>

<!DOCTYPE html>
<html>
<head>
<title>Tambah Karyawan</title>
<meta charset="utf-8">

<style>
body{font-family:Segoe UI;background:#eef2f7;padding:20px}
.container{max-width:750px;margin:auto;background:white;padding:20px;border-radius:12px}
input,select,textarea{width:100%;padding:10px;margin-bottom:12px;border-radius:6px;border:1px solid #ccc}
.btn{padding:10px 15px;border-radius:6px;color:#fff;text-decoration:none;border:none;cursor:pointer}
.btn-save{background:#28a745}
.btn-back{background:#007bff}
img.preview{margin-top:6px;border-radius:5px;border:1px solid #ccc}
.label{font-weight:bold;margin-bottom:4px;display:block}
</style>

<script>
// === Preview Foto ===
function previewImg(input,id){
    const file = input.files[0];
    if(!file) return;
    const reader = new FileReader();
    reader.onload = function(e){
        document.getElementById(id).src = e.target.result;
    }
    reader.readAsDataURL(file);
}

// === Validasi angka ===
function onlyNumber(evt){
    const ch = String.fromCharCode(evt.which);
    if(!/[0-9]/.test(ch)){ evt.preventDefault(); }
}

// === Cek NIK via AJAX ===
function cekNIK(){
    var nik = document.getElementById("nik").value;
    if(nik.length < 16) return;
    var xhr = new XMLHttpRequest();
    xhr.open("POST","ajax_check_nik.php",true);
    xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    xhr.onload = function(){
        if(xhr.responseText == "exist"){
            alert("NIK sudah terpakai!");
            document.getElementById("nik").value = "";
        }
    };
    xhr.send("nik="+nik);
}
</script>

</head>
<body>

<div class="container">
<h2 style="text-align:center">Tambah Karyawan</h2>

<form method="POST" enctype="multipart/form-data">

<label class="label">Nama</label>
<input type="text" name="nama" required>

<label class="label">Jenis Kelamin</label>
<select name="LP">
    <option value="L">Laki-laki (L)</option>
    <option value="P">Perempuan (P)</option>
</select>

<label class="label">Departemen</label>
<select name="dept" required>
    <option value="">-- Pilih Departemen --</option>
    <?php while($d = $depList->fetch_assoc()): ?>
        <option value="<?= $d['id'] ?>"><?= e($d['nama_bagian']) ?></option>
    <?php endwhile; ?>
</select>

<label class="label">Jabatan</label>
<select name="jabatan" required>
    <option value="">-- Pilih Jabatan --</option>
    <?php while($j = $jabList->fetch_assoc()): ?>
        <option value="<?= $j['idj'] ?>"><?= e($j['jabatan']) ?></option>
    <?php endwhile; ?>
</select>

<label class="label">Tanggal Masuk</label>
<input type="date" name="tgl_masuk" required>

<label class="label">Tanggal Lahir</label>
<input type="date" name="tgl_lahir" required>

<label class="label">Alamat</label>
<textarea name="alamat"></textarea>

<label class="label">NIK/NPWP(16)</label>
<input type="text" id="nik" name="nik" maxlength="16" onkeypress="onlyNumber(event)" onkeyup="cekNIK()">

<label class="label">KK</label>
<input type="text" name="kk" maxlength="16" onkeypress="onlyNumber(event)">

<label class="label">Status Menikah</label>
<select name="status_menikah">
    <option value="Belum Menikah">Belum Menikah</option>
    <option value="Menikah">Menikah</option>
</select>

<label class="label">Jumlah Tanggungan</label>
<input type="number" name="jumlah_tanggungan">

<label class="label">No Telepon</label>
<input type="text" name="no_telp" onkeypress="onlyNumber(event)">

<label class="label">Pendidikan</label>
<input type="text" name="pendidikan">

<label class="label">Nama Darurat</label>
<input type="text" name="nama_darurat">

<label class="label">No Darurat</label>
<input type="text" name="no_darurat" onkeypress="onlyNumber(event)">

<label class="label">BPJS Kesehatan</label>
<input type="text" name="bpjs_kes">

<label class="label">BPJS Ketenagakerjaan</label>
<input type="text" name="bpjs_tk">

<label class="label">Jenis Gaji</label>
<select name="jenis_gaji" required>
    <option value="bulanan">Bulanan</option>
    <option value="mingguan">Mingguan</option>
</select>

<label class="label">Gaji Pokok</label>
<input type="number" step="0.01" name="gaji_pokok">

<label class="label">Upah Lembur</label>
<input type="number" step="0.01" name="upah_lembur">

<label class="label">Foto Karyawan</label>
<input type="file" name="foto" onchange="previewImg(this,'prev_foto')">
<img src="" id="prev_foto" width="120" class="preview">

<label class="label">Foto KTP</label>
<input type="file" name="foto_ktp" onchange="previewImg(this,'prev_ktp')">
<img src="" id="prev_ktp" width="120" class="preview">

<label class="label">Foto KK</label>
<input type="file" name="foto_kk" onchange="previewImg(this,'prev_kk')">
<img src="" id="prev_kk" width="120" class="preview">

<button type="submit" name="simpan" class="btn btn-save">Simpan</button>
<a href="karyawan.php" class="btn btn-back">Kembali</a>

</form>
</div>

</body>
</html>
