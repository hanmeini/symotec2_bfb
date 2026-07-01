<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

if (!isset($_SESSION['username'])) {
    die("Unauthorized");
}

require_once 'config1.php';





$conn->begin_transaction();

try {

/* ================= INPUT ================= */
$tanggal = date('Y-m-d H:i:s', strtotime($_POST['tanggal']));
$idbeli  = (int)$_POST['idbeli'];
$inv     = $_POST['inv'] ?? '';
$idt     = $_POST['titipan_id'] ?? null;

if($idbeli <= 0) throw new Exception("ID tidak valid");

/* ================= NORMALISASI JURNAL ================= */
$jurnal = [];

foreach($_POST['jurnal'] as $row){

    $coa    = trim($row['coa'] ?? '');
    $debet  = (float)($row['debet'] ?? 0);
    $kredit = (float)($row['kredit'] ?? 0);

    // skip baris kosong
    if(!$coa) continue;
    if($debet == 0 && $kredit == 0) continue;

    $jurnal[] = [
        'coa' => $coa,
        'debet' => $debet,
        'kredit' => $kredit
    ];
}

if(empty($jurnal)){
    throw new Exception("Jurnal kosong!");
}

/* ================= HITUNG ================= */
$totalD = 0;
$totalK = 0;

foreach($jurnal as $r){
    $totalD += $r['debet'];
    $totalK += $r['kredit'];
}

if(round($totalD,2) != round($totalK,2)){
    throw new Exception("Jurnal tidak balance! Debet=$totalD Kredit=$totalK");
}

$hutang = $totalD;

/* ================= AMBIL pembelianho1 ================= */
$stmtP = $conn->prepare("
SELECT sup, sisa, pph15m, pph22m, pph23m
FROM pembelianho1 
WHERE id_transaksi=? FOR UPDATE
");
$stmtP->bind_param("i",$idbeli);
$stmtP->execute();
$p = $stmtP->get_result()->fetch_assoc();

if(!$p) throw new Exception("pembelianho1 tidak ditemukan");

$sup = $p['sup'];
$sisa_old = (float)$p['sisa'];

$pph15 = (float)$p['pph15m'];
$pph22 = (float)$p['pph22m'];
$pph23 = (float)$p['pph23m'];

/* ================= HITUNG SISA ================= */
$sisaBaru = $sisa_old - $hutang;
if($sisaBaru < 0) $sisaBaru = 0;

/* ================= NOMOR PV ================= */
$res = $conn->query("SELECT MAX(id) AS max_id FROM pv FOR UPDATE");
$row = $res->fetch_assoc();

$no = $row['max_id'] ? $row['max_id'] + 1 : 1;
$kode = "PV".date('Y').sprintf('%04d',$no);

$stmtPV = $conn->prepare("INSERT INTO pv (pv) VALUES (?)");
$stmtPV->bind_param("s",$kode);
$stmtPV->execute();

/* ================= PREPARE ================= */
$stmtJ = $conn->prepare("
INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)
");

$stmtCoa = $conn->prepare("SELECT account_name FROM coa WHERE account_code=? LIMIT 1");

$ket = "Pembayaran Hutang via Titipan";

/* ================= DETEKSI TITIPAN ================= */
$titipan_pakai = 0;

foreach($jurnal as $r){
    if($r['coa'] == '11.04.01.001'){
        $titipan_pakai = $r['kredit'];
    }
}

/* ================= INSERT JURNAL ================= */
foreach($jurnal as $r){

    $coa = $r['coa'];
    $debet = $r['debet'];
    $kredit = $r['kredit'];

    // ambil nama akun
    $stmtCoa->bind_param("s",$coa);
    $stmtCoa->execute();
    $nama = $stmtCoa->get_result()->fetch_assoc()['account_name'] ?? '';

    // DETEKSI PPH
    if(strpos($coa,'21.04.06.001')===0) $pph15 += $kredit;
    if(strpos($coa,'21.04.05.001')===0) $pph22 += $kredit;
    if(strpos($coa,'21.04.02.001')===0) $pph23 += $kredit;

    $stmtJ->bind_param(
        "ssssddsss",
        $kode,
        $tanggal,
        $ket,
        $coa,
        $nama,
        $debet,
        $kredit,
        $idbeli,
        $sup
    );

    $stmtJ->execute();
}

/* ================= UPDATE pembelianho1 ================= */
$stmtUp = $conn->prepare("
UPDATE pembelianho1 
SET sisa=?, pph15m=?, pph22m=?, pph23m=?
WHERE id_transaksi=?
");

$stmtUp->bind_param("ddddi",$sisaBaru,$pph15,$pph22,$pph23,$idbeli);
$stmtUp->execute();

/* ================= INSERT APBY ================= */
$stmtAp = $conn->prepare("
INSERT INTO apby (tanggal, inv, sup, ket, bayar1) 
VALUES (?, ?, ?, ?, ?)
");

$stmtAp->bind_param("ssssd",$tanggal,$inv,$sup,$ket,$totalD);
$stmtAp->execute();

/* ================= UPDATE TITIPAN ================= */
if($idt && $titipan_pakai > 0){

    $stmtT = $conn->prepare("
    SELECT nominal FROM titipanap WHERE id=? FOR UPDATE
    ");
    $stmtT->bind_param("i",$idt);
    $stmtT->execute();
    $t = $stmtT->get_result()->fetch_assoc();

    if(!$t) throw new Exception("Titipan tidak ditemukan");

    $nominal = (float)$t['nominal'];

    if($titipan_pakai > $nominal){
        throw new Exception("Titipan melebihi saldo");
    }

    $sisaTitipan = $nominal - $titipan_pakai;

    // tandai sudah dipakai
    $stmtUpd = $conn->prepare("
    UPDATE titipanap SET inv=? WHERE id=?
    ");
    $stmtUpd->bind_param("si",$inv,$idt);
    $stmtUpd->execute();

    // insert sisa titipan
    if($sisaTitipan > 0){

        $desc = "Sisa titipan dari ID ".$idt;

        $stmtNew = $conn->prepare("
        INSERT INTO titipanap (tanggal, nominal, description, sup)
        VALUES (?,?,?,?)
        ");
        $stmtNew->bind_param("sdss",$tanggal,$sisaTitipan,$desc,$sup);
        $stmtNew->execute();
    }
}

/* ================= COMMIT ================= */
$conn->commit();

echo "<script>
alert('Berhasil disimpan: $kode');
window.location.href='cetak_jurnal.php?kode_transaksi=$kode';
</script>";

}catch(Exception $e){

$conn->rollback();
die("Error: ".$e->getMessage());

}
?>