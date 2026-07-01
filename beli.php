<?php
require_once 'config1.php';

// Ambil nilai 'J' dari URL
$J = isset($_GET['Jb']) ? $_GET['Jb'] : null;

// Cari header dari pembelianHO1 untuk dapatkan sj dan tanggal
$sqlHeader = "SELECT tanggal_transaksi, sj, sup FROM pembelianHO1 WHERE j = ?";
$stmtH = $conn->prepare($sqlHeader);
$stmtH->bind_param("s", $J);
$stmtH->execute();
$stmtH->store_result();

$items = [];
$tanggal_transaksi = '';
$sj = '';
$cus = '';

if ($stmtH->num_rows > 0) {
    $stmtH->bind_result($tanggal_transaksi, $sj, $cus);
    $stmtH->fetch();
    // Cari detail item di transaksiHO1 (Karena sekarang tersentralisasi)
    $sqlT = "SELECT kode_b, nama_b, jumlah_m, user, cabang FROM transaksiHO1 WHERE J = ?";
    $stmtT = $conn->prepare($sqlT);
    $stmtT->bind_param("s", $J);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    while ($row = $resT->fetch_assoc()) {
        $items[] = $row;
    }
    $stmtT->close();
}
$stmtH->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Penerimaan Barang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; }
        h1 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:hover { background-color: #f1f1f1; }
        .no-data { text-align: center; color: red; font-size: 18px; margin-top: 50px; }
        .button-container { margin-top: 20px; text-align: center; }
        .button { background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; font-size: 16px; margin: 4px 2px; cursor: pointer; border: none; border-radius: 5px; transition: 0.3s; }
        .button:hover { background-color: #45a049; }
        .home-icon1 { position: absolute; left: 0; top: 0; padding-left: 10px; color: maroon; font-size: 24px; }
        .left-icon { position: absolute; right: 0; top: 0; padding-right: 10px; color: maroon; font-size: 24px; }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="masuk.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<?php if (count($items) > 0): ?>
    <h1>Bukti Penerimaan Barang</h1>
    <p><strong>Nomor Dokumen:</strong> <?php echo htmlspecialchars($J); ?></p>
    <p><strong>Tanggal Transaksi:</strong> <?php echo htmlspecialchars($tanggal_transaksi); ?></p>
    <p><strong>Surat Jalan Supplier:</strong> <?php echo htmlspecialchars($sj); ?></p>
    <p><strong>Kode Supplier:</strong> <?php echo htmlspecialchars($cus); ?></p>
    <p><strong>User Penerima:</strong> <?php echo htmlspecialchars($items[0]['user'] ?? ''); ?></p>
    
    <table>
        <tr>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>Jumlah Masuk</th>
        </tr>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars($item['kode_b']); ?></td>
            <td><?php echo htmlspecialchars($item['nama_b']); ?></td>
            <td><?php echo htmlspecialchars($item['jumlah_m']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="button-container">
        <button class="button" onclick="window.print();"><i class="fas fa-print"></i> Print</button>
        <a href="masuk.php" class="button"><i class="fa-solid fa-plus"></i> Input Baru</a>
    </div>

<?php else: ?>
    <div class="no-data">
        <i class="fas fa-exclamation-triangle" style="font-size: 40px; margin-bottom: 15px; color: #ff9800;"></i>
        <p>Tidak ada transaksi yang ditemukan untuk kode: <strong><?php echo htmlspecialchars($J); ?></strong></p>
        <p>Atau transaksi tidak memiliki rincian barang.</p>
        <a href="masuk.php" class="button" style="margin-top:20px;">Kembali</a>
    </div>
<?php endif; ?>

</div>
</body>
</html>
