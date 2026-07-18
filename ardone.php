<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['userid'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';

// Proteksi: Hanya HO atau Owner yang boleh mengakses AR ini
if (isset($_SESSION['location']) && $_SESSION['location'] !== 'HO' && $_SESSION['bagian'] !== 'owner') {
    echo "<script>alert('Akses Ditolak: Halaman ini hanya untuk bagian Head Office (HO).'); window.location.href='home.php';</script>";
    exit();
}

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$filter_sql = '';

if ($filter !== '') {
    $escaped_filter = $conn->real_escape_string($filter);
    $filter_sql .= " AND (
        p.J LIKE '%$escaped_filter%'
        OR p.cust LIKE '%$escaped_filter%'
        OR p.userinv LIKE '%$escaped_filter%'
    )";
}

// Mengambil data penjualan kredit yang SUDAH dibayar (bayar > 0) DARI penjualanho1
$sql = "SELECT 
            p.tanggal_transaksi, p.J, p.cust, p.jumlah, p.bayar, p.sisa, p.userinv,
            DATEDIFF(CURDATE(), p.tanggal_transaksi) AS umur
        FROM penjualanho1 p 
        WHERE p.bayar > 0 $filter_sql
        ORDER BY p.tanggal_transaksi DESC";
$result = $conn->query($sql);

if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    echo "<script>alert('Fitur Export Excel akan segera disiapkan.'); window.history.back();</script>";
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Customer Sudah Bayar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #e0f2f1; margin: 0; padding: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; background: #00897b; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; display: inline-block;}
        .btn:hover { background: #00695c; }
        
        .search-box { margin-bottom: 20px; text-align: center; }
        .search-box input[type="text"] { padding: 8px; width: 400px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px;}
        
        table { width: 100%; border-collapse: collapse; font-size: 13px;}
        th, td { border: 1px solid #b2dfdb; padding: 10px; text-align: left; }
        th { background: #00695c; color: white; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .bg-light-green { background-color: #dff0d8; font-weight: bold; }
        tr:hover { background-color: #f5f5f5; }
    </style>
</head>
<body>

<div class="card">
    <div class="header">
        <h2 style="color: #004d40;"><i class="fa-solid fa-file-invoice-dollar"></i> Customer Sudah Bayar</h2>
        <div>
            <a href="ar.php" class="btn" style="background:#dc3545; margin-left:10px;"><i class="fa fa-arrow-left"></i> Kembali ke AR</a>
        </div>
    </div>
    
    <div class="search-box">
        <form method="GET">
            <input type="text" name="filter" placeholder="Cari No. Invoice / Customer / Sales..." value="<?= htmlspecialchars($filter ?? '') ?>">
            <button type="submit" class="btn"><i class="fa fa-search"></i> Cari</button>
            <button type="submit" name="export" value="excel" class="btn" style="background: #2e7d32;"><i class="fa fa-file-excel"></i> Export Excel</button>
        </form>
    </div>

    <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
        <table>
            <thead style="position: sticky; top: 0; z-index: 2;">
                <tr>
                    <th width="3%">No</th>
                    <th width="10%">Sales</th>
                    <th width="12%">Tanggal</th>
                    <th width="12%">No. Invoice</th>
                    <th width="15%">Customer</th>
                    <th width="10%" class="text-right">Tagihan</th>
                    <th width="10%" class="text-right">Total Bayar</th>
                    <th width="10%" class="text-right">Sisa Piutang</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    $no = 1;
                    $total_tagihan = 0;
                    $total_bayar = 0;
                    $total_sisa = 0;

                    while ($row = $result->fetch_assoc()) {
                        $tagihan = floatval($row['jumlah']);
                        $bayar = floatval($row['bayar']);
                        $sisa = floatval($row['sisa']);

                        $total_tagihan += $tagihan;
                        $total_bayar += $bayar;
                        $total_sisa += $sisa;

                        echo "<tr>";
                        echo "<td class='text-center'>" . $no++ . "</td>";
                        echo "<td>" . htmlspecialchars($row['userinv']) . "</td>";
                        echo "<td>" . date('d-M-Y H:i', strtotime($row['tanggal_transaksi'])) . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['J']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($row['cust']) . "</td>";
                        echo "<td class='text-right'>" . number_format($tagihan, 0, ',', '.') . "</td>";
                        echo "<td class='text-right' style='color:green; font-weight:bold;'>" . number_format($bayar, 0, ',', '.') . "</td>";
                        echo "<td class='text-right' style='color:red;'>" . number_format($sisa, 0, ',', '.') . "</td>";
                        echo "</tr>";
                    }

                    echo "<tr class='bg-light-green'>";
                    echo "<td colspan='5' class='text-center'>TOTAL KESELURUHAN</td>";
                    echo "<td class='text-right'>" . number_format($total_tagihan, 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . number_format($total_bayar, 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . number_format($total_sisa, 0, ',', '.') . "</td>";
                    echo "</tr>";
                } else {
                    echo "<tr><td colspan='8' class='text-center'>Tidak ada data.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
