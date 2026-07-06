<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';

// Proteksi HO / Owner
if (isset($_SESSION['location']) && $_SESSION['location'] !== 'HO' && $_SESSION['bagian'] !== 'owner') {
    echo "<script>alert('Akses Ditolak'); window.location.href='home.php';</script>";
    exit();
}

$sql = "
SELECT 
    j.kode_booking,
    j.journal_number,
    MIN(j.tanggal) as tanggal,
    SUM(IF(j.debet > 0, j.debet, 0)) as tagihan,
    SUM(IF(j.kredit > 0, j.kredit, 0)) as bayar,
    (SUM(IF(j.debet > 0, j.debet, 0)) - SUM(IF(j.kredit > 0, j.kredit, 0))) as sisa,
    DATEDIFF(CURDATE(), MIN(j.tanggal)) AS umur
FROM jurnal j
WHERE j.coa = '11201' 
GROUP BY j.kode_booking, j.journal_number
HAVING sisa > 0
ORDER BY umur DESC
";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>AR dari Piutang Dagang (Buku Besar 11201)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f2f2; margin: 0; padding: 20px; }
        .table-container { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 0 8px rgba(0,0,0,0.1); max-height: 80vh; overflow-y: auto; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { text-align: right; padding: 10px; border: 1px solid #ddd; font-size: 14px; }
        th { background-color: blue; color: white; position: sticky; top: 0; z-index: 2; text-align: center;}
        td:nth-child(2), td:nth-child(3), td:nth-child(4), td:nth-child(5) { text-align: left; }
        tr:hover { background-color: #f1f1f1; }
        h1 { text-align: center; }
        .home-icon i { color: maroon; font-size: 36px; float: left; }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="ar.php" class="home-icon"><i class="fa-solid fa-circle-left"></i></a>
        <h1>AR dari Piutang Dagang (COA 11201)</h1>
        
        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <tr>
                    <th>No</th>
                    <th>Kode Booking</th>
                    <th>No Jurnal / Invoice</th>
                    <th>Tanggal Transaksi</th>
                    <th>Total Tagihan (Debet)</th>
                    <th>Total Bayar (Kredit)</th>
                    <th>Sisa Piutang (Saldo)</th>
                    <th>Umur (Hari)</th>
                </tr>
                <?php 
                $no = 1;
                $tot_tagihan = 0; $tot_bayar = 0; $tot_sisa = 0;
                while($row = $result->fetch_assoc()): 
                    $tot_tagihan += $row['tagihan'];
                    $tot_bayar += $row['bayar'];
                    $tot_sisa += $row['sisa'];
                ?>
                <tr>
                    <td style="text-align: center;"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['kode_booking']) ?></td>
                    <td><?= htmlspecialchars($row['journal_number']) ?></td>
                    <td><?= htmlspecialchars($row['tanggal']) ?></td>
                    <td><?= number_format($row['tagihan'], 2) ?></td>
                    <td><?= number_format($row['bayar'], 2) ?></td>
                    <td style="color: red; font-weight: bold;"><?= number_format($row['sisa'], 2) ?></td>
                    <td style="text-align: center;"><?= $row['umur'] ?></td>
                </tr>
                <?php endwhile; ?>
                <tr style="background-color: #dff0d8; font-weight: bold;">
                    <td colspan="4" style="text-align: center;">TOTAL KESELURUHAN</td>
                    <td><?= number_format($tot_tagihan, 2) ?></td>
                    <td><?= number_format($tot_bayar, 2) ?></td>
                    <td style="color: red;"><?= number_format($tot_sisa, 2) ?></td>
                    <td></td>
                </tr>
            </table>
        <?php else: ?>
            <p style="text-align: center; padding: 20px;">Tidak ada data AR / Piutang bersaldo di buku besar 11201.</p>
        <?php endif; ?>
    </div>
</body>
</html>
