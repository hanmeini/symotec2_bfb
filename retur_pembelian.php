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
            $stmt_inv = $conn->prepare("SELECT * FROM pembelianho1 WHERE inv = ? OR j = ? OR sj = ? LIMIT 1");
            $stmt_inv->bind_param("sss", $invoice_no, $invoice_no, $invoice_no);
            $stmt_inv->execute();
            $inv_data = $stmt_inv->get_result()->fetch_assoc();
            $stmt_inv->close();

            if (!$inv_data) {
                throw new Exception("Nomor Invoice Pembelian tidak ditemukan.");
            }
            $actual_j = $inv_data['j'];
            
            $total_retur_dpp = 0;
            $total_retur_ppn = 0;
            $total_retur_hpp = 0;
            $total_retur_harga = 0;
            
            date_default_timezone_set('Asia/Jakarta');
            $tgl = date('Y-m-d H:i:s');
            require_once 'functions.php';
            $kode_booking_titipan = generateNomorDokumen($conn, 'RTPB');
            $j_retur = $kode_booking_titipan;
            
            foreach ($kode_b_arr as $i => $kb) {
                $retur_jml = (float)($qty_retur_arr[$i] ?? 0);
                $s_id = (int)($stock_ids[$i] ?? 0);
                
                if ($retur_jml <= 0) continue;
                
                // Ambil data asli dari stock berdasarkan ID
                $stmt_stk = $conn->prepare("SELECT * FROM stock WHERE ids = ? AND kodeb = ? LIMIT 1");
                $stmt_stk->bind_param("is", $s_id, $kb);
                $stmt_stk->execute();
                $stk_data = $stmt_stk->get_result()->fetch_assoc();
                $stmt_stk->close();
                
                if (!$stk_data) {
                    throw new Exception("Data barang $kb tidak valid di invoice ini.");
                }
                
                $qty_beli = (float)$stk_data['jumlah_m'];
                
                // Cari total yang sudah diretur dari invoice ini sebelumnya
                $ket_ret = "Retur Pembelian Inv: " . $actual_j;
                $stmt_ret = $conn->prepare("SELECT SUM(jumlah_k) AS tot_retur FROM stock WHERE sj = ? AND kodeb = ?");
                $stmt_ret->bind_param("ss", $ket_ret, $kb);
                $stmt_ret->execute();
                $row_ret = $stmt_ret->get_result()->fetch_assoc();
                $sudah_retur = (float)$row_ret['tot_retur'];
                $stmt_ret->close();
                
                $max_bisa_retur = $qty_beli - $sudah_retur;
                
                if ($retur_jml > $max_bisa_retur) {
                    throw new Exception("Jumlah retur untuk barang $kb melebihi sisa yang bisa diretur (Max: $max_bisa_retur).");
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
                $ket_retur = "Retur Pembelian Inv: " . $actual_j;
                $hargat_k = $dpp_retur + $ppn_retur;
                
                $stmt_in = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_k, harga_k, ppn_k, hargat_k, hpp, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_in->bind_param("ssdddddssiis", 
                    $tgl, $kb, $retur_jml, $dpp_retur, $ppn_retur, $hargat_k, $hpp_satuan, 
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
                // Ambil ID supplier untuk tabel titipanap
                $qsup = $conn->prepare("SELECT id FROM sup WHERE kode = ? LIMIT 1");
                $qsup->bind_param("s", $inv_data['sup']);
                $qsup->execute();
                $rsup = $qsup->get_result()->fetch_assoc();
                $sup_id = $rsup ? $rsup['id'] : 0;
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
                
                // 11203 (Titipan Supplier) - Debet (Mengurangi Hutang / Piutang)
                $coa_titip = "11203"; $nama_titip = "Titipan Supplier"; $d_titip = $total_retur_harga; $k_titip = 0;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_titip, $d_titip, $k_titip, $nama_titip);
                $stmt_jur->execute();
                
                // 12102 (PPN Masukan) - Kredit
                if ($total_retur_ppn > 0) {
                    $coa_ppn = "12102"; $nama_ppn = "PPN Masukan"; $d_ppn = 0; $k_ppn = $total_retur_ppn;
                    $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_ppn, $d_ppn, $k_ppn, $nama_ppn);
                    $stmt_jur->execute();
                }

                // 11301 (Persediaan Barang Dagang) - Kredit
                $coa_inv = "11301"; $nama_inv = "Persediaan Barang Dagang"; $d_inv = 0; $k_inv = $total_retur_dpp;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_inv, $d_inv, $k_inv, $nama_inv);
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
        // Cek Invoice di pembelianho1 (Bisa mencari lewat Nomor Supplier ataupun J internal)
        $q_cek = "SELECT sup, j, inv, sj FROM pembelianho1 WHERE inv = ? OR j = ? LIMIT 1";
        $stmt_c = $conn->prepare($q_cek);
        $stmt_c->bind_param("ss", $search_inv, $search_inv);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($row_c = $res_c->fetch_assoc()) {
            $supplier_name = $row_c['sup'];
            $actual_j = $row_c['j'];
            $actual_inv = $row_c['inv'];
            $actual_sj = $row_c['sj'];
            
            // Cari di stock table (pembelian = jumlah_m > 0)
            // Stock MKB bisa menaruh referensi di kolom sj, inv, atau J
            $q_inv = "SELECT stock.ids, stock.kodeb, stock.jumlah_m, stock.harga_m, stock.ppn_m, stock.hpp, stock.r, b.nama_b AS nama 
                      FROM stock 
                      LEFT JOIN b ON stock.kodeb = b.kode_b 
                      WHERE (stock.sj = ? OR stock.inv = ? OR stock.j = ?) AND stock.jumlah_m > 0 AND (stock.id_gudang = 0 OR stock.id_gudang IS NULL)";
            
            $stmt_s = $conn->prepare($q_inv);
            $stmt_s->bind_param("sss", $actual_sj, $actual_inv, $actual_j);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result();
            while($r = $res_s->fetch_assoc()){
                // Cari total yang sudah diretur dari invoice ini
                $ket_ret = "Retur Pembelian Inv: " . $actual_j;
                $stmt_ret = $conn->prepare("SELECT SUM(jumlah_k) AS tot_retur FROM stock WHERE sj = ? AND kodeb = ?");
                $stmt_ret->bind_param("ss", $ket_ret, $r['kodeb']);
                $stmt_ret->execute();
                $row_ret = $stmt_ret->get_result()->fetch_assoc();
                $sudah_retur = (float)$row_ret['tot_retur'];
                $stmt_ret->close();
                
                $sisa_bisa_retur = (float)$r['jumlah_m'] - $sudah_retur;
                
                if ($sisa_bisa_retur > 0) {
                    $r['sisa_bisa_retur'] = $sisa_bisa_retur;
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
                    <th>Harga Satuan</th>
                    <th>PPN Satuan</th>
                    <th>Qty Beli</th>
                    <th>Sudah Retur</th>
                    <th>Max Bisa Retur</th>
                    <th style="width: 120px;">Qty Retur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $qty = (float)$item['jumlah_m'];
                    $sisa_bisa_retur = (float)$item['sisa_bisa_retur'];
                    $sudah_retur = $qty - $sisa_bisa_retur;
                    
                    $harga_sat = $qty > 0 ? (float)$item['harga_m'] / $qty : 0;
                    $ppn_sat = $qty > 0 ? (float)$item['ppn_m'] / $qty : 0;
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['kodeb']) ?>
                        <input type="hidden" name="kode_b[]" value="<?= htmlspecialchars($item['kodeb']) ?>">
                        <input type="hidden" name="stock_ids[]" value="<?= htmlspecialchars($item['ids']) ?>">
                    </td>
                    <td><?= htmlspecialchars($item['nama']) ?></td>
                    <td><?= number_format($harga_sat, 2, ',', '.') ?></td>
                    <td><?= number_format($ppn_sat, 2, ',', '.') ?></td>
                    <td><?= number_format($qty, 2, ',', '.') ?></td>
                    <td><?= number_format($sudah_retur, 2, ',', '.') ?></td>
                    <td><?= number_format($sisa_bisa_retur, 2, ',', '.') ?></td>
                    <td>
                        <input type="number" name="qty_retur[]" min="0" max="<?= $sisa_bisa_retur ?>" step="0.01" value="0" style="width: 80px; padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
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
