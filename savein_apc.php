<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';





$conn->begin_transaction();

try {

    // ================= INPUT =================
    $tanggal = date('Y-m-d H:i:s', strtotime($_POST['tanggal']));
    $idbeli  = (int)$_POST['idbeli'];
    $totalSisa = (float)$_POST['totalSisa'];
    $inv = $_POST['inv'] ?? '';

    // ================= NOMOR COS =================
    $result = $conn->query("SELECT MAX(id) AS max_nomor FROM cos FOR UPDATE");
    $max_nomor = ($result && $result->num_rows > 0)
        ? intval($result->fetch_assoc()['max_nomor'])
        : 0;

    $nomor_formatted = sprintf('%04d', $max_nomor + 1);
    $tahun = date('Y', strtotime($tanggal));
    $kode = "COS".$tahun.$nomor_formatted;

    $stmtCos = $conn->prepare("INSERT INTO cos (cos) VALUES (?)");
    $stmtCos->bind_param("s",$kode);
    $stmtCos->execute();
    $stmtCos->close();

    // ================= AMBIL pembelianho1 =================
    $stmtPembelian = $conn->prepare("
        SELECT sup, pph15m, pph22m, pph23m 
        FROM pembelianho1 
        WHERE id_transaksi=?
    ");
    $stmtPembelian->bind_param("i", $idbeli);
    $stmtPembelian->execute();
    $p = $stmtPembelian->get_result()->fetch_assoc();

    if (!$p) {
        throw new Exception("Data pembelianho1 tidak ditemukan");
    }

    $sup = $p['sup'];
    $pph15 = (float)$p['pph15m'];
    $pph22 = (float)$p['pph22m'];
    $pph23 = (float)$p['pph23m'];

    // ================= VALIDASI BALANCE =================
    $totalD = 0;
    $totalK = 0;

    foreach($_POST['jurnal'] as $row){
        $totalD += (float)$row['debet'];
        $totalK += (float)$row['kredit'];
    }

    if(round($totalD,2) != round($totalK,2)){
        throw new Exception("Jurnal tidak balance!");
    }

    // ================= PREPARE =================
    $stmt = $conn->prepare("
        INSERT INTO jurnal
        (jurnal_sementara, tanggal, keterangan, coa, account_name, debet, kredit, kode_booking, supcust)
        VALUES (?,?,?,?,?,?,?,?,?)
    ");

    $stmtCoa = $conn->prepare("SELECT account_name FROM coa WHERE account_code=? LIMIT 1");

    // ================= LOOP =================
    foreach($_POST['jurnal'] as $row){

        $coa    = trim($row['coa'] ?? '');
        $debet  = (float)($row['debet'] ?? 0);
        $kredit = (float)($row['kredit'] ?? 0);

        if(!$coa) continue;
        if($debet==0 && $kredit==0) continue;

        // ambil nama akun
        $stmtCoa->bind_param("s", $coa);
        $stmtCoa->execute();
        $nama = $stmtCoa->get_result()->fetch_assoc()['account_name'] ?? '';

        // PPH
        if(strpos($coa,'21.04.06.001') === 0) $pph15 += $kredit;
        if(strpos($coa,'21.04.05.001') === 0) $pph22 += $kredit;
        if(strpos($coa,'21.04.02.001') === 0) $pph23 += $kredit;

        $keterangan = "Pembayaran Hutang";

        $stmt->bind_param(
            "ssssddsss",
            $kode,
            $tanggal,
            $keterangan,
            $coa,
            $nama,
            $debet,
            $kredit,
            $idbeli,
            $sup
        );

        $stmt->execute();
    }

    $stmt->close();

    // ================= UPDATE pembelianho1 =================
    $stmt2 = $conn->prepare("
        UPDATE pembelianho1 
        SET sisa=?, pph15m=?, pph22m=?, pph23m=? 
        WHERE id_transaksi=?
    ");

    $stmt2->bind_param("ddddi", $totalSisa, $pph15, $pph22, $pph23, $idbeli);
    $stmt2->execute();
    $stmt2->close();

    // ================= INSERT APBY =================
    $stmt3 = $conn->prepare("
        INSERT INTO apby (tanggal, inv, sup, ket, bayar1) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt3->bind_param("ssssd", $tanggal, $inv, $sup, $keterangan, $totalD);
    $stmt3->execute();

    $conn->commit();

    echo "<script>
        alert('Berhasil disimpan: $kode');
     
         window.location.href = 'cetak_jurnal_sementara.php?kode_transaksi=$kode';
    </script>";

} catch (Exception $e){

    $conn->rollback();
    die("Error: ".$e->getMessage());
}
?>