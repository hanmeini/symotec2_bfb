<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';







$id = trim($_GET['J'] ?? '');

if(!$id){
    die("ID tidak valid");
}

/* ================= DATA ================= */

$sql = "
SELECT
    p.sup,
    p.harga_m,
    p.ppn_m,
    p.sisa,
    p.coa,
    c.account_name,
    p.inv,
    p.pph15m,
    p.pph22m,
    p.pph23m

FROM pembelianho1 p

LEFT JOIN coa c
    ON p.coa = c.account_code

WHERE p.j = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s",$id);

$stmt->execute();

$row = $stmt->get_result()->fetch_assoc();

$stmt->close();

if(!$row){
    die("Data tidak ditemukan");
}

/* ================= MAPPING ================= */

$sup = $row['sup'];

$dpp = (float)$row['harga_m'];

$ppn = (float)$row['ppn_m'];

$sisa_old = (float)$row['sisa'];

$coa_hutang = $row['coa'];

$nama_akun = $row['account_name'];

$inv = $row['inv'];

$pph15_old = (float)$row['pph15m'];

$pph22_old = (float)$row['pph22m'];

$pph23_old = (float)$row['pph23m'];

$pph_aktif =
    $pph15_old > 0 ? 15 :
    ($pph22_old > 0 ? 22 :
    ($pph23_old > 0 ? 23 : 0));

/* ================= TITIPAN ================= */

$stmtTitipan = $conn->prepare("
SELECT
    id,
    nominal,
    description

FROM titipanap

WHERE
(
    inv IS NULL
    OR inv = ''
)

AND nominal > 0

AND sup = ?
");

$stmtTitipan->bind_param("s",$sup);

$stmtTitipan->execute();

$titipan = $stmtTitipan->get_result();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Titipan + PPH</title>

<style>

*{
    box-sizing:border-box;
}

body{
    font-family:'Segoe UI',Arial,sans-serif;
    background:linear-gradient(135deg,#eef2f7,#d9e4f5);
    margin:0;
    padding:20px;
}

.container{
    max-width:950px;
    margin:auto;
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
}

h2,h3{
    margin-top:0;
    color:#333;
    border-bottom:2px solid #eee;
    padding-bottom:10px;
}

p{
    margin:6px 0;
    color:#444;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:15px;
    font-size:14px;
}

th{
    background:#4a69bd;
    color:#fff;
    font-weight:600;
}

th,td{
    border:1px solid #e0e0e0;
    padding:10px;
    text-align:center;
}

tr:nth-child(even){
    background:#fafafa;
}

input,
select{
    width:100%;
    padding:8px;
    border:1px solid #ccc;
    border-radius:6px;
    transition:0.2s;
}

input:focus,
select:focus{
    border-color:#4a69bd;
    outline:none;
    box-shadow:0 0 4px rgba(74,105,189,0.3);
}

.box{
    background:#f1f5fb;
    padding:12px;
    margin:8px 0;
    border-radius:8px;
    font-weight:600;
    display:flex;
    justify-content:space-between;
}

.hidden{
    display:none;
}

button{
    background:#4a69bd;
    color:#fff;
    border:none;
    padding:10px 20px;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
    transition:0.2s;
}

button:hover{
    background:#3b55a0;
}

label{
    cursor:pointer;
    font-size:13px;
}

input[type="radio"],
input[type="checkbox"]{
    width:auto;
    margin-right:5px;
}

.info-box{
    background:#f7f9fc;
    padding:15px;
    border-radius:10px;
    margin-bottom:15px;
}

.row{
    display:flex;
    justify-content:space-between;
    padding:6px 0;
    border-bottom:1px dashed #ddd;
}

.row:last-child{
    border-bottom:none;
}

.label{
    font-weight:600;
    color:#555;
}

.value{
    font-weight:500;
    text-align:right;
}

.total{
    font-size:16px;
    font-weight:bold;
    color:#2c3e50;
}

.sisa{
    font-size:15px;
    font-weight:bold;
    color:#c0392b;
}

@media(max-width:600px){

    .container{
        padding:15px;
    }

    table{
        font-size:12px;
    }
}

</style>

</head>

<body>

<div class="container">

<h3>Pembayaran TITIPAN + PPH</h3>

<div class="info-box">

<div class="row">
<div class="label">Supplier</div>
<div class="value"><?= htmlspecialchars($sup) ?></div>
</div>

<div class="row">
<div class="label">No Invoice</div>
<div class="value"><?= htmlspecialchars($inv) ?></div>
</div>

<div class="row">
<div class="label">DPP</div>
<div class="value"><?= number_format($dpp,2) ?></div>
</div>

<div class="row">
<div class="label">PPN</div>
<div class="value"><?= number_format($ppn,2) ?></div>
</div>

<div class="row total">
<div class="label">Total Tagihan</div>
<div class="value"><?= number_format($dpp+$ppn,2) ?></div>
</div>

<div class="row">
<div class="label">PPH Sebelumnya</div>
<div class="value">
15 : <?= number_format($pph15_old,2) ?> |
22 : <?= number_format($pph22_old,2) ?> |
23 : <?= number_format($pph23_old,2) ?>
</div>
</div>

<div class="row sisa">
<div class="label">Sisa Lama</div>
<div class="value"><?= number_format($sisa_old,2) ?></div>
</div>

</div>

<hr>

<form method="post" action="savein_apt.php" id="formAPT">

<input type="hidden" name="idbeli" value="<?= $id ?>">

<input type="hidden" name="inv" value="<?= htmlspecialchars($inv) ?>">

<input type="hidden" id="dpp" value="<?= $dpp ?>">

<input type="hidden" id="sisa_old" value="<?= $sisa_old ?>">

<input type="hidden" id="pph15" value="<?= $pph15_old ?>">

<input type="hidden" id="pph22" value="<?= $pph22_old ?>">

<input type="hidden" id="pph23" value="<?= $pph23_old ?>">

<label>Tanggal</label>

<input
type="date"
name="tanggal"
value="<?= date('Y-m-d') ?>"
required>

<br><br>

<label>Pilih Titipan</label>

<select id="titipan_id" name="titipan_id">

<option value="">
-- pilih --
</option>

<?php while($t = $titipan->fetch_assoc()): ?>

<option
value="<?= $t['id'] ?>"
data-nom="<?= $t['sisa'] ?>">

<?= htmlspecialchars($t['keterangan']) ?>
|
<?= number_format($t['sisa'],2) ?>

</option>

<?php endwhile; ?>

</select>

<table>

<thead>

<tr>
<th>No</th>
<th>COA</th>
<th>Nama</th>
<th>Debet</th>
<th>Kredit</th>
<th>Aksi</th>
</tr>

</thead>

<tbody>

<tr>

<td>1</td>

<td>
11.04.01.001

<input
type="hidden"
name="jurnal[0][coa]"
value="11.04.01.001">
</td>

<td>UANG MUKA</td>

<td>0</td>

<td>

<input type="number" step="0.01" min="0" max="<?= $sisa_old ?>" id="titipan_pakai">

<input
type="hidden"
name="jurnal[0][kredit]"
id="titipan_jurnal">

</td>

<td>-</td>

</tr>

<tr>

<td>2</td>

<td>
<?= htmlspecialchars($coa_hutang ?? '') ?>

<input
type="hidden"
name="jurnal[1][coa]"
value="<?= htmlspecialchars($coa_hutang ?? '') ?>">
</td>

<td><?= htmlspecialchars($nama_akun ?? '') ?></td>

<td>

<span id="hutang_text">0</span>

<input
type="hidden"
name="jurnal[1][debet]"
id="hutang">

</td>

<td>0</td>

<td>-</td>

</tr>

<tr>

<td colspan="6" style="text-align:left">

<label>

<input
type="checkbox"
name="use_pph"
id="toggle_pph"
<?= $pph_aktif ? 'checked' : '' ?>>

Gunakan PPH

</label>

</td>

</tr>

<tr
id="pph_row"
class="<?= $pph_aktif ? '' : 'hidden' ?>">

<td>3</td>

<td>

<span id="coa_pph_text">-</span>

<input
type="hidden"
name="jurnal[2][coa]"
id="coa_pph">

</td>

<td id="nama_pph">-</td>

<td>0</td>

<td>

<span id="pph_text">0</span>

<input
type="hidden"
name="jurnal[2][kredit]"
id="pph_val">

</td>

<td style="text-align:left">

<label>
<input
type="radio"
name="pph_type"
value="15"
<?= $pph_aktif==15 ? 'checked' : '' ?>>
15
</label>

<br>

<label>
<input
type="radio"
name="pph_type"
value="22"
<?= $pph_aktif==22 ? 'checked' : '' ?>>
22
</label>

<br>

<label>
<input
type="radio"
name="pph_type"
value="23"
<?= $pph_aktif==23 ? 'checked' : '' ?>>
23
</label>

<br>

<input
id="pph_percent"
placeholder="%">

</td>

</tr>

</tbody>

</table>

<div class="box">
<div>Sisa Hutang</div>
<div id="sisa_view">0</div>
</div>

<div class="box">
<div>Sisa Titipan</div>
<div id="titipan_sisa">0</div>
</div>

<input
type="hidden"
name="titipan_pakai"
id="titipan_hidden">

<br>

<button type="submit">
Submit
</button>

</form>

</div>

<script>

function n(v){

    return parseFloat(
        (v || '').toString().replace(/[^0-9.]/g,'')
    ) || 0;
}

function f(v){

    return Number(v).toLocaleString(
        'id-ID',
        {
            minimumFractionDigits:2,
            maximumFractionDigits:2
        }
    );
}

function getCoaPPH(type){

    if(type == 15){
        return {
            coa:'21.04.06.001',
            nama:'PPH 15'
        };
    }

    if(type == 22){
        return {
            coa:'21.04.05.001',
            nama:'PPH 22'
        };
    }

    if(type == 23){
        return {
            coa:'21.04.02.001',
            nama:'PPH 23'
        };
    }

    return {
        coa:'',
        nama:''
    };
}

function hitung(){

    let sisa_old = n(
        document.getElementById('sisa_old').value
    );

    let titipan = n(
        document.getElementById('titipan_pakai').value
    );

    let select =
        document.getElementById('titipan_id');

    let nominal = n(
        select.options[select.selectedIndex]
        ?.dataset.nom
    );

    let aktif =
        document.getElementById('toggle_pph')
        .checked;

    let type =
        document.querySelector(
            'input[name="pph_type"]:checked'
        )?.value;

    let pph = 0;

    let obj = {
        coa:'',
        nama:''
    };

    if(aktif && type){

        let persen = n(
            document.getElementById('pph_percent').value
        );

        let pph15 = n(
            document.getElementById('pph15').value
        );

        let pph22 = n(
            document.getElementById('pph22').value
        );

        let pph23 = n(
            document.getElementById('pph23').value
        );

        if(persen > 0){

            let dpp = n(
                document.getElementById('dpp').value
            );

            pph = (persen / 100) * dpp;

        }else{

            if(type == 15){
                pph = pph15;
            }

            if(type == 22){
                pph = pph22;
            }

            if(type == 23){
                pph = pph23;
            }
        }

        obj = getCoaPPH(type);

        document.getElementById('nama_pph')
        .innerText = obj.nama;

        document.getElementById('coa_pph')
        .value = obj.coa;

        document.getElementById('coa_pph_text')
        .innerText = obj.coa;

    }else{

        document.getElementById('nama_pph')
        .innerText = '-';

        document.getElementById('coa_pph')
        .value = '';

        document.getElementById('coa_pph_text')
        .innerText = '-';
    }

    if(titipan > nominal){

        titipan = nominal;

        document.getElementById('titipan_pakai')
        .value = nominal;
    }

    let hutang = titipan + pph;

    if(hutang > sisa_old){

        hutang = sisa_old;

        titipan = hutang - pph;

        if(titipan < 0){
            titipan = 0;
        }

        document.getElementById('titipan_pakai')
        .value = titipan;
    }

    let sisa = sisa_old - hutang;

    let sisaTitipan = nominal - titipan;

    document.getElementById('hutang_text')
    .innerText = f(hutang);

    document.getElementById('sisa_view')
    .innerText = f(sisa);

    document.getElementById('titipan_sisa')
    .innerText = f(sisaTitipan);

    document.getElementById('pph_text')
    .innerText = f(pph);

    document.getElementById('hutang')
    .value = hutang;

    document.getElementById('titipan_hidden')
    .value = titipan;

    document.getElementById('titipan_jurnal')
    .value = titipan;

    document.getElementById('pph_val')
    .value = pph;
}

/* ================= EVENT ================= */

document.getElementById('titipan_pakai')
.addEventListener('input', function(){

    this.value =
        this.value.replace(/[^0-9]/g,'');

    hitung();
});

document.getElementById('titipan_id')
.addEventListener('change', function(){

    let nominal = n(
        this.options[this.selectedIndex]
        ?.dataset.nom
    );

    document.getElementById('titipan_pakai')
    .value = nominal;

    hitung();
});

document.querySelectorAll(
'input[name="pph_type"]'
).forEach(r => {

    r.addEventListener('change', hitung);
});

document.getElementById('toggle_pph')
.addEventListener('change', function(){

    document.getElementById('pph_row')
    .classList.toggle(
        'hidden',
        !this.checked
    );

    hitung();
});

document.getElementById('pph_percent')
.addEventListener('input', hitung);

/* ================= VALIDASI ================= */

function beforeSubmit(){

    hitung();

    let hutang = n(
        document.getElementById('hutang').value
    );

    if(hutang <= 0){

        alert('Hutang kosong!');

        return false;
    }

    let sisa = n(
        document.getElementById('sisa_view').innerText
    );

    if(sisa < 0){

        alert('Pembayaran melebihi sisa hutang!');

        return false;
    }

    return confirm(
        'Simpan pembayaran titipan ?'
    );
}

/* ================= DISABLE DOUBLE SUBMIT ================= */

document.getElementById('formAPT')
.addEventListener('submit', function(e){

    if(!beforeSubmit()){

        e.preventDefault();

        return;
    }

    let btn =
        this.querySelector(
            'button[type="submit"]'
        );

    btn.disabled = true;

    btn.innerText = 'Menyimpan...';

    btn.style.opacity = '0.7';

    btn.style.cursor = 'not-allowed';
});

window.onload = hitung;

</script>

</body>
</html>

<?php
$conn->close();
?>