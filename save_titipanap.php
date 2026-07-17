<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['username'])) {
    die("Unauthorized");
}

require_once 'config1.php';





$conn->begin_transaction();

try {

    // ================= INPUT =================
    if($_POST['csrf'] !== $_SESSION['csrf']){
        throw new Exception("CSRF tidak valid");
    }

    $tanggal = date('Y-m-d H:i:s', strtotime($_POST['tanggal']));
    $sup     = $_POST['sup'];
    $ket     = $_POST['keterangan'];
    $bank    = $_POST['bank_coa'];
    $nominal = (float)$_POST['nominal'];

    if(!$tanggal || !$sup || !$bank || $nominal <= 0){
        throw new Exception("Data tidak valid");
    }

    // ================= NOMOR TITIPAN =================
   // ================= NOMOR AP =================
require_once 'generate_nomor_ap.php';
$kode = generateNomorAP($conn, 'COS', $tanggal);

    $stmtCos = $conn->prepare("INSERT INTO cos (cos) VALUES (?)");
    $stmtCos->bind_param("s",$kode);
    $stmtCos->execute();
    $stmtCos->close();

    // ================= VALIDASI COA =================
    $cek = $conn->prepare("
        SELECT account_name 
        FROM coa 
        WHERE account_code=? 
        AND parent_account = '111'
    ");
    $cek->bind_param("s",$bank);
    $cek->execute();
    $dataBank = $cek->get_result()->fetch_assoc();

    if(!$dataBank){
        throw new Exception("COA bank tidak valid");
    }

    $namaBank = $dataBank['account_name'];

    // ================= VALIDASI BALANCE =================
    $totalD = $nominal;
    $totalK = $nominal;

    if(round($totalD,2) != round($totalK,2)){
        throw new Exception("Jurnal tidak balance!");
    }

    // ================= INSERT TITIPAN =================
    $stmtTitipan = $conn->prepare("
        INSERT INTO titipanap
        (tanggal, nominal, description, sup,  created_at, updated_at)
        VALUES (?, ?, ?, ?,  NOW(), NOW())
    ");

    $stmtTitipan->bind_param("sdss", $tanggal, $nominal, $ket, $sup);
    $stmtTitipan->execute();

    // ================= PREPARE JURNAL =================
    $stmt = $conn->prepare("
        INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)
    ");

    $keterangan = "Titipan pembelianho1";

    // ================= 1. DEBET UANG MUKA =================
    $coa_um = "11.04.01.001";

    $stmtCoa = $conn->prepare("SELECT account_name FROM coa WHERE account_code=? LIMIT 1");
    $stmtCoa->bind_param("s", $coa_um);
    $stmtCoa->execute();
    $namaUM = $stmtCoa->get_result()->fetch_assoc()['account_name'] ?? 'UANG MUKA';

    $zero = 0;

    $stmt->bind_param(
        "ssssddsss",
        $kode,
        $tanggal,
        $keterangan,
        $coa_um,
        $namaUM,
        $nominal,
        $zero,
        $kode,
        $sup
    );
    $stmt->execute();

    // ================= 2. KREDIT BANK =================
    $stmt->bind_param(
        "ssssddsss",
        $kode,
        $tanggal,
        $keterangan,
        $bank,
        $namaBank,
        $zero,
        $nominal,
        $kode,
        $sup
    );
    $stmt->execute();

    $stmt->close();

    // ================= COMMIT =================
    $conn->commit();

    echo "<script>
        alert('Berhasil disimpan: $kode');
        window.location.href='cetak_jurnal_sementara.php?kode_transaksi=$kode';
    </script>";

} catch (Exception $e){

    $conn->rollback();
    die("Error: ".$e->getMessage());
}
?>