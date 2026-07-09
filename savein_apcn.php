<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

if (!isset($_SESSION['username'])) {
    die("Unauthorized");
}

require_once 'config1.php';





$conn->begin_transaction();

try{

/* ================= INPUT ================= */

$tanggal = date(
    'Y-m-d H:i:s',
    strtotime($_POST['tanggal'])
);

$idbeli = (int)($_POST['idbeli'] ?? 0);

$inv = trim($_POST['inv'] ?? '');

$id_cn = (int)($_POST['id_cn'] ?? 0);

if($idbeli <= 0){
    throw new Exception("ID pembelianho1 tidak valid");
}

/* ================= NORMALISASI JURNAL ================= */

$jurnal = [];

if(empty($_POST['jurnal'])){
    throw new Exception("Jurnal kosong");
}

foreach($_POST['jurnal'] as $r){

    $coa = trim($r['coa'] ?? '');

    $debet = (float)($r['debet'] ?? 0);

    $kredit = (float)($r['kredit'] ?? 0);

    if(!$coa){
        continue;
    }

    if($debet == 0 && $kredit == 0){
        continue;
    }

    $jurnal[] = [
        'coa'    => $coa,
        'debet'  => $debet,
        'kredit' => $kredit
    ];
}

if(empty($jurnal)){
    throw new Exception("Tidak ada jurnal");
}

/* ================= TOTAL ================= */

$totalD = 0;
$totalK = 0;

foreach($jurnal as $r){

    $totalD += $r['debet'];

    $totalK += $r['kredit'];
}

if(round($totalD,2) != round($totalK,2)){

    throw new Exception(
        "Jurnal tidak balance : ".
        "Debet=".$totalD.
        " Kredit=".$totalK
    );
}

$hutang = $totalD;

/* ================= pembelianho1 ================= */

$stmtP = $conn->prepare("
SELECT 
    sup,
    sisa,
    pph15m,
    pph22m,
    pph23m
FROM pembelianho1
WHERE id_transaksi = ?
FOR UPDATE
");

$stmtP->bind_param("i",$idbeli);

$stmtP->execute();

$p = $stmtP->get_result()->fetch_assoc();

if(!$p){
    throw new Exception("Data pembelianho1 tidak ditemukan");
}

$sup = $p['sup'];

$sisa_old = (float)$p['sisa'];

$pph15 = (float)$p['pph15m'];

$pph22 = (float)$p['pph22m'];

$pph23 = (float)$p['pph23m'];

/* ================= HITUNG SISA ================= */

$sisaBaru = $sisa_old - $hutang;

if($sisaBaru < 0){
    $sisaBaru = 0;
}

/* ================= NOMOR AP ================= */
require_once 'generate_nomor_ap.php';
$kode = generateNomorAP($conn, 'PV', $tanggal);

/* ================= PREPARE JURNAL ================= */

$stmtJ = $conn->prepare("
INSERT INTO jurnal
(
    journal_number,
    tanggal,
    keterangan,
    coa,
    account_name,
    debet,
    kredit,
    kode_booking,
    supcust
)
VALUES (?,?,?,?,?,?,?,?,?)
");

$stmtCoa = $conn->prepare("
SELECT account_name
FROM coa
WHERE account_code = ?
LIMIT 1
");

$ket = "Pembayaran Hutang via CN";

/* ================= DETEKSI CN ================= */

$cn_pakai = 0;

foreach($jurnal as $r){

    /*
    CN pembelianho1
    */

    if($r['coa'] == '11.04.01.001'){

        $cn_pakai = (float)$r['kredit'];
    }
}

/* ================= INSERT JURNAL ================= */

foreach($jurnal as $r){

    $coa    = $r['coa'];

    $debet  = $r['debet'];

    $kredit = $r['kredit'];

    /* ================= NAMA AKUN ================= */

    $stmtCoa->bind_param("s",$coa);

    $stmtCoa->execute();

    $nama = $stmtCoa
        ->get_result()
        ->fetch_assoc()['account_name'] ?? '';

    /* ================= PPH ================= */

    if(strpos($coa,'21.04.06.001') === 0){

        $pph15 += $kredit;
    }

    if(strpos($coa,'21.04.05.001') === 0){

        $pph22 += $kredit;
    }

    if(strpos($coa,'21.04.02.001') === 0){

        $pph23 += $kredit;
    }

    /* ================= INSERT ================= */

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
SET
    sisa   = ?,
    pph15m = ?,
    pph22m = ?,
    pph23m = ?
WHERE id_transaksi = ?
");

$stmtUp->bind_param(
    "ddddi",
    $sisaBaru,
    $pph15,
    $pph22,
    $pph23,
    $idbeli
);

$stmtUp->execute();

/* ================= APBY ================= */

$stmtAp = $conn->prepare("
INSERT INTO apby
(
    tanggal,
    inv,
    sup,
    ket,
    bayar1
)
VALUES (?,?,?,?,?)
");

$stmtAp->bind_param(
    "ssssd",
    $tanggal,
    $inv,
    $sup,
    $ket,
    $totalD
);

$stmtAp->execute();

/* ================= UPDATE CN ================= */

if($id_cn > 0 && $cn_pakai > 0){

    $stmtCN = $conn->prepare("
    SELECT cn
    FROM cndn
    WHERE idn = ?
    FOR UPDATE
    ");

    $stmtCN->bind_param("i",$id_cn);

    $stmtCN->execute();

    $cnData = $stmtCN
        ->get_result()
        ->fetch_assoc();

    if(!$cnData){

        throw new Exception("CN tidak ditemukan");
    }

    $saldoCN = (float)$cnData['cn'];

    if($cn_pakai > $saldoCN){

        throw new Exception(
            "Nominal CN melebihi saldo"
        );
    }

    $sisaCN = $saldoCN - $cn_pakai;

    /* ================= TANDAI TERPAKAI ================= */

    $stmtUpd = $conn->prepare("
    UPDATE cndn
    SET inv = ?
    WHERE idn = ?
    ");

    $stmtUpd->bind_param(
        "si",
        $inv,
        $id_cn
    );

    $stmtUpd->execute();

    /* ================= SISA CN ================= */

    if($sisaCN > 0){

        $desc = "Sisa CN dari ID ".$id_cn;

        $stmtNew = $conn->prepare("
        INSERT INTO cndn
        (
            tanggal,
            no_cn_dn,
            cn,
            sup
        )
        VALUES (?,?,?,?)
        ");

        $noCNBaru = "SISA-".$id_cn;

        $stmtNew->bind_param(
            "ssds",
            $tanggal,
            $noCNBaru,
            $sisaCN,
            $sup
        );

        $stmtNew->execute();
    }
}

/* ================= COMMIT ================= */

$conn->commit();

echo "
<script>

alert('Berhasil disimpan : ".$kode."');

window.location.href =
'cetak_jurnal.php?kode_transaksi=".$kode."';

</script>
";

}catch(Exception $e){

    $conn->rollback();

    die(
        "Error : ".
        $e->getMessage()
    );
}
?>