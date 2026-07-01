<?php
session_start();
require_once 'config1.php';
require_once 'functions_stock.php';

error_reporting(E_ALL);
ini_set('display_errors',1);

/* ================= LOGIN ================= */

if (!isset($_SESSION['userid'])) {
    header("Location:index.html");
    exit();
}

/* ================= DB ================= */

function db_connect() {

    $c = new mysqli(
        getenv('DB_HOST'),
        getenv('DB_USER'),
        getenv('DB_PASS'),
        getenv('DB_NAME')
    );

    if($c->connect_error){
        throw new Exception($c->connect_error);
    }

    $c->set_charset('utf8mb4');

    return $c;
}

function e($s){
    return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
}

/* ================= NOMOR ANTAR ================= */

function generateANTAR($conn) {

    $prefix = "ANTAR".date("Y").date("m");

    $stmt = $conn->prepare("
        SELECT sj
        FROM antar
        WHERE sj LIKE CONCAT(?,'%')
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->bind_param("s",$prefix);
    $stmt->execute();
    $stmt->bind_result($last);
    $stmt->fetch();
    $stmt->close();

    $urut = 1;

    if($last){
        $urut = (int)substr($last,-5) + 1;
    }

    return $prefix.str_pad($urut,5,'0',STR_PAD_LEFT);
}

/* ================= SAVE SJ ================= */

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_sj'])){

    $asal   = $_POST['id_gudang_asal'] ?? '';
    $tujuan = $_POST['id_gudang'] ?? '';
    $kode_b = $_POST['kode_b'] ?? [];
    $jumlah = $_POST['jumlah_k'] ?? [];

    if(!$asal){
        $errorMsg="Pilih gudang asal";
    }
    elseif(!$tujuan){
        $errorMsg="Pilih gudang tujuan";
    }
    elseif($asal==$tujuan){
        $errorMsg="Gudang tidak boleh sama";
    }
    else{

        try{

            $conn = db_connect();
            $conn->begin_transaction();

            $sj = generateANTAR($conn);

            $stmtAntar = $conn->prepare("
                INSERT INTO antar
                (sj,tanggal,pengirim,penerima,notrim)
                VALUES (?,CURDATE(),?,?, '')
            ");

            $stmtAntar->bind_param("sii",$sj,$asal,$tujuan);

            if(!$stmtAntar->execute()){
                throw new Exception($stmtAntar->error);
            }

            $stmtAntar->close();

            /* ===== AMBIL NAMA GUDANG ===== */

            $gudang=[];

            $q = $conn->query("
                SELECT id_gudang,nama_gudang
                FROM master_gudang
            ");

            while($r = $q->fetch_assoc()){
                $gudang[$r['id_gudang']] = $r['nama_gudang'];
            }

            $namaGudangAsal   = $gudang[$asal] ?? '';
            $namaGudangTujuan = $gudang[$tujuan] ?? '';

            /* ===== PREPARE TRANSAKSI ===== */

            $stmtOut = $conn->prepare("
                INSERT INTO stock
                (tanggal_transaksi,J,kodeb,jumlah_k,userid,sj,id_gudang)
                VALUES (NOW(),?,?,?,?,?,?)
            ");

            $stmtIn = $conn->prepare("
                INSERT INTO stock
                (tanggal_transaksi,J,kodeb,jumlah_m,userid,sj,id_gudang)
                VALUES (NOW(),?,?,?,?,?,?)
            ");

            foreach($kode_b as $i=>$kode){

                if(empty($kode)) continue;

                $qty = (float)($jumlah[$i] ?? 0);

                if($qty<=0) continue;

                /* ===== DATA BARANG ===== */

                $stmtBarang = $conn->prepare("
                    SELECT nama_b AS namabarang,jenis, '' as satuan
                    FROM b
                    WHERE kode_b=?
                    LIMIT 1
                ");

                $stmtBarang->bind_param("s",$kode);
                $stmtBarang->execute();
                $stmtBarang->bind_result($nama,$jenis,$satuan);
                $stmtBarang->fetch();
                $stmtBarang->close();

                if(!$nama){
                    throw new Exception("Barang tidak ditemukan ".$kode);
                }

                $userid=$_SESSION['userid'];

                /* ===== PERUBAHAN KOLOM J ===== */

                $J_out=$sj;   // transaksi keluar pakai nomor SJ
                $J_in="";     // transaksi masuk kosong (belum diterima)

                $outStatus="OUT";
                $inStatus="IN";

                $stmtOut->bind_param(
                    "ssdsis",
                    $J_out,
                    $kode,
                    $qty,
                    $userid,
                    $sj,
                    $asal
                );

                if(!$stmtOut->execute()){
                    throw new Exception($stmtOut->error);
                }

                $stmtIn->bind_param(
                    "ssdsis",
                    $J_in,
                    $kode,
                    $qty,
                    $userid,
                    $sj,
                    $tujuan
                );

                if(!$stmtIn->execute()){
                    throw new Exception($stmtIn->error);
                }
                
                // Recalculate stock history for this item
                recalculate_stock_history($conn, $kode);

            }

            $stmtOut->close();
            $stmtIn->close();

            $conn->commit();

            $successMsg="SJ berhasil dibuat : ".$sj;

        }
        catch(Exception $e){

            if(isset($conn)) $conn->rollback();

            $errorMsg=$e->getMessage();

        }

    }

}

/* ================= LOAD GUDANG ================= */

$gudang_options=[];

$c=db_connect();

$g=$c->query("
SELECT id_gudang,nama_gudang
FROM master_gudang
ORDER BY nama_gudang
");

while($r=$g->fetch_assoc()){
$gudang_options[]=$r;
}

$c->close();

/* ================= LOAD BARANG ================= */

$barangList=[];
$idGudangAsal=$_POST['id_gudang_asal'] ?? '';

if($idGudangAsal){

$c=db_connect();

$stmt=$c->prepare("
SELECT 
t.kodeb AS kode_b,
b.nama_b AS namabarang,
SUM(t.jumlah_m - t.jumlah_k) AS stok
FROM stock t
LEFT JOIN b ON b.kode_b = t.kodeb
WHERE t.id_gudang=?
GROUP BY t.kodeb,b.nama_b
HAVING stok>0
ORDER BY b.nama_b
");

$stmt->bind_param("i",$idGudangAsal);
$stmt->execute();
$stmt->bind_result($kode,$nama,$stok);

while($stmt->fetch()){

$barangList[]=[
'kodeb'=>$kode,
'nama'=>$nama
];

}

$stmt->close();
$c->close();

}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>SJ Antar Gudang</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

body{font-family:Arial;background:#f4f4f4;padding:20px}

.wrap{
max-width:1000px;
margin:auto;
background:#fff;
padding:20px;
border-radius:10px;
box-shadow:0 3px 12px rgba(0,0,0,.08);
}

table{width:100%;border-collapse:collapse;margin-top:15px}

th,td{
border:1px solid #ddd;
padding:8px;
text-align:center
}

th{background:#f7f7f7}

input,select{
width:100%;
padding:6px;
border:1px solid #ccc;
border-radius:6px
}

button{
padding:6px 12px;
border:none;
border-radius:6px;
cursor:pointer
}

.add{background:#28a745;color:white}
.del{background:#dc3545;color:white}

.msg{
padding:10px;
margin-bottom:10px;
border-radius:5px;
}

.success{background:#d4edda;color:#155724}
.error{background:#f8d7da;color:#721c24}

</style>

</head>
<body>

<a href="sjrekapout.php"><i class="fa-solid fa-circle-left"></i></a>

<div class="wrap">

<h2>SJ Antar Gudang</h2>

<?php if(!empty($successMsg)) echo '<div class="msg success">'.e($successMsg).'</div>'; ?>
<?php if(!empty($errorMsg)) echo '<div class="msg error">'.e($errorMsg).'</div>'; ?>

<form method="POST">

<label>Gudang Asal</label>

<select name="id_gudang_asal" onchange="this.form.submit()" required>

<option value="">-- pilih gudang --</option>

<?php foreach($gudang_options as $g): ?>

<option value="<?=e($g['id_gudang'])?>"
<?=($idGudangAsal==$g['id_gudang']?'selected':'')?>>

<?=e($g['nama_gudang'])?>

</option>

<?php endforeach; ?>

</select>


<label>Gudang Tujuan</label>

<select name="id_gudang" required>

<option value="">-- pilih gudang --</option>

<?php foreach($gudang_options as $g):

if($g['id_gudang']==$idGudangAsal) continue;

?>

<option value="<?=e($g['id_gudang'])?>">

<?=e($g['nama_gudang'])?>

</option>

<?php endforeach; ?>

</select>


<table>

<tr>
<th>Barang</th>
<th>Qty</th>
<th>Aksi</th>
</tr>

<tbody id="itemBody">

<tr>

<td>

<select name="kode_b[]" required>

<option value="">-- pilih barang --</option>

<?php foreach($barangList as $b): ?>

<option value="<?=e($b['kodeb'])?>">
<?=e($b['kodeb'].' - '.$b['nama'])?>
</option>

<?php endforeach; ?>

</select>

</td>

<td>
<input type="number" name="jumlah_k[]" value="1" min="1">
</td>

<td>
<button type="button" class="del" onclick="this.closest('tr').remove()">Hapus</button>
</td>

</tr>

</tbody>

</table>

<br>

<button type="button" class="add" onclick="addRow()">Tambah Item</button>

<br><br>

<input type="submit" name="submit_sj" value="Simpan SJ">

</form>

</div>

<script>

const optionHTML=`<?php foreach($barangList as $b): ?>
<option value="<?=e($b['kodeb'])?>">
<?=e($b['kodeb'].' - '.$b['nama'])?>
</option>
<?php endforeach;?>`;

function addRow(){

const tr=document.createElement('tr');

tr.innerHTML=`

<td>
<select name="kode_b[]" required>
<option value="">-- pilih barang --</option>
${optionHTML}
</select>
</td>

<td>
<input type="number" name="jumlah_k[]" value="1" min="1">
</td>

<td>
<button type="button" class="del"
onclick="this.closest('tr').remove()">Hapus</button>
</td>

`;

document.getElementById('itemBody').appendChild(tr);

}

</script>

</body>
</html>