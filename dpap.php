<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';

/* CSRF */
if(empty($_SESSION['csrf'])){
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* KONEKSI */


/* SUPPLIER */
$sup = $conn->query("SELECT kode,nama FROM sup ORDER BY nama");

/* BANK */
$bank = $conn->query("
    SELECT account_code,account_name 
    FROM coa 
    WHERE layer=4 AND parent_account = '111'
");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Input Titipan pembelianho1</title>

<style>
body{font-family:Segoe UI;background:#eef2f7;}
.container{max-width:900px;margin:auto;background:#fff;padding:20px;border-radius:10px;}
table{width:100%;border-collapse:collapse;}
td,th{border:1px solid #ddd;padding:8px;}
th{background:#4a69bd;color:#fff;}
input,select,textarea{width:100%;padding:6px;}
.box{margin-top:10px;padding:10px;background:#f1f5fb;font-weight:bold;}
button{padding:10px 20px;background:#4a69bd;color:#fff;border:0;border-radius:5px;}
</style>
</head>

<body>

<div class="container">

<h3>Input Titipan pembelianho1</h3>

<form method="post" action="save_titipanap.php">

<input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">

<label>Tanggal</label>
<input type="date" name="tanggal" required>

<label>Supplier</label>
<select name="sup" required>
<option value="">-- pilih supplier --</option>
<?php while($s=$sup->fetch_assoc()): ?>
<option value="<?= $s['kode'] ?>">
<?= $s['kode'] ?> - <?= htmlspecialchars($s['nama']) ?>
</option>
<?php endwhile; ?>
</select>

<label>Keterangan</label>
<textarea name="keterangan" required></textarea>

<table>
<tr>
<th>No</th>
<th>COA</th>
<th>Nama</th>
<th>Debet</th>
<th>Kredit</th>
</tr>

<tr>
<td>1</td>
<td>
<select name="bank_coa" id="bank_coa" onchange="setNama(this)">
<option value="">Pilih KAS</option>
<?php while($r=$bank->fetch_assoc()): ?>
<option value="<?= $r['account_code'] ?>">
<?= $r['account_code'] ?> - <?= htmlspecialchars($r['account_name']) ?>
</option>
<?php endwhile; ?>
</select>
</td>
<td id="nama_bank">-</td>
<td>0</td>
<td><input class="bank" inputmode="numeric" placeholder="0"></td>
</tr>

<tr>
<td>2</td>
<td>11.04.01.001</td>
<td>UANG MUKA pembelianho1</td>
<td><span id="debet_text">0</span></td>
<td>0</td>
</tr>

</table>

<div class="box">Total: <span id="total_view">0</span></div>

<input type="hidden" name="total" id="total">
<input type="hidden" name="nominal" id="nominal">

<br>
<button type="submit">Simpan</button>

</form>

</div>

<script>

function n(v){
    return parseFloat((v||'').replace(/[^0-9.-]/g,'')) || 0;
}

function f(v){
    return v.toLocaleString('id-ID');
}

/* set nama bank */
function setNama(el){
    let text = el.options[el.selectedIndex].text;
    let nama = text.split(' - ')[1] || '-';
    document.getElementById('nama_bank').innerText = nama;
}

/* hitung */
function hitung(){
    let el = document.querySelector('.bank');
    let val = n(el.value);

    document.getElementById('debet_text').innerText = f(val);
    document.getElementById('total_view').innerText = f(val);

    document.getElementById('total').value = val;
    document.getElementById('nominal').value = val;
}

/* ================= INPUT (TANPA FORMAT) ================= */
document.addEventListener('input', function(e){
    if(e.target.matches('.bank')){

        // hanya angka
        let clean = e.target.value.replace(/[^0-9]/g,'');
        e.target.value = clean;

        hitung();
    }
});

/* ================= FORMAT SAAT BLUR ================= */
document.addEventListener('blur', function(e){
    if(e.target.matches('.bank')){

        let val = n(e.target.value);
        e.target.value = val ? f(val) : '';

    }
}, true);

/* ================= VALIDASI ================= */
document.querySelector('form').addEventListener('submit', function(e){

    let val = n(document.querySelector('.bank').value);
    let coa = document.getElementById('bank_coa').value;

    if(!coa){
        alert("Pilih bank!");
        e.preventDefault();
        return;
    }

    if(val <= 0){
        alert("Nominal harus > 0");
        e.preventDefault();
        return;
    }

    if(val > 100000000000){
        alert("Nominal terlalu besar!");
        e.preventDefault();
        return;
    }

    if(!confirm("Yakin simpan?")){
        e.preventDefault();
    }
});

/* ================= LOAD ================= */
window.onload = function(){
    hitung();
};

</script>

</body>
</html>