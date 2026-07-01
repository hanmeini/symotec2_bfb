<?php
require_once 'config1.php';
require_once 'functions_stock.php';

$sj = isset($_GET['sj']) ? $_GET['sj'] : '';

if (empty($sj)) {
    die("SJ tidak ditemukan.");
}

$conn->set_charset("utf8mb4");

// Jika POST Konfirmasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terima_sj'])) {
    try {
        $conn->begin_transaction();

        // 1. Ambil data SJ dari tabel antar
        $stmt_antar = $conn->prepare("SELECT pengirim, penerima FROM antar WHERE sj = ? AND notrim = '' LIMIT 1");
        $stmt_antar->bind_param("s", $sj);
        $stmt_antar->execute();
        $stmt_antar->bind_result($pengirim, $penerima);
        if (!$stmt_antar->fetch()) {
            throw new Exception("SJ sudah diterima atau tidak ditemukan.");
        }
        $stmt_antar->close();

        // 2. Ambil nama gudang
        $nama_pengirim = '';
        $nama_penerima = '';
        $q = $conn->query("SELECT id_gudang, nama_gudang FROM master_gudang");
        while ($r = $q->fetch_assoc()) {
            if ($r['id_gudang'] == $pengirim) $nama_pengirim = $r['nama_gudang'];
            if ($r['id_gudang'] == $penerima) $nama_penerima = $r['nama_gudang'];
        }

        // 3. Ambil data barang yang dikirim (jumlah_k > 0)
        $stmt_items = $conn->prepare("SELECT stock.kodeb, b.nama_b AS nama, b.jenis, stock.jumlah_k, stock.userid FROM stock LEFT JOIN b ON stock.kodeb = b.kode_b WHERE stock.sj = ? AND stock.jumlah_k > 0");
        $stmt_items->bind_param("s", $sj);
        $stmt_items->execute();
        $result_items = $stmt_items->get_result();
        
        $items_to_insert = [];
        while ($row = $result_items->fetch_assoc()) {
            $items_to_insert[] = $row;
        }
        $stmt_items->close();

        if (empty($items_to_insert)) {
            throw new Exception("Tidak ada item yang ditemukan untuk SJ ini.");
        }

        // 4. Masukkan ke stok tujuan (jumlah_m)
        $stmt_in = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_m, userid, sj, id_gudang) VALUES (NOW(), ?, ?, ?, ?, ?)");
        
        foreach ($items_to_insert as $item) {
            $username = $_SESSION['username'] ?? 'system';
            $stmt_in->bind_param("sdssi", 
                $item['kodeb'], 
                $item['jumlah_k'], // jumlah_k dari asal jadi jumlah_m di tujuan
                $username, 
                $sj, 
                $penerima
            );
            $stmt_in->execute();
            
            // Recalculate stock history for this item
            recalculate_stock_history($conn, $item['kodeb']);
        }
        $stmt_in->close();

        // 5. Update tabel antar (notrim)
        $stmt_upd = $conn->prepare("UPDATE antar SET notrim = 'Sudah' WHERE sj = ?");
        $stmt_upd->bind_param("s", $sj);
        $stmt_upd->execute();
        $stmt_upd->close();

        $conn->commit();
        echo "<script>alert('Surat Jalan berhasil diterima! Stok gudang telah ditambahkan.'); window.opener.location.reload(); window.close();</script>";
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = $e->getMessage();
    }
}

// Ambil detail item untuk ditampilkan
$stmt = $conn->prepare("SELECT stock.kodeb, b.nama_b AS nama, stock.jumlah_k FROM stock LEFT JOIN b ON stock.kodeb = b.kode_b WHERE stock.sj = ? AND stock.jumlah_k > 0");
$stmt->bind_param("s", $sj);
$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penerimaan Surat Jalan</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; padding: 20px; }
        h2 { margin-top: 0; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: center; }
        th { background: #28a745; color: white; }
        .btn-green { background: #28a745; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 4px; font-weight: bold; font-size: 14px; margin-top: 20px; }
        .btn-green:hover { background: #218838; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>

    <h2>Detail Penerimaan SJ: <?= htmlspecialchars($sj) ?></h2>

    <?php if(!empty($errorMsg)) echo '<div class="error">'.htmlspecialchars($errorMsg).'</div>'; ?>

    <table>
        <thead>
            <tr>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Qty Masuk</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $ada = false;
            while ($row = $result->fetch_assoc()): 
                $ada = true;
            ?>
            <tr>
                <td><?= htmlspecialchars($row['kodeb']) ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['jumlah_k']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <?php if ($ada): ?>
    <form method="POST">
        <button type="submit" name="terima_sj" class="btn-green">Konfirmasi Terima</button>
    </form>
    <?php else: ?>
    <p>Data barang tidak ditemukan untuk SJ ini.</p>
    <?php endif; ?>

</body>
</html>
