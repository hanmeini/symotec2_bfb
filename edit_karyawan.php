<?php
require_once 'functions.php'; // db_connect(), e(), log_activity()
$conn = db_connect();

// pastikan id ada
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID tidak valid.");
}
$id = intval($_GET['id']);

// ambil daftar departemen & jabatan (untuk dropdown)
$depList = $conn->query("SELECT id, nama_bagian FROM bagian ORDER BY nama_bagian ASC");
$jabList = $conn->query("SELECT idj, jabatan FROM jabatan ORDER BY jabatan ASC");

// ambil data karyawan
$sql = "SELECT no_staff, nama, LP, dept, jabatan, jenis_gaji,
               tgl_masuk, tgl_lahir, alamat,
               foto, nik, foto_ktp, kk, foto_kk, status_menikah, jumlah_tanggungan,
               no_telp, pendidikan, nama_darurat, no_darurat, bpjs_kes, bpjs_tk,
               gaji_pokok, upah_lembur, aktive
        FROM data_karyawan WHERE no_staff = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    die("Data tidak ditemukan.");
}

// bind result variables
$stmt->bind_result(
    $no_staff, $nama, $LP, $dept_id, $jab_id, $jenis_gaji,
    $tgl_masuk, $tgl_lahir, $alamat,
    $foto_old, $nik_old, $foto_ktp_old, $kk_old, $foto_kk_old, $status_menikah,
    $jumlah_tanggungan, $no_telp, $pendidikan, $nama_darurat, $no_darurat,
    $bpjs_kes, $bpjs_tk, $gaji_pokok, $upah_lembur, $aktive
);
$stmt->fetch();
$stmt->close();

// fungsi upload (opsional)
function uploadFileOptional($fieldname, $oldFile = '') {
    if (!isset($_FILES[$fieldname]) || $_FILES[$fieldname]['error'] == 4) {
        return $oldFile;
    }
    $allowed = ['jpg','jpeg','png'];
    $name = $_FILES[$fieldname]['name'];
    $tmp = $_FILES[$fieldname]['tmp_name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return $oldFile;
    }
    $newName = $fieldname . "_" . md5(uniqid()) . "." . $ext;
    if (move_uploaded_file($tmp, "uploads/".$newName)) {
        return $newName;
    }
    return $oldFile;
}

// proses update
$errors = [];
if (isset($_POST['update'])) {
    // ambil input
    $nama_in = trim($_POST['nama']);
    $LP_in = $_POST['LP'];
    $dept_in = $_POST['dept'];
    $jabatan_in = $_POST['jabatan'];
    $tgl_masuk_in = $_POST['tgl_masuk'] ?: null;
    $tgl_lahir_in = $_POST['tgl_lahir'] ?: null;
    $alamat_in = trim($_POST['alamat']);
    $nik_in = preg_replace('/\D/','',$_POST['nik']);
    $kk_in = preg_replace('/\D/','',$_POST['kk']);
    $status_menikah_in = $_POST['status_menikah'];
    $jumlah_tanggungan_in = intval($_POST['jumlah_tanggungan'] ?: 0);
    $no_telp_in = preg_replace('/\D/','',$_POST['no_telp']);
    $pendidikan_in = trim($_POST['pendidikan']);
    $nama_darurat_in = trim($_POST['nama_darurat']);
    $no_darurat_in = preg_replace('/\D/','',$_POST['no_darurat']);
    $bpjs_kes_in = preg_replace('/\D/','',$_POST['bpjs_kes']);
    $bpjs_tk_in = preg_replace('/\D/','',$_POST['bpjs_tk']);
    $gaji_pokok_in = $_POST['gaji_pokok'] ?: 0;
    $upah_lembur_in = $_POST['upah_lembur'] ?: 0;
    $jenis_gaji_in = $_POST['jenis_gaji'];


    // validasi server-side
    if ($nik_in !== '' && strlen($nik_in) != 16) $errors[] = "NIK harus 16 digit.";
    if ($kk_in !== '' && strlen($kk_in) != 16) $errors[] = "KK harus 16 digit.";
    if ($bpjs_kes_in !== '' && strlen($bpjs_kes_in) > 13) $errors[] = "BPJS Kesehatan maksimal 13 digit.";
    if ($bpjs_tk_in !== '' && strlen($bpjs_tk_in) > 13) $errors[] = "BPJS Ketenagakerjaan maksimal 13 digit.";

    // cek duplikat NIK
    if ($nik_in !== '') {
        $chk = $conn->prepare("SELECT COUNT(*) FROM data_karyawan WHERE nik = ? AND no_staff <> ?");
        $chk->bind_param("si", $nik_in, $id);
        $chk->execute();
        $chk->store_result();
        $chk->bind_result($cntDup);
        $chk->fetch();
        $chk->close();
        if ($cntDup > 0) $errors[] = "NIK sudah terdaftar pada karyawan lain.";
    }

    if (empty($errors)) {
        // upload file jika ada
        $foto_new = uploadFileOptional('foto', $foto_old);
        $foto_ktp_new = uploadFileOptional('foto_ktp', $foto_ktp_old);
        $foto_kk_new = uploadFileOptional('foto_kk', $foto_kk_old);

        // update query
        $update_sql = "UPDATE data_karyawan SET
            nama=?, LP=?, dept=?, jabatan=?, jenis_gaji=?,
            tgl_masuk=?, tgl_lahir=?, alamat=?, foto=?,
            nik=?, foto_ktp=?, kk=?, foto_kk=?, status_menikah=?, jumlah_tanggungan=?,
            no_telp=?, pendidikan=?, nama_darurat=?, no_darurat=?, bpjs_kes=?, bpjs_tk=?,
            gaji_pokok=?, upah_lembur=? WHERE no_staff = ?";

        $stmt_up = $conn->prepare($update_sql);
        $stmt_up->bind_param(
            "ssissssssssssisssssddii",
            $nama_in, $LP_in, $dept_in, $jabatan_in, $jenis_gaji_in,
            $tgl_masuk_in, $tgl_lahir_in, $alamat_in, $foto_new,
            $nik_in, $foto_ktp_new, $kk_in, $foto_kk_new, $status_menikah_in, $jumlah_tanggungan_in,
            $no_telp_in, $pendidikan_in, $nama_darurat_in, $no_darurat_in,
            $bpjs_kes_in, $bpjs_tk_in, $gaji_pokok_in, $upah_lembur_in, $id
        );
        $stmt_up->execute();
        $stmt_up->close();

        log_activity('edit', ($_SESSION['user'] ?? 'system'), "Edit karyawan id=$id nama={$nama_in}", $conn);

        header("Location: karyawan.php");
        exit;
    }
}

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Edit Karyawan</title>
<style>
body{font-family:Segoe UI, sans-serif;background:#eef2f7;padding:20px}
.container{max-width:800px;margin:auto;background:#fff;padding:20px;border-radius:8px}
input,select,textarea{width:100%;padding:10px;margin-bottom:10px;border-radius:6px;border:1px solid #ccc}
label{font-weight:bold;display:block;margin-bottom:6px}
.btn{padding:10px 14px;border-radius:6px;color:#fff;text-decoration:none;border:none;cursor:pointer}
.btn-save{background:#28a745}
.btn-back{background:#6c757d}
.preview{width:140px;border-radius:6px;border:1px solid #ddd;margin-top:6px}
.error{color:#b71c1c;margin-bottom:12px}
.small{font-size:13px;color:#555}
</style>

<script>
function previewFile(inputId, imgId){
    var input = document.getElementById(inputId);
    var img = document.getElementById(imgId);
    if(input.files && input.files[0]){
        var reader = new FileReader();
        reader.onload = function(e){ img.src = e.target.result; img.style.display='block'; }
        reader.readAsDataURL(input.files[0]);
    }
}
function onlyDigits(el){
    el.value = el.value.replace(/\D/g,'');
}
function checkNIKEdit(){
    var nik = document.getElementById('nik').value.replace(/\D/g,'');
    if(nik.length < 16) return;
    var xhr = new XMLHttpRequest();
    xhr.open("POST","ajax_check_nik.php",true);
    xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    xhr.onload = function(){
        if(xhr.status==200){
            try {
                var res = JSON.parse(xhr.responseText);
                if(res.exists && res.id && parseInt(res.id) !== <?= $id ?>){
                    alert('NIK sudah dipakai karyawan lain.');
                    document.getElementById('nik').value = '';
                }
            } catch(e){}
        }
    };
    xhr.send("nik="+encodeURIComponent(nik));
}
</script>
</head>
<body>
<div class="container">
    <h2 style="text-align:center">Edit Karyawan</h2>

    <?php if (!empty($errors)): ?>
        <div class="error"><?= implode('<br>', array_map('esc', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label>Nama</label>
        <input type="text" name="nama" value="<?= esc($nama) ?>" required>

        <label>Jenis Kelamin (LP)</label>
        <select name="LP">
            <option value="L" <?= ($LP==='L')?'selected':'' ?>>Laki-laki</option>
            <option value="P" <?= ($LP==='P')?'selected':'' ?>>Perempuan</option>
        </select>

        <label>Departemen</label>
        <select name="dept" required>
            <option value="">-- Pilih Departemen --</option>
            <?php
            if ($depList) {
                while ($d = $depList->fetch_assoc()) {
                    $sel = ($dept_id == $d['id']) ? 'selected' : '';
                    echo '<option value="'.esc($d['id']).'" '.$sel.'>'.esc($d['nama_bagian']).'</option>';
                }
            }
            ?>
        </select>

        <label>Jabatan</label>
        <select name="jabatan" required>
            <option value="">-- Pilih Jabatan --</option>
            <?php
            if ($jabList) {
                while ($j = $jabList->fetch_assoc()) {
                    $selj = ($jab_id == $j['idj']) ? 'selected' : '';
                    echo '<option value="'.esc($j['idj']).'" '.$selj.'>'.esc($j['jabatan']).'</option>';
                }
            }
            ?>
        </select>

        <label>Tanggal Masuk</label>
        <input type="date" name="tgl_masuk" value="<?= esc($tgl_masuk) ?>">

        <label>Tanggal Lahir</label>
        <input type="date" name="tgl_lahir" value="<?= esc($tgl_lahir) ?>">

        <label>Alamat</label>
        <textarea name="alamat"><?= esc($alamat) ?></textarea>

        <label>NIK</label>
        <input type="text" id="nik" name="nik" maxlength="16" value="<?= esc($nik_old) ?>" oninput="onlyDigits(this)" onblur="checkNIKEdit()">

        <label>KK</label>
        <input type="text" name="kk" maxlength="16" value="<?= esc($kk_old) ?>" oninput="onlyDigits(this)">

        <label>Status Menikah</label>
        <select name="status_menikah">
            <option value="Belum Menikah" <?= ($status_menikah=='Belum Menikah')?'selected':'' ?>>Belum Menikah</option>
            <option value="Menikah" <?= ($status_menikah=='Menikah')?'selected':'' ?>>Menikah</option>
        </select>

        <label>Jumlah Tanggungan</label>
        <input type="number" name="jumlah_tanggungan" value="<?= esc($jumlah_tanggungan) ?>">

        <label>No Telepon</label>
        <input type="text" name="no_telp" value="<?= esc($no_telp) ?>" oninput="onlyDigits(this)">

        <label>Pendidikan</label>
        <input type="text" name="pendidikan" value="<?= esc($pendidikan) ?>">

        <label>Nama Kontak Darurat</label>
        <input type="text" name="nama_darurat" value="<?= esc($nama_darurat) ?>">

        <label>No Kontak Darurat</label>
        <input type="text" name="no_darurat" value="<?= esc($no_darurat) ?>" oninput="onlyDigits(this)">

        <label>BPJS Kesehatan</label>
        <input type="text" name="bpjs_kes" value="<?= esc($bpjs_kes) ?>" oninput="onlyDigits(this)">

        <label>BPJS Ketenagakerjaan</label>
        <input type="text" name="bpjs_tk" value="<?= esc($bpjs_tk) ?>" oninput="onlyDigits(this)">
        
        <label class="label">Jenis Gaji</label>
        <select name="jenis_gaji" required>
            <option value="bulanan" <?= ($jenis_gaji=='bulanan')?'selected':'' ?>>Bulanan</option>
            <option value="mingguan" <?= ($jenis_gaji=='mingguan')?'selected':'' ?>>Mingguan</option>
        </select>

        <label>Gaji Pokok</label>
        <input type="number" step="0.01" name="gaji_pokok" value="<?= esc($gaji_pokok) ?>">

        <label>Upah Lembur</label>
        <input type="number" step="0.01" name="upah_lembur" value="<?= esc($upah_lembur) ?>">

        <!-- Foto preview + upload -->
        <label>Foto Profil</label>
        <?php if ($foto_old): ?>
            <img src="uploads/<?= esc($foto_old) ?>" id="preview_foto_existing" class="preview">
        <?php endif; ?>
        <input type="file" id="foto" name="foto" onchange="previewFile('foto','preview_foto')">
        <img id="preview_foto" class="preview" style="display:none">

        <label>Foto KTP</label>
        <?php if ($foto_ktp_old): ?>
            <img src="uploads/<?= esc($foto_ktp_old) ?>" id="preview_ktp_existing" class="preview">
        <?php endif; ?>
        <input type="file" id="foto_ktp" name="foto_ktp" onchange="previewFile('foto_ktp','preview_ktp')">
        <img id="preview_ktp" class="preview" style="display:none">

        <label>Foto KK</label>
        <?php if ($foto_kk_old): ?>
            <img src="uploads/<?= esc($foto_kk_old) ?>" id="preview_kk_existing" class="preview">
        <?php endif; ?>
        <input type="file" id="foto_kk" name="foto_kk" onchange="previewFile('foto_kk','preview_kk')">
        <img id="preview_kk" class="preview" style="display:none">

        <br><br>
        <button type="submit" name="update" class="btn btn-save">Simpan Perubahan</button>
        <a href="karyawan.php" class="btn btn-back" style="margin-left:8px">Batal</a>
    </form>
</div>

</body>
</html>
