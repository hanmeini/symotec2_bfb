<?php
require_once 'config1.php';
require_once 'functions.php';
require_once 'functions_stock.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verifikasi') {
    $ord_nomor = $_POST['j'];
    $conn->begin_transaction();
    try {
        // Cek order
        $stmt = $conn->prepare("SELECT * FROM penjualanHO1 WHERE J = ?");
        $stmt->bind_param("s", $ord_nomor);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows === 0) {
            throw new Exception("Order tidak ditemukan.");
        }
        $order = $res->fetch_assoc();
        $stmt->close();

        // Generate INV and SJ
        $nomor_inv = generateNomorDokumen($conn, 'INV');
        $nomor_sj = str_replace('/INV/', '/SJ/', $nomor_inv);
        $tanggal = $order['tanggal_transaksi'];
        $total_harga = $order['jumlah'];
        $dpp = $order['harga'];
        $ppn = $order['ppn'];
        $userinv = $order['userinv'];

        // Update penjualanHO1
        $upd_penj = $conn->prepare("UPDATE penjualanHO1 SET J = ?, inv = ? WHERE J = ?");
        $upd_penj->bind_param("sss", $nomor_inv, $nomor_sj, $ord_nomor);
        $upd_penj->execute();
        $upd_penj->close();

        // Update stock
        $upd_stock = $conn->prepare("UPDATE stock SET J = ?, sj = ? WHERE sj = ?");
        $upd_stock->bind_param("sss", $nomor_inv, $nomor_sj, $ord_nomor);
        $upd_stock->execute();
        $upd_stock->close();

        // Update transaksiHO1
        $upd_trans = $conn->prepare("UPDATE transaksiHO1 SET J = ?, sj = ? WHERE J = ?");
        $upd_trans->bind_param("sss", $nomor_inv, $nomor_sj, $ord_nomor);
        $upd_trans->execute();
        $upd_trans->close();

        // Hitung HPP
        $total_hpp = 0;
        $stmt_stock = $conn->prepare("SELECT kodeb, jumlah_k FROM stock WHERE sj = ?");
        $stmt_stock->bind_param("s", $nomor_sj);
        $stmt_stock->execute();
        $res_stock = $stmt_stock->get_result();
        while($row_stock = $res_stock->fetch_assoc()) {
            $kodeb = $row_stock['kodeb'];
            $qty = $row_stock['jumlah_k'];
            
            $stmt_hpp = $conn->prepare("SELECT harga_m FROM b WHERE kode_b = ? LIMIT 1");
            $stmt_hpp->bind_param("s", $kodeb);
            $stmt_hpp->execute();
            $res_hpp = $stmt_hpp->get_result();
            if($r_hpp = $res_hpp->fetch_assoc()){
                $total_hpp += ($qty * (float)$r_hpp['harga_m']);
            }
            $stmt_hpp->close();
            
            // Recalculate stock history here because now it's official
            recalculate_stock_history($conn, $kodeb);
        }
        $stmt_stock->close();

        // FETCH COA PIUTANG (Pengakuan Awal)
        $coa_user = '11201'; // Selalu Piutang Dagang saat verifikasi (sebelum pelunasan)

        // JURNAL A: PENGAKUAN PENJUALAN
        $ket_jurnal = "Penjualan POS " . $nomor_inv;
        $stmt_jurnal = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit, kode_booking) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // 1. Debet: Piutang Dagang
        $d = $total_harga; $k = 0;
        $coa = $coa_user;
        $stmt_jurnal->bind_param("ssssdds", $nomor_inv, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor_inv);
        $stmt_jurnal->execute();

        // 2. Kredit: Penjualan
        $d = 0; $k = $dpp;
        $coa = '41101';
        $stmt_jurnal->bind_param("ssssdds", $nomor_inv, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor_inv);
        $stmt_jurnal->execute();

        // 3. Kredit: PPN Keluaran
        if ($ppn > 0) {
            $d = 0; $k = $ppn;
            $coa = '21201';
            $stmt_jurnal->bind_param("ssssdds", $nomor_inv, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor_inv);
            $stmt_jurnal->execute();
        }

        // 4. Debet: HPP & Kredit: Persediaan
        if ($total_hpp > 0) {
            $d = $total_hpp; $k = 0;
            $coa = '51101';
            $stmt_jurnal->bind_param("ssssdds", $nomor_inv, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor_inv);
            $stmt_jurnal->execute();

            $d = 0; $k = $total_hpp;
            $coa = '11301';
            $stmt_jurnal->bind_param("ssssdds", $nomor_inv, $tanggal, $ket_jurnal, $coa, $d, $k, $nomor_inv);
            $stmt_jurnal->execute();
        }
        $stmt_jurnal->close();

        $conn->commit();
        $message = "<div class='alert alert-success d-flex justify-content-between align-items-center'>
            <span>Order {$ord_nomor} berhasil diverifikasi menjadi INV: {$nomor_inv} dan SJ: {$nomor_sj}. Jurnal berhasil dibuat.</span>
            <a href='pelunasan.php?J={$nomor_inv}' class='btn btn-sm btn-success fw-bold'>Bayar / Lunas &rarr;</a>
        </div>";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger'>Gagal verifikasi: " . $e->getMessage() . "</div>";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tolak') {
    $ord_nomor = $_POST['j'];
    $conn->begin_transaction();
    try {
        // Cek order
        $stmt = $conn->prepare("SELECT * FROM penjualanHO1 WHERE J = ? AND (inv IS NULL OR inv = '')");
        $stmt->bind_param("s", $ord_nomor);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception("Order tidak ditemukan atau sudah diverifikasi.");
        }
        $stmt->close();

        // Dapatkan semua kode barang yang terlibat untuk recalculate stock
        $stmt_stock = $conn->prepare("SELECT kodeb FROM stock WHERE J = ?");
        $stmt_stock->bind_param("s", $ord_nomor);
        $stmt_stock->execute();
        $res_stock = $stmt_stock->get_result();
        $kodeb_list = [];
        while($row = $res_stock->fetch_assoc()){
            $kodeb_list[] = $row['kodeb'];
        }
        $stmt_stock->close();

        // Hapus dari ketiga tabel operasional
        $conn->query("DELETE FROM penjualanHO1 WHERE J = '" . $conn->real_escape_string($ord_nomor) . "'");
        $conn->query("DELETE FROM transaksiHO1 WHERE J = '" . $conn->real_escape_string($ord_nomor) . "'");
        $conn->query("DELETE FROM stock WHERE J = '" . $conn->real_escape_string($ord_nomor) . "'");

        // Recalculate stok untuk barang yang terlibat
        foreach($kodeb_list as $kb) {
            recalculate_stock_history($conn, $kb);
        }

        $conn->commit();
        $message = "<div class='alert alert-warning'>Order {$ord_nomor} berhasil ditolak dan dihapus. Stok telah dikembalikan.</div>";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger'>Gagal menolak order: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Verifikasi Order</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Verifikasi Order Penjualan</h2>
        <a href="home.php" class="btn btn-secondary">Kembali</a>
    </div>

    <?= $message ?>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>No Order</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th class="text-end">Total Belanja</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $modals_html = ""; // Variabel untuk menyimpan HTML modal
                    $result = $conn->query("SELECT * FROM penjualanHO1 WHERE J LIKE '%ORD%' AND (inv IS NULL OR inv = '') ORDER BY id_transaksi DESC");
                    if ($result->num_rows > 0) {
                        $row_idx = 0;
                        while ($row = $result->fetch_assoc()) {
                            $row_idx++;
                            $j_val = htmlspecialchars($row['j']);
                            
                            echo "<tr>
                                <td><strong>{$j_val}</strong></td>
                                <td>{$row['tanggal_transaksi']}</td>
                                <td>{$row['cust']}</td>
                                <td class='text-end'>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td>
                                <td class='text-center'>
                                    <div class='d-flex justify-content-center gap-2'>
                                        <button type='button' class='btn btn-info btn-sm text-white' data-bs-toggle='modal' data-bs-target='#modal_{$row_idx}'>Lihat Detail</button>
                                        <form method='POST' onsubmit=\"return confirm('Yakin ingin memverifikasi order ini? Aksi ini akan men-generate nomor INV, SJ, dan Jurnal Akuntansi.');\">
                                            <input type='hidden' name='action' value='verifikasi'>
                                            <input type='hidden' name='j' value='{$j_val}'>
                                            <button type='submit' class='btn btn-success btn-sm'>Verifikasi & Generate</button>
                                        </form>
                                        <form method='POST' onsubmit=\"return confirm('Yakin ingin MENOLAK order ini? Order akan dihapus dan stok akan dikembalikan.');\">
                                            <input type='hidden' name='action' value='tolak'>
                                            <input type='hidden' name='j' value='{$j_val}'>
                                            <button type='submit' class='btn btn-danger btn-sm'>Tolak Order</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>";
                            
                            // Build Modal HTML
                            $modals_html .= "
                            <div class='modal fade' id='modal_{$row_idx}' tabindex='-1' aria-hidden='true'>
                                <div class='modal-dialog modal-lg'>
                                    <div class='modal-content'>
                                        <div class='modal-header bg-dark text-white'>
                                            <h5 class='modal-title'>Rincian Order: {$j_val}</h5>
                                            <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal' aria-label='Close'></button>
                                        </div>
                                        <div class='modal-body'>
                                            <table class='table table-sm table-bordered mb-0'>
                                                <thead class='table-light'>
                                                    <tr>
                                                        <th>Kode</th>
                                                        <th>Nama Barang</th>
                                                        <th class='text-center'>Qty</th>
                                                        <th class='text-end'>Harga Jual (Satuan)</th>
                                                        <th class='text-end'>Diskon Order</th>
                                                        <th class='text-end'>Subtotal</th>
                                                    </tr>
                                                </thead>
                                                <tbody>";
                            // Ambil dari transaksiHO1
                            $stmt_det = $conn->prepare("SELECT kode_b, nama_b, jumlah_k, harga_k, hargat_k FROM transaksiHO1 WHERE J = ?");
                            $stmt_det->bind_param("s", $j_val);
                            $stmt_det->execute();
                            $res_det = $stmt_det->get_result();
                            while($rdet = $res_det->fetch_assoc()){
                                $modals_html .= "<tr>
                                        <td>{$rdet['kode_b']}</td>
                                        <td>{$rdet['nama_b']}</td>
                                        <td class='text-center'>" . (float)$rdet['jumlah_k'] . "</td>
                                        <td class='text-end'>Rp " . number_format($rdet['harga_k'], 0, ',', '.') . "</td>
                                        <td class='text-end'>Rp " . number_format($row['diskon'], 0, ',', '.') . "</td>
                                        <td class='text-end fw-bold'>Rp " . number_format($rdet['hargat_k'], 0, ',', '.') . "</td>
                                      </tr>";
                            }
                            $stmt_det->close();
                            $modals_html .= "      </tbody>
                                            </table>
                                        </div>
                                        <div class='modal-footer d-flex justify-content-between'>
                                            <form method='POST' onsubmit=\"return confirm('Yakin ingin MENOLAK order ini? Order akan dihapus dan stok akan dikembalikan.');\">
                                                <input type='hidden' name='action' value='tolak'>
                                                <input type='hidden' name='j' value='{$j_val}'>
                                                <button type='submit' class='btn btn-danger'>Tolak Order</button>
                                            </form>
                                            <form method='POST' onsubmit=\"return confirm('Yakin ingin memverifikasi order ini? Aksi ini akan men-generate nomor INV, SJ, dan Jurnal Akuntansi.');\">
                                                <input type='hidden' name='action' value='verifikasi'>
                                                <input type='hidden' name='j' value='{$j_val}'>
                                                <button type='submit' class='btn btn-success'>Verifikasi & Generate</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center py-4'>Tidak ada order yang menunggu verifikasi.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Cetak semua modal di luar tabel (Valid HTML) -->
<?= isset($modals_html) ? $modals_html : '' ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
