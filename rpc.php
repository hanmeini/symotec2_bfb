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

$user = $_SESSION['username'] ?? 'system';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_rpc') {
    if (isset($_POST['ids']) && is_array($_POST['ids']) && count($_POST['ids']) > 0) {
        $ids = array_map('intval', $_POST['ids']);
        $ids_str = implode(',', $ids);

        $conn->begin_transaction();
        try {
            // Generate Nomor RPC
            $no_rpc = generateNomorDokumen($conn, 'RPC');
            $tanggal = date('Y-m-d H:i:s');
            
            // Simpan ke header
            $stmt = $conn->prepare("INSERT INTO rpc_header (no_rpc, tanggal_rpc, user_pembuat) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $no_rpc, $tanggal, $user);
            $stmt->execute();
            $stmt->close();
            
            // Update penjualanho1 (Tandai SJ sudah masuk RPC ini)
            $conn->query("UPDATE penjualanho1 SET no_rpc = '$no_rpc' WHERE id_transaksi IN ($ids_str)");
            
            // Ambil semua item (kodeb) dari SJ yang baru saja di-update untuk hitung ulang Stock Gudang (sg)
            $q_items = "SELECT DISTINCT s.kodeb 
                        FROM stock s 
                        JOIN penjualanho1 p ON s.sj = p.inv 
                        WHERE p.id_transaksi IN ($ids_str)";
            $res_items = $conn->query($q_items);
            if ($res_items) {
                while($row_item = $res_items->fetch_assoc()) {
                    recalculate_stock_history($conn, $row_item['kodeb']);
                }
            }
            
            $conn->commit();
            header("Location: rpc.php?print=" . urlencode($no_rpc));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>Gagal membuat RPC: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Pilih minimal 1 Surat Jalan untuk direkap.</div>";
    }
}

$print_mode = false;
$no_rpc_print = '';
$rekap_items = [];
$list_sj = [];

if (isset($_GET['print'])) {
    $print_mode = true;
    $no_rpc_print = $_GET['print'];
    
    // Ambil daftar SJ
    $res_sj = $conn->query("SELECT inv FROM penjualanho1 WHERE no_rpc = '" . $conn->real_escape_string($no_rpc_print) . "'");
    while ($row = $res_sj->fetch_assoc()) {
        $list_sj[] = $row['inv'];
    }
    
    if (count($list_sj) > 0) {
        // Ambil rekap total qty dari tabel stock
        $in_sj = "'" . implode("','", array_map([$conn, 'real_escape_string'], $list_sj)) . "'";
        
        $q_rekap = "
            SELECT s.kodeb, b.nama_b, SUM(s.jumlah_k) as total_qty 
            FROM stock s 
            LEFT JOIN b ON s.kodeb = b.kode_b 
            WHERE s.sj IN ($in_sj) 
            GROUP BY s.kodeb, b.nama_b
            ORDER BY b.nama_b ASC
        ";
        $res_rekap = $conn->query($q_rekap);
        while ($r = $res_rekap->fetch_assoc()) {
            $rekap_items[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RPC - Rekap Packing Cetak</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
<?php if ($print_mode): ?>
<style>
    body { background: #fff; padding: 20px; font-size: 14px; }
    .print-header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
    .sj-list { font-size: 12px; margin-bottom: 15px; }
    @media print { .no-print { display: none !important; } }
</style>
<?php endif; ?>
</head>
<body class="<?= $print_mode ? 'bg-white' : 'bg-light' ?>">

<?php if ($print_mode): ?>

<div class="container">
    <div class="no-print mb-3">
        <button onclick="window.print()" class="btn btn-primary">Cetak Sekarang</button>
        <a href="rpc.php" class="btn btn-secondary">Kembali</a>
    </div>
    
    <div class="print-header">
        <h2>REKAP MUAT BARANG (RPC)</h2>
        <h4>No: <?= htmlspecialchars($no_rpc_print) ?></h4>
    </div>
    
    <div class="sj-list">
        <strong>Terdapat <?= count($list_sj) ?> Surat Jalan yang direkap:</strong><br>
        <?= implode(", ", $list_sj) ?>
    </div>
    
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="20%">Kode Barang</th>
                <th width="45%">Nama Barang & Spek</th>
                <th width="30%" class="text-center">Total Kuantitas Muat (Pcs)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($rekap_items) > 0): ?>
                <?php $no=1; foreach($rekap_items as $item): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($item['kodeb'] ?? '') ?></td>
                    <td>
                        <strong><?= htmlspecialchars($item['nama_b'] ?? '') ?></strong>
                    </td>
                    <td class="text-center" style="font-size: 18px;"><strong><?= number_format($item['total_qty'] ?? 0, 0, ',', '.') ?></strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">Tidak ada barang untuk SJ ini.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="row mt-5">
        <div class="col-4 text-center">
            <p>Admin / Pembuat</p><br><br><br>
            <p>( <?= htmlspecialchars($user) ?> )</p>
        </div>
        <div class="col-4 text-center">
            <p>Checker Gudang</p><br><br><br>
            <p>( ........................ )</p>
        </div>
        <div class="col-4 text-center">
            <p>Pengirim (Supir)</p><br><br><br>
            <p>( ........................ )</p>
        </div>
    </div>
</div>

<?php else: ?>

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Kelola RPC (Rekap Packing Cetak)</h2>
        <a href="home.php" class="btn btn-secondary">Kembali</a>
    </div>

    <?= $message ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <strong>Daftar Surat Jalan (SJ) Siap Muat</strong>
        </div>
        <div class="card-body p-0">
            <form method="POST">
                <input type="hidden" name="action" value="generate_rpc">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th width="5%" class="text-center">
                                <input type="checkbox" id="checkAll">
                            </th>
                            <th>No Surat Jalan</th>
                            <th>No Order Asli</th>
                            <th>Tanggal</th>
                            <th>Customer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM penjualanho1 WHERE inv LIKE '%SJ%' AND (no_rpc IS NULL OR no_rpc = '') ORDER BY id_transaksi DESC");
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                    <td class='text-center'>
                                        <input type='checkbox' name='ids[]' value='{$row['id_transaksi']}' class='checkItem'>
                                    </td>
                                    <td><strong>{$row['inv']}</strong></td>
                                    <td>{$row['j']}</td>
                                    <td>{$row['tanggal_transaksi']}</td>
                                    <td>{$row['cust']}</td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-4'>Semua SJ sudah di-RPC atau belum ada SJ baru.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <div class="p-3 bg-white border-top text-end">
                    <button type="submit" class="btn btn-success"><i class="fas fa-print me-1"></i> Generate & Cetak RPC</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('checkAll').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.checkItem');
    for (var checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});
</script>

<?php endif; ?>
</body>
</html>
