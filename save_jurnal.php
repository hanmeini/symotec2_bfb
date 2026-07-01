<?php
require_once 'config1.php';
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $nomor = generateNomorDokumen($conn, 'JV'); // Jurnal Voucher
        $tanggal = $_POST['tanggal'] ?? date('Y-m-d');
        $location = $_POST['location'] ?? '';
        
        $coa = $_POST['coa'] ?? [];
        $debet = $_POST['debet'] ?? [];
        $kredit = $_POST['kredit'] ?? [];
        $keterangan = $_POST['keterangan'] ?? [];
        
        $userin = $_SESSION['username'] ?? 'system';
        
        $stmt = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, coa, debet, kredit, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($coa as $i => $kode_coa) {
            if (empty($kode_coa)) continue;
            
            $d = (float)str_replace(',', '', $debet[$i] ?? '0');
            $k = (float)str_replace(',', '', $kredit[$i] ?? '0');
            $ket = $keterangan[$i] ?? '';
            
            $stmt->bind_param("sssdds", $nomor, $tanggal, $kode_coa, $d, $k, $ket);
            $stmt->execute();
        }
        $stmt->close();
        
        $conn->commit();
        echo "<script>
            alert('Jurnal Umum Berhasil disimpan! Nomor: $nomor');
            window.location.href = 'jurnal.php';
        </script>";
        
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>
            alert('Gagal: " . addslashes($e->getMessage()) . "');
            window.history.back();
        </script>";
    }
}
?>
