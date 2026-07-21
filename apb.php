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





/* =========================================================
   FUNCTION AMBIL NAMA AKUN
========================================================= */

function getNamaAkun($conn,$coa){

    $nama='';

    $stmt=$conn->prepare("
    SELECT account_name
    FROM coa
    WHERE account_code=?
    LIMIT 1
    ");

    $stmt->bind_param("s",$coa);
    $stmt->execute();

    $res=$stmt->get_result();

    if($r=$res->fetch_assoc()){
        $nama=$r['account_name'];
    }

    $stmt->close();

    return $nama;
}

/* =========================================================
   SIMPAN
========================================================= */

if($_SERVER['REQUEST_METHOD']=='POST'){

    $conn->begin_transaction();

    try{

        $id_transaksi = (int)($_POST['id_transaksi'] ?? 0);

        $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));

        $coa_bank = trim($_POST['coa_bank'] ?? '');

        $coa_hutang = trim($_POST['coa_hutang'] ?? '');

        $bayar = (float)($_POST['bayar'] ?? 0);

        $pph = (float)($_POST['pph'] ?? 0);

        $jenispph = trim($_POST['jenispph'] ?? '');

        $coa_pph = trim($_POST['coa_pph'] ?? '');

        $biaya_bank = (float)($_POST['biaya_bank'] ?? 0);

        if($id_transaksi<=0){
            throw new Exception("ID transaksi tidak valid");
        }

        if($coa_bank==''){
            throw new Exception("COA bank wajib dipilih");
        }

        if($bayar<=0){
            throw new Exception("Nilai pembayaran harus lebih besar dari nol");
        }

        /* =========================================================
           AMBIL pembelianho1 ASLI
        ========================================================= */

        $stmt = $conn->prepare("
        SELECT
            p.*,
            s.nama AS supplier
        FROM pembelianho1 p
        LEFT JOIN sup s
        ON p.sup=s.kode
        WHERE p.id_transaksi=?
        LIMIT 1
        ");

        $stmt->bind_param("i",$id_transaksi);

        $stmt->execute();

        $result = $stmt->get_result();

        $data = $result->fetch_assoc();

        $stmt->close();

        if(!$data){
            throw new Exception("Data pembelianho1 tidak ditemukan");
        }

        $jurnal_pembelian = $data['j'];

        $supplier = $data['supplier'] ?? '';

        $sup = $data['sup'] ?? '';

        $invoice = $data['inv'] ?? '';

        $sj = $data['sj'] ?? '';

        $tagihan = (float)$data['hargat_m'];

        /* =========================================================
           TOTAL SUDAH DIBAYAR
        ========================================================= */

        $stmtTotal = $conn->prepare("
        SELECT
            COALESCE(SUM(bayar),0) AS total_bayar,
            COALESCE(SUM(pph),0) AS total_pph
        FROM pembelianho1
        WHERE j=?
        ");

        $stmtTotal->bind_param("s",$jurnal_pembelian);

        $stmtTotal->execute();

        $resTotal = $stmtTotal->get_result();

        $dTotal = $resTotal->fetch_assoc();

        $stmtTotal->close();

        $bayar_lama = (float)$dTotal['total_bayar'];

        $pph_lama = (float)$dTotal['total_pph'];

        $total_bayar_baru = $bayar_lama + $bayar;

        $total_pph_baru = $pph_lama + $pph;

        $sisa_baru = $tagihan - $total_bayar_baru - $total_pph_baru;

        if($sisa_baru < 0){
            throw new Exception("Pembayaran melebihi sisa tagihan");
        }

        require_once 'generate_nomor_ap.php';
        $kodeCOS = generateNomorAP($conn, 'BOS', $tanggal);

        $stmtCos = $conn->prepare("
        INSERT INTO bos(bos)
        VALUES(?)
        ");

        $stmtCos->bind_param("s",$kodeCOS);

        $stmtCos->execute();

        $stmtCos->close();

        /* =========================================================
           NAMA AKUN
        ========================================================= */

        $nama_bank = getNamaAkun($conn,$coa_bank);

        $nama_hutang = getNamaAkun($conn,$coa_hutang);

        $nama_pph = '';

        if($coa_pph!=''){
            $nama_pph = getNamaAkun($conn,$coa_pph);
        }

        /* =========================================================
           INSERT JURNAL
        ========================================================= */

        $stmtJurnal = $conn->prepare("
            INSERT INTO jurnal
            (
                journal_number,
                jurnal_sementara,
                tanggal,
                keterangan,
                coa,
                account_name,
                debet,
                kredit,
                kode_booking,
                supcust
            )
            VALUES
            (?,?,?,?,?,?,?,?,?,?)
        ");

        $keterangan = "Pembayaran AP BANK ".$invoice;

        /* =========================
           DEBET HUTANG
        ========================= */

        $debet_hutang = $bayar + $pph;

        $kredit_hutang = 0;

        $stmtJurnal->bind_param(
            "ssssssddss",
            $kodeCOS,
            $kodeCOS,
            $tanggal,
            $keterangan,
            $coa_hutang,
            $nama_hutang,
            $debet_hutang,
            $kredit_hutang,
            $jurnal_pembelian,
            $sup
        );

        $stmtJurnal->execute();

        /* =========================
           KREDIT BANK
        ========================= */

        $debet_bank = 0;

        $kredit_bank = $bayar;

        $stmtJurnal->bind_param(
            "ssssssddss",
            $kodeCOS,
            $kodeCOS,
            $tanggal,
            $keterangan,
            $coa_bank,
            $nama_bank,
            $debet_bank,
            $kredit_bank,
            $jurnal_pembelian,
            $sup
        );

        $stmtJurnal->execute();

        /* =========================
           KREDIT PPH
        ========================= */

        if($pph > 0 && $coa_pph!=''){

            $debet_pph = 0;

            $kredit_pph = $pph;

            $stmtJurnal->bind_param(
                "ssssssddss",
            $kodeCOS,
            $kodeCOS,
                $tanggal,
                $keterangan,
                $coa_pph,
                $nama_pph,
                $debet_pph,
                $kredit_pph,
                $jurnal_pembelian,
                $sup
            );

            $stmtJurnal->execute();
        }

   /* =========================
   BIAYA ADMIN BANK
========================= */

if($biaya_bank > 0){

    $coa_beban_bank = '71.01.02.001';

    $nama_beban_bank = getNamaAkun(
        $conn,
        $coa_beban_bank
    );

    /* DEBET BEBAN */

    $debet_beban_bank = $biaya_bank;
    $kredit_beban_bank = 0;

    $stmtJurnal->bind_param(
        "ssssssddss",
            $kodeCOS,
            $kodeCOS,
        $tanggal,
        $keterangan,
        $coa_beban_bank,
        $nama_beban_bank,
        $debet_beban_bank,
        $kredit_beban_bank,
        $jurnal_pembelian,
        $sup
    );

    $stmtJurnal->execute();

    /* KREDIT BANK */

    $debet_bank_admin = 0;
    $kredit_bank_admin = $biaya_bank;

    $stmtJurnal->bind_param(
        "ssssssddss",
            $kodeCOS,
            $kodeCOS,
        $tanggal,
        $keterangan,
        $coa_bank,
        $nama_bank,
        $debet_bank_admin,
        $kredit_bank_admin,
        $jurnal_pembelian,
        $sup
    );

    $stmtJurnal->execute();
}
        $stmtJurnal->close();

        /* =========================================================
           HISTORY PEMBAYARAN
        ========================================================= */

        $userid = $_SESSION['userid']
            ?? $_SESSION['username'];

        $stmtBayar = $conn->prepare("
        INSERT INTO pembelianho1
        (
            tanggal_transaksi,
            j,
            sup,
            bayar,
            pph,
            jenispph,
            sj,
            inv,
            userid
        )
        VALUES
        (?,?,?,?,?,?,?,?,?,?)
        ");

        $stmtBayar->bind_param(
            "sssddssss",
            $tanggal,
            $jurnal_pembelian,
            $sup,
            $bayar,
            $pph,
            $jenispph,
            $sj,
            $invoice,
            $userid
        );

        $stmtBayar->execute();

        $stmtBayar->close();

        $conn->commit();

        echo "
        <script>

        alert('Jurnal berhasil disimpan');

        if(window.opener){
            window.opener.location.reload();
        }

        window.location.href=
        'cetak_jurnal_sementara.php?kode_transaksi=".$kodeCOS."';

        </script>
        ";

        exit();

    }catch(Exception $e){

        $conn->rollback();

        die("
        <h3 style='color:red'>
        ERROR :
        ".$e->getMessage()."
        </h3>
        ");
    }
}

/* =========================================================
   AMBIL DATA
========================================================= */

$jurnal = trim($_GET['J'] ?? '');

if($jurnal==''){
    die("Jurnal tidak ditemukan");
}

$sql = "
SELECT
    p.id_transaksi,
    p.tanggal_transaksi,
    p.j,
    p.sup,
    p.inv,
    p.harga_m,
    p.ppn_m,
    p.hargat_m,
    p.coa,
    p.sj,

    s.nama AS supplier,

    c.account_name AS nama_hutang

FROM pembelianho1 p

LEFT JOIN sup s
ON p.sup=s.kode

LEFT JOIN coa c
ON TRIM(LOWER(p.coa))=
   TRIM(LOWER(c.account_code))

WHERE p.j=?

LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s",$jurnal);

$stmt->execute();

$result = $stmt->get_result();

$data = $result->fetch_assoc();

$stmt->close();

if(!$data){
    die("Data pembelianho1 tidak ditemukan");
}

/* =========================================================
   TOTAL PEMBAYARAN
========================================================= */

$stmtTotal = $conn->prepare("
SELECT
    COALESCE(SUM(bayar),0) AS total_bayar,
    COALESCE(SUM(pph),0) AS total_pph
FROM pembelianho1
WHERE j=?
");

$stmtTotal->bind_param("s",$jurnal);

$stmtTotal->execute();

$resTotal = $stmtTotal->get_result();

$dTotal = $resTotal->fetch_assoc();

$stmtTotal->close();

$total_bayar = (float)$dTotal['total_bayar'];

$total_pph = (float)$dTotal['total_pph'];

/* =========================================================
   VARIABLE
========================================================= */

$id_transaksi = $data['id_transaksi'];

$supplier = $data['supplier'] ?? '';

$invoice = $data['inv'] ?? '';

$dpp = (float)$data['harga_m'];

$ppn = (float)$data['ppn_m'];

$total = (float)$data['hargat_m'];

$sisa_old = $total - $total_bayar - $total_pph;

if($sisa_old < 0){
    $sisa_old = 0;
}

$coa_hutang = $data['coa'] ?? '';

$nama_hutang = $data['nama_hutang'] ?? '';

/* =========================================================
   COA BANK
========================================================= */

$coaBank = $conn->query("
SELECT
    account_code,
    account_name
FROM coa
WHERE layer=4
AND parent_account = '111'
ORDER BY account_code
");
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Pembayaran BANK AP</title>

<style>

body{
    margin:0;
    padding:20px;
    background:#eef2f7;
    font-family:Arial;
}

.container{
    max-width:1000px;
    margin:auto;
    background:#fff;
    padding:25px;
    border-radius:12px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

.info-box{
    background:#f8fafc;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
}

.row{
    display:flex;
    justify-content:space-between;
    padding:8px 0;
    border-bottom:1px dashed #ddd;
}

.label{
    font-weight:bold;
}

.value{
    text-align:right;
}

table{
    width:100%;
    border-collapse:collapse;
}

th,td{
    border:1px solid #ddd;
    padding:10px;
}

th{
    background:#4a69bd;
    color:white;
}

input,select{
    width:100%;
    padding:8px;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    margin-top:15px;
    padding:10px 20px;
    border:none;
    background:#4a69bd;
    color:white;
    border-radius:6px;
    cursor:pointer;
}

.hidden{
    display:none;
}

.box{
    margin-top:10px;
    background:#f1f5fb;
    padding:12px;
    border-radius:8px;
    display:flex;
    justify-content:space-between;
    font-weight:bold;
}

.red{
    color:red;
}

</style>

</head>

<body>

<div class="container">

<h2>Pembayaran BANK AP</h2>

<div class="info-box">

<div class="row">
<div class="label">Jurnal</div>
<div class="value"><?= htmlspecialchars($jurnal) ?></div>
</div>

<div class="row">
<div class="label">Supplier</div>
<div class="value"><?= htmlspecialchars($supplier) ?></div>
</div>

<div class="row">
<div class="label">Invoice</div>
<div class="value"><?= htmlspecialchars($invoice) ?></div>
</div>

<div class="row">
<div class="label">Total Tagihan</div>
<div class="value"><?= number_format($total,2,',','.') ?></div>
</div>

<div class="row">
<div class="label">Total Bayar Lama</div>
<div class="value"><?= number_format($total_bayar,2,',','.') ?></div>
</div>

<div class="row">
<div class="label">Total PPH Lama</div>
<div class="value"><?= number_format($total_pph,2,',','.') ?></div>
</div>

<div class="row">
<div class="label red">Sisa</div>
<div class="value red">
<?= number_format($sisa_old,2,',','.') ?>
</div>
</div>

</div>

<form method="post" id="formBayar">

<input
type="hidden"
name="id_transaksi"
value="<?= $id_transaksi ?>">

<input
type="hidden"
name="coa_hutang"
value="<?= htmlspecialchars($coa_hutang) ?>">

<input
type="hidden"
id="dpp"
value="<?= $dpp ?>">

<input
type="hidden"
id="sisa_old"
value="<?= $sisa_old ?>">

<label>Tanggal</label>

<input
type="date"
name="tanggal"
value="<?= date('Y-m-d') ?>"
required>

<br><br>

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

<select
name="coa_bank"
id="coa_bank"
required>

<option value="">
Pilih Bank
</option>

<?php
while($r=$coaBank->fetch_assoc()){
?>

<option
value="<?= htmlspecialchars($r['account_code']) ?>"
data-nama="<?= htmlspecialchars($r['account_name']) ?>">

<?= htmlspecialchars($r['account_code']) ?>
-
<?= htmlspecialchars($r['account_name']) ?>

</option>

<?php
}
?>

</select>

</td>

<td id="nama_bank">-</td>

<td>0</td>

<td>

<input
type="number"
step="0.01"
min="0"
max="<?= $sisa_old ?>"
name="bayar"
id="bayar"
required>

</td>

</tr>

<tr>

<td>2</td>

<td>
<?= htmlspecialchars($coa_hutang) ?>
</td>

<td>
<?= htmlspecialchars($nama_hutang ?: '-') ?>
</td>

<td>
<span id="hutang_text">0</span>
</td>

<td>0</td>

</tr>

<tr>

<td colspan="5">

<label>

<input
type="checkbox"
id="toggle_pph">

Gunakan PPH

</label>

</td>

</tr>

<tr
id="pph_row"
class="hidden">

<td>3</td>

<td>

<span id="coa_pph_text">-</span>

<input
type="hidden"
name="coa_pph"
id="coa_pph">

</td>

<td id="nama_pph">-</td>

<td>0</td>

<td>

<span id="pph_text">0</span>

<input
type="hidden"
name="pph"
id="pph"
value="0">

</td>

</tr>

<tr
id="pph_option"
class="hidden">

<td colspan="5">

<label>
<input type="radio" name="jenispph" value="PPH15">
PPH15
</label>

<label>
<input type="radio" name="jenispph" value="PPH21">
PPH21
</label>

<label>
<input type="radio" name="jenispph" value="PPH22">
PPH22
</label>

<label>
<input type="radio" name="jenispph" value="PPH23">
PPH23
</label>

<label>
<input type="radio" name="jenispph" value="PPH4A2">
PPH4A2
</label>

<br><br>

<input
type="number"
step="0.01"
id="pph_percent"
placeholder="% PPH">

</td>

</tr>

<tr>

<td colspan="5">

Biaya Admin Bank :

<label>
<input type="radio" name="biaya_bank_radio" value="0" checked>
0
</label>

<label>
<input type="radio" name="biaya_bank_radio" value="2500">
2.500
</label>

<label>
<input type="radio" name="biaya_bank_radio" value="6500">
6.500
</label>

<label>
<input type="radio" name="biaya_bank_radio" value="10000">
10.000
</label>

<input
type="hidden"
name="biaya_bank"
id="biaya_bank"
value="0">

</td>

</tr>

</table>

<div class="box">
<div>Total Bayar Baru</div>
<div id="total_bayar_view">0</div>
</div>

<div class="box">
<div>Sisa Baru</div>
<div id="sisa_view">0</div>
</div>

<button type="submit">
Simpan Pembayaran
</button>

</form>

</div>

<script>

function n(v){

    return parseFloat(
        (v || '0').toString().replace(/,/g,'')
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

function getCoaPPH(jenis){

    jenis=(jenis||'').toUpperCase();

    if(jenis==='PPH15'){
        return {
            coa:'21.04.06.001',
            nama:'PPH15'
        };
    }

    if(jenis==='PPH21'){
        return {
            coa:'21.04.01.001',
            nama:'PPH21'
        };
    }

    if(jenis==='PPH22'){
        return {
            coa:'21.04.05.001',
            nama:'PPH22'
        };
    }

    if(jenis==='PPH23'){
        return {
            coa:'21.04.02.001',
            nama:'PPH23'
        };
    }

    if(jenis==='PPH4A2'){
        return {
            coa:'21.04.07.001',
            nama:'PPH4A2'
        };
    }

    return {
        coa:'',
        nama:''
    };

}

function hitung(){

    let bayar=n(
        document.getElementById('bayar').value
    );

    let total_tagihan=
        <?= json_encode($total) ?>;

    let bayar_old=
        <?= json_encode($total_bayar) ?>;

    let pph_old=
        <?= json_encode($total_pph) ?>;

    let biaya_bank=n(
        document.querySelector(
            'input[name="biaya_bank_radio"]:checked'
        )?.value
    );

    document.getElementById('biaya_bank').value=
        biaya_bank;

    let pph=0;

    let usePPH=
        document.getElementById('toggle_pph').checked;

    let jenispph=
        document.querySelector(
            'input[name="jenispph"]:checked'
        )?.value || '';

    if(usePPH && jenispph){

        let persen=n(
            document.getElementById('pph_percent').value
        );

        let dpp=<?= json_encode($dpp) ?>;

        pph=(persen/100)*dpp;

        let obj=getCoaPPH(jenispph);

        document.getElementById('coa_pph').value=obj.coa;

        document.getElementById('coa_pph_text').innerText=obj.coa;

        document.getElementById('nama_pph').innerText=obj.nama;

    }else{

        document.getElementById('coa_pph').value='';

        document.getElementById('coa_pph_text').innerText='-';

        document.getElementById('nama_pph').innerText='-';
    }

    let totalBayarBaru=
        bayar_old + bayar;

    let totalPPHBaru=
        pph_old + pph;

    let sisaBaru=
        total_tagihan -
        totalBayarBaru -
        totalPPHBaru;

    document.getElementById('pph').value=
        pph.toFixed(2);

    document.getElementById('pph_text').innerText=
        f(pph);

    document.getElementById('hutang_text').innerText=
        f(bayar+pph);

    document.getElementById('total_bayar_view').innerText=
        f(totalBayarBaru);

    document.getElementById('sisa_view').innerText=
        f(sisaBaru);

}

document.getElementById('coa_bank')
.addEventListener('change',function(){

    let nama=
        this.options[this.selectedIndex]
        ?.dataset?.nama || '-';

    document.getElementById('nama_bank')
    .innerText=nama;

});

document.getElementById('toggle_pph')
.addEventListener('change',function(){

    document.getElementById('pph_row')
    .classList.toggle(
        'hidden',
        !this.checked
    );

    document.getElementById('pph_option')
    .classList.toggle(
        'hidden',
        !this.checked
    );

    hitung();

});

document.querySelectorAll(
'input[name="jenispph"]'
).forEach(r=>{

    r.addEventListener('change',hitung);

});

document.querySelectorAll(
'input[name="biaya_bank_radio"]'
).forEach(r=>{

    r.addEventListener('change',hitung);

});

document.addEventListener('input',function(e){

    if(
        e.target.matches(
            '#bayar,#pph_percent'
        )
    ){
        hitung();
    }

});

document.getElementById('formBayar')
.addEventListener('submit',function(e){

    let bayar=n(
        document.getElementById('bayar').value
    );

    let sisa=parseFloat(
        document.getElementById('sisa_view')
        .innerText
        .replace(/\./g,'')
        .replace(',','.')
    ) || 0;

    if(bayar<=0){

        alert('Pembayaran harus diisi');

        e.preventDefault();

        return;
    }

    if(sisa < 0){

        alert('Pembayaran melebihi tagihan');

        e.preventDefault();

        return;
    }

    if(!confirm('Simpan pembayaran ?')){

        e.preventDefault();
    }

});

hitung();

</script>

</body>
</html>

<?php
$conn->close();
?>