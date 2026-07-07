<?php
require_once 'config1.php';
require_once 'functions_stock.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

$username1 = $_SESSION['username'];
$location = $_SESSION['location'] ?? 'HO';

// Validasi: RETUR PEMBELIAN HANYA UNTUK GUDANG PUSAT (HO / HO1 / id_gudang = 0)
if ($location !== 'HO' && $location !== 'HO1') {
    die("<div style='padding:20px; color:red; text-align:center;'><h2>Akses Ditolak!</h2><p>Fitur Retur Pembelian hanya bisa diakses oleh Gudang Pusat.</p><a href='home.php'>Kembali ke Home</a></div>");
}
$id_gudang = 0; // Pasti Gudang Pusat

$success_msg = "";
$error_msg = "";

// PROSES SUBMIT RETUR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_retur'])) {
    $invoice_no = $_POST['invoice_no'];
    $sup_name = $_POST['sup_name'];
    $with_ppn = isset($_POST['with_ppn']) ? 1 : 0;
    
    $stock_ids = $_POST['stock_ids'] ?? [];
    $kode_b_arr = $_POST['kode_b'] ?? [];
    $qty_retur_arr = $_POST['qty_retur'] ?? [];
    
    if (empty($invoice_no) || empty($kode_b_arr)) {
        $error_msg = "Data retur tidak lengkap.";
    } else {
        $conn->begin_transaction();
        try {
            // Cek Invoice Pembelian
            $stmt_inv = $conn->prepare("SELECT * FROM pembelianho1 WHERE j = ? LIMIT 1");
            $stmt_inv->bind_param("s", $invoice_no);
            $stmt_inv->execute();
            $inv_data = $stmt_inv->get_result()->fetch_assoc();
            $stmt_inv->close();

            if (!$inv_data) {
                throw new Exception("Nomor Invoice Pembelian tidak ditemukan.");
            }
            
            $total_retur_dpp = 0;
            $total_retur_ppn = 0;
            $total_retur_hpp = 0;
            $total_retur_harga = 0;
            
            $tgl = date('Y-m-d H:i:s');
            $kode_booking_titipan = "RETPEM-" . $invoice_no . "-" . time();
            $j_retur = $kode_booking_titipan;
            
            foreach ($kode_b_arr as $i => $kb) {
                $retur_jml = (float)($qty_retur_arr[$i] ?? 0);
                $s_id = (int)($stock_ids[$i] ?? 0);
                
                if ($retur_jml <= 0) continue;
                
                // Ambil data asli dari stock (pembelian masuk)
                $stmt_stk = $conn->prepare("SELECT * FROM stock WHERE ids = ? AND sj = ? AND kodeb = ? LIMIT 1");
                $stmt_stk->bind_param("iss", $s_id, $invoice_no, $kb);
                $stmt_stk->execute();
                $stk_data = $stmt_stk->get_result()->fetch_assoc();
                $stmt_stk->close();
                
                if (!$stk_data) {
                    throw new Exception("Data barang $kb tidak valid di invoice ini.");
                }
                
                $qty_beli = (float)$stk_data['jumlah_m'];
                $r_sebelum = (float)$stk_data['r'];
                $max_bisa_retur = $qty_beli - $r_sebelum;
                
                if ($retur_jml > $max_bisa_retur) {
                    throw new Exception("Jumlah retur barang $kb melebihi sisa (Max: $max_bisa_retur).");
                }
                
                // Harga Satuan Beli
                $harga_satuan = $qty_beli > 0 ? (float)$stk_data['harga_m'] / $qty_beli : 0;
                $ppn_satuan = $qty_beli > 0 ? (float)$stk_data['ppn_m'] / $qty_beli : 0;
                $hpp_satuan = (float)$stk_data['hpp']; 
                
                $dpp_retur = $harga_satuan * $retur_jml;
                $ppn_retur = $with_ppn ? ($ppn_satuan * $retur_jml) : 0;
                
                $total_retur_dpp += $dpp_retur;
                $total_retur_ppn += $ppn_retur;
                $total_retur_hpp += ($hpp_satuan * $retur_jml);
                
                // 1. Update kolom 'r' dan 's' di record stock aslinya
                // Untuk pembelian: r bertambah, s (sisa) = jumlah_m - r
                $stmt_up = $conn->prepare("UPDATE stock SET r = r + ?, s = jumlah_m - r WHERE ids = ?");
                $stmt_up->bind_param("di", $retur_jml, $s_id);
                $stmt_up->execute();
                $stmt_up->close();
                
                // 2. Insert record Retur Keluar ke stock (barang dikembalikan ke Supplier)
                // Keluar = masuk ke jumlah_k, harga_k, ppn_k
                $status = 'Retur Keluar'; 
                $ket_retur = "Retur Pembelian Inv: " . $invoice_no;
                $hargat_k = $dpp_retur + $ppn_retur;
                
                $stmt_in = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_k, harga_k, ppn_k, hargat_k, hpp, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_in->bind_param("ssdddddssiis", 
                    $tgl, $kb, $retur_jml, $hpp_satuan, $ppn_retur, $hargat_k, $hpp_satuan, 
                    $username1, $ket_retur, $id_gudang, $stk_data['bulan'], $stk_data['noseri']
                );
                $stmt_in->execute();
                $stmt_in->close();
                
                // Recalculate stock history
                recalculate_stock_history($conn, $kb);
            }
            
            if ($total_retur_dpp > 0) {
                $total_retur_harga = $total_retur_dpp + $total_retur_ppn;
                
                // 3. Masukkan record minus ke pembelianho1 agar Hutang Terpotong
                $min_dpp = -$total_retur_dpp;
                $min_ppn = -$total_retur_ppn;
                $min_total = -$total_retur_harga;
                $min_diskon = 0; 
                
                $stmt_pemb = $conn->prepare("INSERT INTO pembelianho1 (tanggal_transaksi, j, sup, diskon, harga_m, ppn_m, jumlah_m, userinv) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_pemb->bind_param("sssdddds", $tgl, $j_retur, $inv_data['sup'], $min_diskon, $min_dpp, $min_ppn, $min_total, $username1);
                $stmt_pemb->execute();
                $stmt_pemb->close();
                
                // 4. Masukkan ke tabel titipanap (Deposit / Piutang Supplier)
                // Cek id supplier (jika ada) dari nama supplier
                $sup_id = 0;
                $qsup = $conn->prepare("SELECT id FROM sup WHERE nama_sup = ? LIMIT 1");
                $qsup->bind_param("s", $inv_data['sup']);
                $qsup->execute();
                $rsup = $qsup->get_result();
                if ($row_s = $rsup->fetch_assoc()) $sup_id = $row_s['id'];
                $qsup->close();

                $stmt_titipan = $conn->prepare("INSERT INTO titipanap (kode_booking, tanggal, nominal, description, cust_id, sup, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $desc_titipan = "Titipan Retur Pembelian dari Inv " . $invoice_no;
                $tgl_only = date('Y-m-d');
                $stmt_titipan->bind_param("ssdssis", $kode_booking_titipan, $tgl_only, $total_retur_harga, $desc_titipan, $inv_data['sup'], $sup_id, $tgl);
                $stmt_titipan->execute();
                $stmt_titipan->close();
                
                // 5. Penjurnalan (Accounting)
                $tgl_jurnal = date('Y-m-d');
                $stmt_jur = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit, account_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $keterangan_jur = "Retur Pembelian " . $invoice_no;
                
                // 21104 (Titipan Supplier) - Debet (Mengurangi Hutang / Piutang)
                $coa_titip = "21104"; $nama_titip = "Titipan Supplier"; $d_titip = $total_retur_harga; $k_titip = 0;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_titip, $d_titip, $k_titip, $nama_titip);
                $stmt_jur->execute();

                // 51201 (Retur Pembelian) - Kredit
                $coa_rp = "51201"; $nama_rp = "Retur Pembelian"; $d_rp = 0; $k_rp = $total_retur_dpp;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_rp, $d_rp, $k_rp, $nama_rp);
                $stmt_jur->execute();
                
                // 12102 (PPN Masukan) - Kredit (Dibatalkan)
                if ($total_retur_ppn > 0) {
                    $coa_ppn = "12102"; $nama_ppn = "PPN Masukan"; $d_ppn = 0; $k_ppn = $total_retur_ppn;
                    $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_ppn, $d_ppn, $k_ppn, $nama_ppn);
                    $stmt_jur->execute();
                }

                // 11301 (Inventory) - Kredit (Barang Keluar)
                $coa_inv = "11301"; $nama_inv = "Inventory"; $d_inv = 0; $k_inv = $total_retur_hpp;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_inv, $d_inv, $k_inv, $nama_inv);
                $stmt_jur->execute();
                
                // 51101 (HPP) - Debet (Untuk menyeimbangkan Inventory keluar jika diperlukan)
                $coa_hpp = "51101"; $nama_hpp = "HPP"; $d_hpp = $total_retur_hpp; $k_hpp = 0;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_hpp, $d_hpp, $k_hpp, $nama_hpp);
                $stmt_jur->execute();
                
                $stmt_jur->close();
                
                $conn->commit();
                $success_msg = "Retur Pembelian Berhasil! Stok keluar, Tagihan Hutang berkurang, Saldo masuk ke Titipan Supplier, & Jurnal tercatat.";
            } else {
                throw new Exception("Tidak ada item valid yang diretur atau nilai retur 0.");
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = "Gagal memproses retur: " . $e->getMessage();
        }
    }
}

// PROSES PENCARIAN INVOICE
$search_inv = $_POST['search_inv'] ?? '';
$items = [];
$supplier_name = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_search'])) {
    if (!empty($search_inv)) {
        // Cek Invoice di pembelianho1
        $q_cek = "SELECT sup, j FROM pembelianho1 WHERE j = ? LIMIT 1";
        $stmt_c = $conn->prepare($q_cek);
        $stmt_c->bind_param("s", $search_inv);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($row_c = $res_c->fetch_assoc()) {
            $supplier_name = $row_c['sup'];
            
            // Cari di stock table (pembelian = jumlah_m > 0)
            $q_inv = "SELECT stock.ids, stock.kodeb, stock.jumlah_m, stock.harga_m, stock.ppn_m, stock.hpp, stock.r, b.nama_b AS nama 
                      FROM stock 
                      LEFT JOIN b ON stock.kodeb = b.kode_b 
                      WHERE sj = ? AND jumlah_m > 0 AND (id_gudang = 0 OR id_gudang IS NULL)";
            
            $stmt_s = $conn->prepare($q_inv);
            $stmt_s->bind_param("s", $search_inv);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result();
            while($r = $res_s->fetch_assoc()){
                if ((float)$r['jumlah_m'] - (float)$r['r'] > 0) {
                    $items[] = $r;
                }
            }
            $stmt_s->close();
            
            if (empty($items)) {
                $error_msg = "Semua item pada invoice pembelian ini sudah diretur secara penuh.";
            }
        } else {
            $error_msg = "Invoice Pembelian tidak ditemukan.";
        }
        $stmt_c->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Pembelian (Pusat)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 950px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid #1976d2; }
        h2 { text-align: center; color: #1976d2; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"] { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; background: #1976d2; color: #fff; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        button:hover { background: #0d47a1; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #e0e0e0; padding: 12px; text-align: center; }
        th { background: #f5f5f5; color: #333; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .home-icon { font-size: 16px; color: #fff; background: #333; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin-bottom: 20px; display: inline-block; }
        .home-icon:hover { background: #000; }
        .sup-info { background: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <a href="home.php" class="home-icon"><i class="fas fa-arrow-left"></i> Kembali</a>
    <h2><i class="fas fa-undo-alt"></i> Form Retur Pembelian (Khusus Gudang Pusat)</h2>

    <?php if ($success_msg): ?>
        <div class="msg success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="msg error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Cari Nomor Invoice Pembelian (Dari Supplier)</label>
            <input type="text" name="search_inv" value="<?= htmlspecialchars($search_inv) ?>" placeholder="Masukkan nomor nota/invoice pembelian..." required>
        </div>
        <button type="submit" name="btn_search"><i class="fas fa-search"></i> Temukan Invoice</button>
    </form>

    <?php if (!empty($items)): ?>
    <hr style="margin:30px 0; border: 0; border-top: 1px solid #eee;">
    <form method="POST" action="" onsubmit="return confirm('Proses retur ini akan memotong stok pusat, memotong tagihan hutang supplier, dan membuat jurnal akuntansi. Lanjutkan?');">
        <input type="hidden" name="invoice_no" value="<?= htmlspecialchars($search_inv) ?>">
        <input type="hidden" name="sup_name" value="<?= htmlspecialchars($supplier_name) ?>">
        
        <div class="sup-info">
            <strong>Supplier:</strong> <?= htmlspecialchars($supplier_name) ?> <br>
            <strong>Invoice:</strong> <?= htmlspecialchars($search_inv) ?>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="font-weight:bold; cursor:pointer;">
                <input type="checkbox" name="with_ppn" value="1" checked> 
                Hitung Retur beserta PPN (Centang jika faktur beli ini kena PPN)
            </label>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Harga Beli Satuan</th>
                    <th>PPN Satuan</th>
                    <th>Qty Beli</th>
                    <th>Max Retur</th>
                    <th style="width: 120px;">Qty Retur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $qty = (float)$item['jumlah_m'];
                    $r_sebelum = (float)$item['r'];
                    $max_retur = $qty - $r_sebelum;
                    
                    $harga_sat = $qty > 0 ? (float)$item['harga_m'] / $qty : 0;
                    $ppn_sat = $qty > 0 ? (float)$item['ppn_m'] / $qty : 0;
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['kodeb']) ?>
                        <input type="hidden" name="stock_ids[]" value="<?= $item['ids'] ?>">
                        <input type="hidden" name="kode_b[]" value="<?= htmlspecialchars($item['kodeb']) ?>">
                    </td>
                    <td><?= htmlspecialchars($item['nama']) ?></td>
                    <td><?= number_format($harga_sat, 2) ?></td>
                    <td><?= number_format($ppn_sat, 2) ?></td>
                    <td><?= $qty ?></td>
                    <td><strong style="color:#1976d2;"><?= $max_retur ?></strong></td>
                    <td>
                        <input type="number" name="qty_retur[]" min="0" max="<?= $max_retur ?>" step="0.01" value="0" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 25px; text-align: right;">
            <button type="submit" name="proses_retur" style="font-size: 16px; padding: 12px 25px;"><i class="fas fa-save"></i> Simpan Retur Pembelian</button>
        </div>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
