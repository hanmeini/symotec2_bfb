<?php
require_once 'config1.php';
require_once 'functions.php';
require_once 'functions_stock.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        $nomor = generateNomorDokumen($conn, 'ORD');
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
        $jenis_penjualan = $_POST['jenis_penjualan'] ?? 'grosir';
        
        $stmt = $conn->prepare("INSERT INTO penjualanHO1 (tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, userinv, po) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssddddss", $tanggal, $nomor, $cust, $diskon, $dpp, $ppn, $total_harga, $userinv, $po);
        $stmt->execute();
        $stmt->close();
        
        // Menghapus blok auto-lunas pembayaranho1 agar sesuai dengan alur ATK (Pelunasan diproses manual)

        
        // Kurangi Stock beserta Harga (Akuntansi)
        $userin = $_SESSION['username'] ?? 'system';
        $stmtStock = $conn->prepare("INSERT INTO stock (tanggal_transaksi, J, cus, kodeb, jumlah_k, harga_k, ppn_k, hargat_k, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Simpan ke transaksiHO1 (mengikuti ledger ATK HO)
        $stmtTransaksi = $conn->prepare("INSERT INTO transaksiHO1 (tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_k, harga_k, ppn_k, hargat_k, user, sj, id_gudang, dpp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
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

            $stmtStock->bind_param("ssssddddssiis", $tanggal, $nomor, $cust, $kode, $qty, $hrg, $pjk, $tot, $userin, $nomor, $id_gudang, $bln, $ns);
            $stmtStock->execute();
            
            // Ambil HPP (harga beli) untuk mencatat di dpp transaksiHO1
            $hpp_item = 0;
            $stmt_hpp = $conn->prepare("SELECT harga_m FROM b WHERE kode_b = ? LIMIT 1");
            $stmt_hpp->bind_param("s", $kode);
            $stmt_hpp->execute();
            $res_hpp = $stmt_hpp->get_result();
            if($row_hpp = $res_hpp->fetch_assoc()){
                $hpp_item = $qty * (float)$row_hpp['harga_m'];
                $total_hpp += $hpp_item;
            }
            $stmt_hpp->close();
            
            // Simpan ke transaksiHO1
            $stmtTransaksi->bind_param("sssssddddssid", $tanggal, $nomor, $cust, $kode, $nama, $qty, $hrg, $pjk, $tot, $userin, $nomor, $id_gudang, $hpp_item);
            $stmtTransaksi->execute();
            
            // Recalculate stock history for this item
            recalculate_stock_history($conn, $kode);
        }
        $stmtStock->close();
        $stmtTransaksi->close();
        
        // ===================================
        // Jurnal Akuntansi (Piutang, Penjualan, PPN, HPP, Persediaan) 
        // TELAH DIPINDAHKAN ke proses Verifikasi Order (verifikasi_order.php)
        // Sehingga pesanan (ORD) tidak akan masuk buku besar sebelum diverifikasi.
        
        $conn->commit();
        
        // ===================================
        // SINKRONISASI BFBS (Khusus Retail)
        // ===================================
        if ($jenis_penjualan === 'retail') {
            try {
                $conn_bfbs = new mysqli('localhost', 'root', '', 'symotec2_bfbs');
                if ($conn_bfbs->connect_error) {
                    throw new Exception("Koneksi BFBS gagal: " . $conn_bfbs->connect_error);
                }
                $conn_bfbs->begin_transaction();
                
                $total_dpp_bfbs = 0;
                $total_ppn_bfbs = 0;
                $total_harga_bfbs = 0;
                
                $stmtStock_bfbs = $conn_bfbs->prepare("INSERT INTO stock (tanggal_transaksi, J, cus, kodeb, jumlah_k, harga_k, ppn_k, hargat_k, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtTransaksi_bfbs = $conn_bfbs->prepare("INSERT INTO transaksiHO1 (tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_k, harga_k, ppn_k, hargat_k, user, sj, id_gudang, dpp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($kode_b as $i => $kode) {
                    if (empty($kode)) continue;
                    $qty = (float)($jumlah_k[$i] ?? 0);
                    if ($qty <= 0) continue;
                    
                    $nama = $nama_b[$i] ?? '';
                    
                    // Ambil harga normal dari master barang
                    $stmt_normal = $conn_bfbs->prepare("SELECT harga_b, hargat_b FROM b WHERE kode_b = ? LIMIT 1");
                    $stmt_normal->bind_param("s", $kode);
                    $stmt_normal->execute();
                    $res_normal = $stmt_normal->get_result();
                    
                    $hrg_normal = 0;
                    $tot_normal = 0;
                    $pjk_normal = 0;
                    
                    if($row_normal = $res_normal->fetch_assoc()){
                        $unit_dpp = (float)$row_normal['harga_b'];
                        $unit_hargat = (float)$row_normal['hargat_b'];
                        $hrg_normal = $unit_dpp;
                        $tot_normal = $unit_hargat * $qty;
                        $pjk_normal = ($unit_hargat - $unit_dpp) * $qty;
                    }
                    $stmt_normal->close();
                    
                    $total_dpp_bfbs += ($hrg_normal * $qty);
                    $total_ppn_bfbs += $pjk_normal;
                    $total_harga_bfbs += $tot_normal;
                    
                    $gr = $garansi[$i] ?? 'none';
                    $bln = (int)($bulan[$i] ?? 0);
                    $ns = $noseri[$i] ?? '';
                    
                    $stmtStock_bfbs->bind_param("ssssddddssiis", $tanggal, $nomor, $cust, $kode, $qty, $hrg_normal, $pjk_normal, $tot_normal, $userin, $nomor, $id_gudang, $bln, $ns);
                    $stmtStock_bfbs->execute();
                    
                    // Note: HPP untuk transaksiHO1 retail BFBS diset 0 karena hanya dicatat di BFB
                    $hpp_item_bfbs = 0;
                    $stmtTransaksi_bfbs->bind_param("sssssddddssid", $tanggal, $nomor, $cust, $kode, $nama, $qty, $hrg_normal, $pjk_normal, $tot_normal, $userin, $nomor, $id_gudang, $hpp_item_bfbs);
                    $stmtTransaksi_bfbs->execute();
                    
                    recalculate_stock_history($conn_bfbs, $kode);
                }
                $stmtStock_bfbs->close();
                $stmtTransaksi_bfbs->close();
                
                $total_dpp_bfbs -= $diskon;
                $total_harga_bfbs -= $diskon;
                
                $stmt_bfbs = $conn_bfbs->prepare("INSERT INTO penjualanHO1 (tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, userinv, po) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_bfbs->bind_param("sssddddss", $tanggal, $nomor, $cust, $diskon, $total_dpp_bfbs, $total_ppn_bfbs, $total_harga_bfbs, $userinv, $po);
                $stmt_bfbs->execute();
                $stmt_bfbs->close();
                
                // Sync nomor dokumen
                $conn_bfbs->query("UPDATE master_nomor_dokumen SET nomor_terakhir = nomor_terakhir + 1 WHERE kode_dokumen = 'ORD'");
                
                $conn_bfbs->commit();
                $conn_bfbs->close();
            } catch (Exception $e) {
                if (isset($conn_bfbs) && $conn_bfbs) {
                    $conn_bfbs->rollback();
                    $conn_bfbs->close();
                }
                throw new Exception("Gagal Sinkronisasi BFBS: " . $e->getMessage());
            }
        }
        
        echo "<script>
            alert('Order Penjualan Berhasil dibuat! Harap lakukan verifikasi order sebelum pengiriman/pembayaran.');
            window.location.href = 'pos.php';
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
