<?php
require_once 'config1.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cek Perbedaan BFB & BFBS</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h3 { color: maroon; }
    </style>
</head>
<body>
    <h2>Log Transaksi Terakhir (Retail)</h2>
    <p>Di bawah ini adalah 5 order terakhir. Perhatikan perbedaan harga antara BFB (Harga Diskon) dan BFBS (Harga Normal).</p>
    
    <div style="display: flex; gap: 20px;">
        <div style="flex: 1;">
            <h3>Database Asli (symotec2_bfb)</h3>
            <table>
                <tr><th>No Order</th><th>Tanggal</th><th>Total Harga (Retail)</th></tr>
                <?php
                $res = $conn->query("SELECT J, tanggal_transaksi, jumlah FROM penjualanHO1 ORDER BY id_transaksi DESC LIMIT 5");
                if($res) {
                    while($row = $res->fetch_assoc()) {
                        echo "<tr><td>{$row['J']}</td><td>{$row['tanggal_transaksi']}</td><td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td></tr>";
                    }
                }
                ?>
            </table>
        </div>
        
        <div style="flex: 1;">
            <h3>Database Laporan Pusat (symotec2_bfbs)</h3>
            <table>
                <tr><th>No Order</th><th>Tanggal</th><th>Total Harga (Normal)</th></tr>
                <?php
                $conn_bfbs = new mysqli('localhost', 'root', '', 'symotec2_bfbs');
                if ($conn_bfbs->connect_error) {
                    echo "<tr><td colspan='3'>Gagal konek ke BFBS</td></tr>";
                } else {
                    $res2 = $conn_bfbs->query("SELECT J, tanggal_transaksi, jumlah FROM penjualanHO1 ORDER BY id_transaksi DESC LIMIT 5");
                    if($res2) {
                        while($row = $res2->fetch_assoc()) {
                            echo "<tr><td>{$row['J']}</td><td>{$row['tanggal_transaksi']}</td><td>Rp " . number_format($row['jumlah'], 0, ',', '.') . "</td></tr>";
                        }
                    }
                    $conn_bfbs->close();
                }
                ?>
            </table>
        </div>
    </div>
    <a href="home.php"><button style="padding: 10px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">Kembali ke Home</button></a>
</body>
</html>
