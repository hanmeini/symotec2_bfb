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
    $userid = $_SESSION['userid'];
    $stmt_sales = $conn->prepare("SELECT id_gudang FROM master_sales WHERE userid = ?");
    $stmt_sales->bind_param("i", $userid);
    $stmt_sales->execute();
    $res_sales = $stmt_sales->get_result();
    if ($row = $res_sales->fetch_assoc()) {
        $id_gudang = $row['id_gudang'];
    }
    $stmt_sales->close();
}

$success_msg = "";
$error_msg = "";

// PROSES SUBMIT RETUR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_retur'])) {
    $invoice_no = $_POST['invoice_no'];
    $kode_b = $_POST['kode_b'] ?? [];
    $qty_retur = $_POST['qty_retur'] ?? [];
    
    if (empty($invoice_no) || empty($kode_b)) {
        $error_msg = "Data retur tidak lengkap.";
    } else {
        $conn->begin_transaction();
        try {
            // Ambil data asli dari penjualanHO1 untuk mencari diskon dan lain-lain
            // Asumsi: Kita mencari row penjualan yang sesuai dengan Invoice dan Sales/Gudang
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
                throw new Exception("Nomor Invoice tidak ditemukan.");
            }
            
            // Loop item yang diretur
            $total_retur_dpp = 0;
            $total_retur_ppn = 0;
            $total_retur_harga = 0;
            
            foreach ($kode_b as $i => $kb) {
                $retur_jml = (float)($qty_retur[$i] ?? 0);
                if ($retur_jml <= 0) continue;
                
                // Cari data di stock table terkait penjualan ini
                $stmt_stk = $conn->prepare("SELECT stock.*, b.nama_b AS nama FROM stock LEFT JOIN b ON stock.kodeb = b.kode_b WHERE sj = ? AND kodeb = ? AND jumlah_k >= ? LIMIT 1");
                $stmt_stk->bind_param("ssd", $invoice_no, $kb, $retur_jml);
                $stmt_stk->execute();
                $stk_data = $stmt_stk->get_result()->fetch_assoc();
                $stmt_stk->close();
                
                if ($stk_data) {
                    $qty_jual = (float)$stk_data['jumlah_k'];
                    $harga_satuan = $qty_jual > 0 ? (float)$stk_data['harga_k'] / $qty_jual : 0;
                    $ppn_satuan = $qty_jual > 0 ? (float)$stk_data['ppn_k'] / $qty_jual : 0;
                    
                    $dpp_retur = $harga_satuan * $retur_jml;
                    $ppn_total_retur = $ppn_satuan * $retur_jml;
                    
                    $total_retur_dpp += $dpp_retur;
                    $total_retur_ppn += $ppn_total_retur;
                    
                    // Masukkan kembali ke stock sebagai Retur Masuk
                    $tgl = date('Y-m-d H:i:s');
                    $status = 'Retur Masuk'; 
                    $ket_retur = "Retur Inv: " . $invoice_no;
                    
                    $stmt_in = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_m, harga_m, ppn_m, hargat_m, userid, sj, id_gudang, bulan, noseri) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $hargat_m = $dpp_retur + $ppn_total_retur;
                    
                    $stmt_in->bind_param("ssddddssiis", 
                        $tgl, $kb, $retur_jml, $harga_satuan, $ppn_satuan, $hargat_m, 
                        $username1, $ket_retur, $stk_data['id_gudang'], $stk_data['bulan'], $stk_data['noseri']
                    );
                    $stmt_in->execute();
                    $stmt_in->close();
                    
                    // Recalculate stock history for this item
                    recalculate_stock_history($conn, $kb);
                }
            }
            
            // Masukkan record minus ke penjualanHO1 agar Laporan Omzet Terpotong
            if ($total_retur_dpp > 0) {
                $total_retur_harga = $total_retur_dpp + $total_retur_ppn;
                $tgl = date('Y-m-d H:i:s');
                $j_retur = "RETUR-" . $invoice_no . "-" . time(); // Kasih time() biar unik jika diretur berkali-kali
                
                $min_dpp = -$total_retur_dpp;
                $min_ppn = -$total_retur_ppn;
                $min_total = -$total_retur_harga;
                $min_diskon = 0; // Abaikan proporsi diskon untuk simpel, atau bisa dihitung
                
                $stmt_penj = $conn->prepare("INSERT INTO penjualanHO1 (tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, userinv, po) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $po_retur = 'Retur';
                $stmt_penj->bind_param("sssddddss", $tgl, $j_retur, $inv_data['cust'], $min_diskon, $min_dpp, $min_ppn, $min_total, $username1, $po_retur);
                $stmt_penj->execute();
                $stmt_penj->close();
                
                $conn->commit();
                $success_msg = "Retur Penjualan Berhasil Diproses! Stok telah kembali dan Omzet telah disesuaikan.";
            } else {
                throw new Exception("Tidak ada item valid yang diretur.");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_search'])) {
    if (!empty($search_inv)) {
        // Cari di stock table untuk rincian barang dari Invoice ini
        $q_inv = "SELECT stock.*, b.nama_b AS nama FROM stock LEFT JOIN b ON stock.kodeb = b.kode_b WHERE sj = ? AND jumlah_k > 0";
        if ($is_sales) {
            $q_inv .= " AND id_gudang = " . (int)$id_gudang;
        }
        $stmt_s = $conn->prepare($q_inv);
        $stmt_s->bind_param("s", $search_inv);
        $stmt_s->execute();
        $res_s = $stmt_s->get_result();
        while($r = $res_s->fetch_assoc()){
            $items[] = $r;
        }
        $stmt_s->close();
        
        if (empty($items)) {
            $error_msg = "Invoice tidak ditemukan atau bukan dari cabang Anda.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Retur Penjualan (Customer Returns)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #4CAF50; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 15px; background: #4CAF50; color: #fff; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background: #45a049; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background: #4CAF50; color: #fff; }
        .msg { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .home-icon { font-size: 24px; color: maroon; text-decoration: none; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>

<div class="container">
    <a href="home.php" class="home-icon"><i class="fas fa-home"></i> Kembali ke Home</a>
    <h2>Form Retur Penjualan (Customer Return)</h2>

    <?php if ($success_msg): ?>
        <div class="msg success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="msg error"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label>Cari Nomor Invoice / Struk (Contoh: INV...)</label>
            <input type="text" name="search_inv" value="<?= htmlspecialchars($search_inv) ?>" placeholder="Masukkan nomor struk..." required>
        </div>
        <button type="submit" name="btn_search"><i class="fas fa-search"></i> Cari Data Penjualan</button>
    </form>

    <?php if (!empty($items)): ?>
    <hr style="margin:30px 0;">
    <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin memproses retur ini? Stok akan dikembalikan ke gudang dan omzet akan dikurangi.');">
        <input type="hidden" name="invoice_no" value="<?= htmlspecialchars($search_inv) ?>">
        <h3>Rincian Barang di Invoice: <?= htmlspecialchars($search_inv) ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Jumlah Terbeli</th>
                    <th>Harga Satuan (DPP)</th>
                    <th>Jumlah Retur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['kodeb']) ?>
                        <input type="hidden" name="kode_b[]" value="<?= htmlspecialchars($item['kodeb']) ?>">
                    </td>
                    <td><?= htmlspecialchars($item['nama']) ?></td>
                    <td><?= htmlspecialchars($item['jumlah_k']) ?></td>
                    <td><?= number_format($item['harga_k'], 2) ?></td>
                    <td>
                        <input type="number" name="qty_retur[]" min="0" max="<?= $item['jumlah_k'] ?>" value="0" style="width:80px; padding:5px;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top: 20px; text-align: right;">
            <button type="submit" name="proses_retur"><i class="fas fa-undo"></i> Proses Retur Barang</button>
        </div>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
