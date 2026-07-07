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

// Cek Sales
$is_sales = false;
$id_gudang = 0;
if ($location !== 'HO' && $location !== 'HO1') {
    $is_sales = true;
    $userid = $_SESSION['userid'] ?? 0;
    // jika session ada query ke master sales
    if ($userid > 0) {
        $stmt_sales = $conn->prepare("SELECT id_gudang FROM master_sales WHERE userid = ?");
        $stmt_sales->bind_param("i", $userid);
        $stmt_sales->execute();
        $res_sales = $stmt_sales->get_result();
        if ($row = $res_sales->fetch_assoc()) {
            $id_gudang = $row['id_gudang'];
        }
        $stmt_sales->close();
    }
}

$success_msg = "";
$error_msg = "";

// PROSES SUBMIT RETUR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_retur'])) {
    $invoice_no = $_POST['invoice_no'];
    $cust_name = $_POST['cust_name'];
    $with_ppn = isset($_POST['with_ppn']) ? 1 : 0;
    
    $stock_ids = $_POST['stock_ids'] ?? [];
    $kode_b_arr = $_POST['kode_b'] ?? [];
    $qty_retur_arr = $_POST['qty_retur'] ?? [];
    
    if (empty($invoice_no) || empty($kode_b_arr)) {
        $error_msg = "Data retur tidak lengkap.";
    } else {
        $conn->begin_transaction();
        try {
            // Validasi kepemilikan Invoice (Sales hanya bisa retur miliknya)
            $q_invoice = "SELECT * FROM penjualanHO1 WHERE J = ?";
            if ($is_sales) {
                $q_invoice .= " AND userinv = '" . $conn->real_escape_string($username1) . "'";
            }
            $q_invoice .= " LIMIT 1";
            $stmt_inv = $conn->prepare($q_invoice);
            $stmt_inv->bind_param("s", $invoice_no);
            $stmt_inv->execute();
            $inv_data = $stmt_inv->get_result()->fetch_assoc();
            $stmt_inv->close();

            if (!$inv_data) {
                throw new Exception("Nomor Invoice tidak ditemukan atau Anda tidak memiliki akses untuk meretur Invoice ini.");
            }
            
            $total_retur_dpp = 0;
            $total_retur_ppn = 0;
            $total_retur_hpp = 0;
            $total_retur_harga = 0;
            
            date_default_timezone_set('Asia/Jakarta');
            $tgl = date('Y-m-d H:i:s');
            $kode_booking_titipan = "RETUR-" . str_replace('/', '', $invoice_no) . "-" . time();
            $j_retur = $kode_booking_titipan;
            
            foreach ($kode_b_arr as $i => $kb) {
                $retur_jml = (float)($qty_retur_arr[$i] ?? 0);
                $s_id = (int)($stock_ids[$i] ?? 0);
                
                if ($retur_jml <= 0) continue;
                
                // Ambil data asli dari stock untuk divalidasi dan dihitung
                $stmt_stk = $conn->prepare("SELECT * FROM stock WHERE ids = ? AND kodeb = ? LIMIT 1");
                $stmt_stk->bind_param("is", $s_id, $kb);
                $stmt_stk->execute();
                $stk_data = $stmt_stk->get_result()->fetch_assoc();
                $stmt_stk->close();
                
                if (!$stk_data) {
                    throw new Exception("Data barang $kb tidak valid di invoice ini.");
                }
                
                $qty_jual = (float)$stk_data['jumlah_k'];
                
                // Cari total yang sudah diretur dari invoice ini sebelumnya
                $ket_ret = "Retur Penjualan Inv: " . $invoice_no;
                $stmt_ret = $conn->prepare("SELECT SUM(jumlah_m) AS tot_retur FROM stock WHERE sj = ? AND kodeb = ?");
                $stmt_ret->bind_param("ss", $ket_ret, $kb);
                $stmt_ret->execute();
                $row_ret = $stmt_ret->get_result()->fetch_assoc();
                $sudah_retur = (float)$row_ret['tot_retur'];
                $stmt_ret->close();
                
                $max_bisa_retur = $qty_jual - $sudah_retur;
                
                if ($retur_jml > $max_bisa_retur) {
                    throw new Exception("Jumlah retur untuk barang $kb melebihi sisa yang bisa diretur (Max: $max_bisa_retur).");
                }
                
                // Hitung Proporsi Harga & PPN Satuan dari stock table
                // Jika stock tidak ada harga_k (0), lempar error
                if ((float)$stk_data['harga_k'] <= 0) {
                    throw new Exception("Barang $kb memiliki harga 0 di sistem, tidak bisa memproses retur potong omzet.");
                }
                
                $harga_satuan = $qty_jual > 0 ? (float)$stk_data['harga_k'] / $qty_jual : 0;
                $ppn_satuan = $qty_jual > 0 ? (float)$stk_data['ppn_k'] / $qty_jual : 0;
                $hpp_satuan = (float)$stk_data['hpp']; // hpp sudah satuan
                
                $dpp_retur = $harga_satuan * $retur_jml;
                $ppn_retur = $with_ppn ? ($ppn_satuan * $retur_jml) : 0;
                $hpp_total = $hpp_satuan * $retur_jml;
                
                $total_retur_dpp += $dpp_retur;
                $total_retur_ppn += $ppn_retur;
                $total_retur_hpp += $hpp_total;
                
                // 1. Update kolom 'r' dan 's' di record stock aslinya
                $stmt_up = $conn->prepare("UPDATE stock SET r = r + ?, s = jumlah_k - r WHERE ids = ?");
                $stmt_up->bind_param("di", $retur_jml, $s_id);
                $stmt_up->execute();
                $stmt_up->close();
                
                // 2. Insert record Retur Masuk ke stock
                $status = 'Retur Masuk'; 
                $ket_retur = "Retur Inv: " . $invoice_no;
                $hargat_m = $dpp_retur + $ppn_retur;
                
                $stmt_in = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_m, harga_m, ppn_m, hargat_m, hpp, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_in->bind_param("ssdddddssiis", 
                    $tgl, $kb, $retur_jml, $hpp_satuan, $ppn_retur, $hargat_m, $hpp_satuan, 
                    $username1, $ket_retur, $stk_data['id_gudang'], $stk_data['bulan'], $stk_data['noseri']
                );
                $stmt_in->execute();
                $stmt_in->close();
                
                // Recalculate stock history for this item
                recalculate_stock_history($conn, $kb);
            }
            
            if ($total_retur_dpp > 0) {
                $total_retur_harga = $total_retur_dpp + $total_retur_ppn;
                
                // 3. Masukkan record minus ke penjualanHO1 agar Omzet Terpotong
                $min_dpp = -$total_retur_dpp;
                $min_ppn = -$total_retur_ppn;
                $min_total = -$total_retur_harga;
                $min_diskon = 0; 
                
                $stmt_penj = $conn->prepare("INSERT INTO penjualanHO1 (tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, userinv, po) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $po_retur = 'Retur';
                $stmt_penj->bind_param("sssddddss", $tgl, $j_retur, $inv_data['cust'], $min_diskon, $min_dpp, $min_ppn, $min_total, $username1, $po_retur);
                $stmt_penj->execute();
                $stmt_penj->close();
                
                // 4. Masukkan ke tabel titipan (Deposit Customer)
                $stmt_titipan = $conn->prepare("INSERT INTO titipan (kode_booking, tanggal, nominal, description, cust_id, created_at) VALUES (?, ?, ?, ?, ?, ?)");
                $desc_titipan = "Titipan Retur dari Inv " . $invoice_no;
                $tgl_only = date('Y-m-d');
                $stmt_titipan->bind_param("ssdsss", $kode_booking_titipan, $tgl_only, $total_retur_harga, $desc_titipan, $inv_data['cust'], $tgl);
                $stmt_titipan->execute();
                $stmt_titipan->close();
                
                // 5. Penjurnalan (Accounting)
                $tgl_jurnal = date('Y-m-d');
                $stmt_jur = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit, account_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                // 41201 (Return Penjualan) - Debet
                $keterangan_jur = "Retur Penjualan " . $invoice_no;
                $coa_rp = "41201"; $nama_rp = "Retur Penjualan"; $d_rp = $total_retur_dpp; $k_rp = 0;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_rp, $d_rp, $k_rp, $nama_rp);
                $stmt_jur->execute();
                
                // 21201 (PPN Keluaran) - Debet
                if ($total_retur_ppn > 0) {
                    $coa_ppn = "21201"; $nama_ppn = "PPN Keluaran"; $d_ppn = $total_retur_ppn; $k_ppn = 0;
                    $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_ppn, $d_ppn, $k_ppn, $nama_ppn);
                    $stmt_jur->execute();
                }
                
                // 11201 (Piutang Dagang) - Kredit
                $coa_titip = "11201"; $nama_titip = "Piutang Dagang"; $d_titip = 0; $k_titip = $total_retur_harga;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_titip, $d_titip, $k_titip, $nama_titip);
                $stmt_jur->execute();
                
                // 11301 (Persediaan Barang Dagang) - Debet
                $coa_inv = "11301"; $nama_inv = "Persediaan Barang Dagang"; $d_inv = $total_retur_hpp; $k_inv = 0;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_inv, $d_inv, $k_inv, $nama_inv);
                $stmt_jur->execute();
                
                // 51101 (Harga Pokok Penjualan) - Kredit
                $coa_hpp = "51101"; $nama_hpp = "Harga Pokok Penjualan"; $d_hpp = 0; $k_hpp = $total_retur_hpp;
                $stmt_jur->bind_param("ssssdds", $j_retur, $tgl_jurnal, $keterangan_jur, $coa_hpp, $d_hpp, $k_hpp, $nama_hpp);
                $stmt_jur->execute();
                
                $stmt_jur->close();
                
                $conn->commit();
                $success_msg = "Retur Penjualan Berhasil! Stok kembali, Omzet dikurangi, Saldo masuk ke Titipan Customer, & Jurnal berhasil dicatat.";
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
$customer_name = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_search'])) {
    if (!empty($search_inv)) {
        // Cek Invoice di penjualanHO1
        $q_cek = "SELECT cust, J FROM penjualanHO1 WHERE J = ?";
        if ($is_sales) {
            $q_cek .= " AND userinv = '" . $conn->real_escape_string($username1) . "'";
        }
        $q_cek .= " LIMIT 1";
        $stmt_c = $conn->prepare($q_cek);
        $stmt_c->bind_param("s", $search_inv);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();
        if ($row_c = $res_c->fetch_assoc()) {
            $customer_name = $row_c['cust'];
            
            // Cari di stock table untuk rincian barang dari Invoice ini
            // Ambil juga r (retur) untuk validasi sisa
            $q_inv = "SELECT stock.ids, stock.kodeb, stock.jumlah_k, stock.harga_k, stock.ppn_k, stock.hpp, stock.r, b.nama_b AS nama 
                      FROM stock 
                      LEFT JOIN b ON stock.kodeb = b.kode_b 
                      WHERE sj = ? AND jumlah_k > 0";
            if ($is_sales) {
                $q_inv .= " AND id_gudang = " . (int)$id_gudang;
            }
            $stmt_s = $conn->prepare($q_inv);
            $stmt_s->bind_param("s", $search_inv);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result();
            while($r = $res_s->fetch_assoc()){
                // Cari total yang sudah diretur dari invoice ini sebelumnya
                $ket_ret = "Retur Penjualan Inv: " . $search_inv;
                $stmt_ret = $conn->prepare("SELECT SUM(jumlah_m) AS tot_retur FROM stock WHERE sj = ? AND kodeb = ?");
                $stmt_ret->bind_param("ss", $ket_ret, $r['kodeb']);
                $stmt_ret->execute();
                $row_ret = $stmt_ret->get_result()->fetch_assoc();
                $sudah_retur = (float)$row_ret['tot_retur'];
                $stmt_ret->close();
                
                $sisa_bisa_retur = (float)$r['jumlah_k'] - $sudah_retur;
                
                // Hanya tampilkan jika masih ada sisa yang bisa diretur
                if ($sisa_bisa_retur > 0) {
                    $r['sisa_bisa_retur'] = $sisa_bisa_retur;
                    $items[] = $r;
                }
            }
            $stmt_s->close();
            
            if (empty($items)) {
                $error_msg = "Semua item pada invoice ini sudah diretur secara penuh.";
            }
        } else {
            $error_msg = "Invoice tidak ditemukan atau bukan milik Anda.";
        }
        $stmt_c->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Penjualan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; padding: 20px; }
        .container { max-width: 950px; margin: 0 auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid #d32f2f; }
        h2 { text-align: center; color: #d32f2f; margin-bottom: 25px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input[type="text"] { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; background: #d32f2f; color: #fff; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        button:hover { background: #b71c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #e0e0e0; padding: 12px; text-align: center; }
        th { background: #f5f5f5; color: #333; }
        .msg { padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .home-icon { font-size: 16px; color: #fff; background: #333; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin-bottom: 20px; display: inline-block; }
        .home-icon:hover { background: #000; }
        .cust-info { background: #e3f2fd; border-left: 4px solid #1976d2; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="container">
    <a href="home.php" class="home-icon"><i class="fas fa-arrow-left"></i> Kembali</a>
    <h2><i class="fas fa-undo"></i> Form Retur Penjualan</h2>

    <?php if ($success_msg): ?>
        <div class="msg success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="msg error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Cari Nomor Invoice / Faktur Penjualan</label>
            <input type="text" name="search_inv" value="<?= htmlspecialchars($search_inv) ?>" placeholder="Contoh: 0001/INV/..." required>
        </div>
        <button type="submit" name="btn_search"><i class="fas fa-search"></i> Temukan Invoice</button>
    </form>

    <?php if (!empty($items)): ?>
    <hr style="margin:30px 0; border: 0; border-top: 1px solid #eee;">
    <form method="POST" action="" onsubmit="return confirm('Proses retur ini akan menambah stok, memotong omzet, dan membuat jurnal akuntansi. Lanjutkan?');">
        <input type="hidden" name="invoice_no" value="<?= htmlspecialchars($search_inv) ?>">
        <input type="hidden" name="cust_name" value="<?= htmlspecialchars($customer_name) ?>">
        
        <div class="cust-info">
            <strong>Customer:</strong> <?= htmlspecialchars($customer_name) ?> <br>
            <strong>Invoice:</strong> <?= htmlspecialchars($search_inv) ?>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="font-weight:bold; cursor:pointer;">
                <input type="checkbox" name="with_ppn" value="1" checked> 
                Hitung Retur beserta PPN (Centang jika faktur menggunakan PPN)
            </label>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Harga Satuan (DPP)</th>
                    <th>PPN Satuan</th>
                    <th>Qty Jual</th>
                    <th>Sudah Retur</th>
                    <th>Max Bisa Retur</th>
                    <th style="width: 120px;">Qty Retur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $qty = (float)$item['jumlah_k'];
                    $sisa_bisa_retur = (float)$item['sisa_bisa_retur'];
                    $sudah_retur = $qty - $sisa_bisa_retur;
                    
                    $harga_sat = $qty > 0 ? (float)$item['harga_k'] / $qty : 0;
                    $ppn_sat = $qty > 0 ? (float)$item['ppn_k'] / $qty : 0;
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['kodeb']) ?>
                        <input type="hidden" name="kode_b[]" value="<?= htmlspecialchars($item['kodeb']) ?>">
                        <input type="hidden" name="stock_ids[]" value="<?= $item['ids'] ?>">
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
            <button type="submit" name="proses_retur" style="font-size: 16px; padding: 12px 25px;"><i class="fas fa-save"></i> Simpan Retur & Jurnal</button>
        </div>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
