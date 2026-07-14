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
        
        // --- FETCH COA USER ---
        $coa_user = '11201'; // Default: Piutang Dagang
        $stmt_coa = $conn->prepare("SELECT coa FROM me WHERE username = ?");
        $stmt_coa->bind_param("s", $userinv);
        $stmt_coa->execute();
        $res_coa = $stmt_coa->get_result();
        if ($row_coa = $res_coa->fetch_assoc()) {
            if (!empty($row_coa['coa'])) {
                $coa_user = $row_coa['coa'];
            }
        }
        $stmt_coa->close();
        
        $stmt = $conn->prepare("INSERT INTO penjualanHO1 (tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, userinv, po) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddddss", $tanggal, $nomor, $cust, $diskon, $dpp, $ppn, $total_harga, $userinv, $po);
        $stmt->execute();
        $stmt->close();
        
        // Menghapus blok auto-lunas pembayaranho1 agar sesuai dengan alur ATK (Pelunasan diproses manual)

        
        // Kurangi Stock beserta Harga (Akuntansi)
        $userin = $_SESSION['username'] ?? 'system';
        $stmtStock = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_k, harga_k, ppn_k, hargat_k, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Simpan ke transaksiHO1 (mengikuti ledger ATK HO)
        $stmtTransaksi = $conn->prepare("INSERT INTO transaksiHO1 (tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_k, harga_k, ppn_k, hargat_k, user, sj, id_gudang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $total_hpp = 0;

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
            
            // Simpan ke transaksiHO1
            $stmtTransaksi->bind_param("sssssddddssi", $tanggal, $nomor, $cust, $kode, $nama, $qty, $hrg, $pjk, $tot, $userin, $nomor, $id_gudang);
            $stmtTransaksi->execute();
            
            // Calculate HPP
            $stmt_hpp = $conn->prepare("SELECT harga_m FROM b WHERE kode_b = ? LIMIT 1");
            $stmt_hpp->bind_param("s", $kode);
            $stmt_hpp->execute();
            $res_hpp = $stmt_hpp->get_result();
            if($row_hpp = $res_hpp->fetch_assoc()){
                $total_hpp += ($qty * (float)$row_hpp['harga_m']);
            }
            $stmt_hpp->close();
            
            // Recalculate stock history for this item
            recalculate_stock_history($conn, $kode);
        }
        $stmtStock->close();
        $stmtTransaksi->close();
        
        // ===================================
        // JURNAL A: PENGAKUAN PENJUALAN
        // ===================================
        $ket_jurnal = "Penjualan POS " . $nomor;
        
        $stmt_jurnal = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit, kode_booking) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // 1. Debet: Piutang Dagang (Sesuai COA Sales)
        $d = $total_harga; $k = 0;
        $coa = $coa_user;
        $stmt_jurnal->bind_param("ssssdds", $nomor, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor);
        $stmt_jurnal->execute();

        // 2. Kredit: 41101 Penjualan
        $d = 0; $k = $dpp;
        $coa = '41101';
        $stmt_jurnal->bind_param("ssssdds", $nomor, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor);
        $stmt_jurnal->execute();

        // 3. Kredit: 21201 PPN Keluaran
        if ($ppn > 0) {
            $d = 0; $k = $ppn;
            $coa = '21201';
            $stmt_jurnal->bind_param("ssssdds", $nomor, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor);
            $stmt_jurnal->execute();
        }

        // 4. Debet: 51101 HPP & Kredit: 11301 Persediaan
        if ($total_hpp > 0) {
            $d = $total_hpp; $k = 0;
            $coa = '51101';
            $stmt_jurnal->bind_param("ssssdds", $nomor, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor);
            $stmt_jurnal->execute();

            $d = 0; $k = $total_hpp;
            $coa = '11301';
            $stmt_jurnal->bind_param("ssssdds", $nomor, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor);
            $stmt_jurnal->execute();
        }
        $stmt_jurnal->close();
        
        $conn->commit();
        echo "<script>
            alert('Transaksi Penjualan Berhasil disimpan! Mengarahkan ke Pelunasan...');
            window.location.href = 'pelunasan.php?J=$nomor';
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
