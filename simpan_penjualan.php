<?php
require_once 'config1.php';
require_once 'functions.php';
require_once 'functions_stock.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $nomor = generateNomorDokumen($conn, 'INV');
        $tanggal = $_POST['tanggal_transaksi'] ?? date('Y-m-d H:i:s');
        $cust = $_POST['cust'] ?? '';
        $total_harga = $_POST['total_harga_termasuk_ppn'] ?? 0;
        
        $kode_b = $_POST['kode_b'] ?? [];
        $nama_b = $_POST['nama_b'] ?? [];
        $jumlah_k = $_POST['jumlah_k'] ?? [];
        $harga_k2 = $_POST['harga_k2'] ?? []; // DPP per row
        $ppn_k_arr = $_POST['ppn_k'] ?? [];   // PPN per row
        $hargat_k = $_POST['hargat_k'] ?? []; // Total per row
        $garansi = $_POST['garansi'] ?? [];
        $bulan = $_POST['bulan'] ?? [];
        $noseri = $_POST['noseri'] ?? [];
        $id_gudang = isset($_POST['id_gudang']) ? (int)$_POST['id_gudang'] : 0;
        
        $po = $_POST['po'] ?? '';
        $diskon = $_POST['diskon'] ?? 0;
        $dpp = $_POST['total_dpp_setelah_diskon'] ?? 0;
        $ppn = $_POST['total_ppn'] ?? 0;
        $userinv = $_POST['username1'] ?? $_SESSION['username'] ?? 'system';
        
        $stmt = $conn->prepare("INSERT INTO penjualanHO1 (tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, userinv, po) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddddss", $tanggal, $nomor, $cust, $diskon, $dpp, $ppn, $total_harga, $userinv, $po);
        $stmt->execute();
        $stmt->close();
        
        // Otomatis catat uang masuk (Pelunasan Cash) agar muncul di Laporan Pembayaran
        $bank = 'CASH';
        $cabang = ($_SESSION['location'] === 'HO' || $_SESSION['location'] === 'HO1') ? 'Pusat' : 'Cabang';
        $stmtBayar = $conn->prepare("INSERT INTO pembayaranho1 (tanggal, j_value, cust, bayar, bank, userbayar, cabang) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtBayar->bind_param("sssdsss", $tanggal, $nomor, $cust, $total_harga, $bank, $userinv, $cabang);
        $stmtBayar->execute();
        $stmtBayar->close();

        
        // Kurangi Stock beserta Harga (Akuntansi)
        $userin = $_SESSION['username'] ?? 'system';
        $stmtStock = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_k, harga_k, ppn_k, hargat_k, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($kode_b as $i => $kode) {
            if (empty($kode)) continue;
            $qty = (float)($jumlah_k[$i] ?? 0);
            if ($qty <= 0) continue;
            
            $nama = $nama_b[$i] ?? '';
            // Normalisasi angka desimal/ribuan secara cerdas
            $normalize = function($val) {
                $val = str_replace(' ', '', $val);
                if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
                    $val = str_replace('.', '', $val);
                    $val = str_replace(',', '.', $val);
                } elseif (strpos($val, ',') !== false) {
                    $val = str_replace(',', '.', $val);
                }
                return (float)$val;
            };

            $hrg = $normalize($harga_k2[$i] ?? '0');
            $pjk = $normalize($ppn_k_arr[$i] ?? '0');
            $tot = $normalize($hargat_k[$i] ?? '0');

            $gr = $garansi[$i] ?? 'none';
            $bln = (int)($bulan[$i] ?? 0);
            $ns = $noseri[$i] ?? '';

            $stmtStock->bind_param("ssddddssiis", $tanggal, $kode, $qty, $hrg, $pjk, $tot, $userin, $nomor, $id_gudang, $bln, $ns);
            $stmtStock->execute();
            
            // Recalculate stock history for this item
            recalculate_stock_history($conn, $kode);
        }
        $stmtStock->close();
        
        $conn->commit();
        echo "<script>
            alert('Transaksi Penjualan Berhasil disimpan! Nomor: $nomor');
            window.location.href = 'pos.php" . ($id_gudang > 0 ? "?id_gudang=$id_gudang" : "") . "';
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
