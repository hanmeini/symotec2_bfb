<?php
session_start();

if (!isset($_SESSION['userid'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';

// Konfigurasi koneksi database
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS'); 
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$id_gudang = $_GET['id_gudang'] ?? '';

// Menentukan apakah user adalah sales
$is_sales = false;
if (isset($_SESSION['location']) && $_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    $is_sales = true;
}

$sales_filter = "";
if ($is_sales) {
    $sales_filter = " AND p.userinv = '" . $conn->real_escape_string($_SESSION['username']) . "'";
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

// Mengambil data penjualan kredit yang belum lunas (sisa > 0)
$sql = "SELECT 
            p.tanggal_transaksi, p.J, p.cust, p.jumlah, p.bayar, p.sisa, p.userinv,
            DATEDIFF(CURDATE(), p.tanggal_transaksi) AS umur
        FROM penjualanho1 p 
        WHERE p.sisa > 0 $sales_filter $filter_sql
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
    <title>AP Sales (Piutang Kredit)</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #e0f2f1; margin: 0; padding: 20px; }
        .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .btn { padding: 8px 15px; background: #00897b; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; display: inline-block;}
        .btn:hover { background: #00695c; }
        .btn-warning { background: #ffb300; color: black; font-weight: bold; }
        .btn-warning:hover { background: #ffa000; }
        
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
        <h2 style="color: #004d40;"><i class="fa-solid fa-file-invoice-dollar"></i> Laporan AP Sales (Piutang Penjualan Kredit)</h2>
        <div>
            <a href="gudang/home.php?id=<?= htmlspecialchars($id_gudang) ?>" class="btn"><i class="fa fa-arrow-left"></i> Kembali</a>
        </div>
    </div>
    
    <div class="search-box">
        <form method="GET">
            <input type="text" name="filter" placeholder="Cari No. Invoice / Customer / Sales..." value="<?= htmlspecialchars($filter ?? '') ?>">
            <button type="submit" class="btn"><i class="fa fa-search"></i> Cari</button>
            <button type="submit" name="export" value="excel" class="btn" style="background:#207245;"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Sales</th>
                    <th>Tanggal Transaksi</th>
                    <th>No. Invoice (J)</th>
                    <th>Customer</th>
                    <th class="text-right">Sisa (Piutang)</th>
                    <th class="text-right">1-30 Hari</th>
                    <th class="text-right">31-60 Hari</th>
                    <th class="text-right">61-90 Hari</th>
                    <th class="text-right">>90 Hari</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $data_rows = [];
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $data_rows[] = $row;
                    }
                }

                $total_sisa = 0;
                $total_1_30 = 0;
                $total_31_60 = 0;
                $total_61_90 = 0;
                $total_90_plus = 0;

                if (count($data_rows) > 0) {
                    $no = 1;
                    foreach($data_rows as $row) {
                        $umur = intval($row['umur']);
                        $sisa = floatval($row['sisa']);

                        $sisa_1_30 = ($umur >= 1 && $umur <= 30) ? $sisa : 0;
                        $sisa_31_60 = ($umur >= 31 && $umur <= 60) ? $sisa : 0;
                        $sisa_61_90 = ($umur >= 61 && $umur <= 90) ? $sisa : 0;
                        $sisa_90_plus = ($umur > 90) ? $sisa : 0;

                        $total_sisa += $sisa;
                        $total_1_30 += $sisa_1_30;
                        $total_31_60 += $sisa_31_60;
                        $total_61_90 += $sisa_61_90;
                        $total_90_plus += $sisa_90_plus;

                        echo "<tr>";
                        echo "<td class='text-center'>" . $no++ . "</td>";
                        echo "<td>" . htmlspecialchars($row['userinv']) . "</td>";
                        echo "<td>" . date('d-M-Y H:i', strtotime($row['tanggal_transaksi'])) . "</td>";
                        echo "<td><strong>" . htmlspecialchars($row['J']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($row['cust']) . "</td>";
                        echo "<td class='text-right' style='color:red; font-weight:bold;'>" . number_format($sisa, 0, ',', '.') . "</td>";
                        echo "<td class='text-right'>" . ($sisa_1_30 > 0 ? number_format($sisa_1_30, 0, ',', '.') : '') . "</td>";
                        echo "<td class='text-right'>" . ($sisa_31_60 > 0 ? number_format($sisa_31_60, 0, ',', '.') : '') . "</td>";
                        echo "<td class='text-right'>" . ($sisa_61_90 > 0 ? number_format($sisa_61_90, 0, ',', '.') : '') . "</td>";
                        echo "<td class='text-right'>" . ($sisa_90_plus > 0 ? number_format($sisa_90_plus, 0, ',', '.') : '') . "</td>";
                        echo "<td class='text-center'>";
                        echo "<a href='pelunasan.php?J=" . urlencode($row['J']) . "' class='btn btn-warning btn-sm' style='padding:4px 8px; font-size:12px;'><i class='fa fa-money-bill-wave'></i> Pelunasan</a>";
                        echo "</td>";
                        echo "</tr>";
                    }

                    echo "<tr class='bg-light-green'>";
                    echo "<td colspan='5' class='text-center'>TOTAL KESELURUHAN</td>";
                    echo "<td class='text-right'>" . number_format($total_sisa, 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . number_format($total_1_30, 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . number_format($total_31_60, 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . number_format($total_61_90, 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . number_format($total_90_plus, 0, ',', '.') . "</td>";
                    echo "<td></td>";
                    echo "</tr>";
                } else {
                    echo "<tr><td colspan='11' class='text-center'>Tidak ada data piutang penjualan kredit yang belum lunas.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (count($data_rows) > 0): ?>
<div style="display:flex; gap: 20px; flex-wrap: wrap;">
    <!-- REKAP PER SALES -->
    <div class="card" style="flex: 1; min-width: 400px;">
        <h3 style="color: #004d40; border-bottom: 2px solid #00897b; padding-bottom:10px;">Rekap Per Sales</h3>
        <table>
            <thead>
                <tr>
                    <th>Sales</th>
                    <th class="text-right">Total Piutang</th>
                    <th class="text-right">1-30 Hari</th>
                    <th class="text-right">> 30 Hari</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rekap_sales = [];
                foreach ($data_rows as $row) {
                    $sales = $row['userinv'] ?: 'Tidak Didefinisikan';
                    $umur = intval($row['umur']);
                    $sisa = floatval($row['sisa']);
                    
                    if (!isset($rekap_sales[$sales])) {
                        $rekap_sales[$sales] = ['total' => 0, 'under_30' => 0, 'over_30' => 0];
                    }
                    $rekap_sales[$sales]['total'] += $sisa;
                    if ($umur <= 30) $rekap_sales[$sales]['under_30'] += $sisa;
                    else $rekap_sales[$sales]['over_30'] += $sisa;
                }
                
                foreach ($rekap_sales as $sales => $data) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($sales) . "</td>";
                    echo "<td class='text-right'>" . number_format($data['total'], 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . ($data['under_30'] > 0 ? number_format($data['under_30'], 0, ',', '.') : '') . "</td>";
                    echo "<td class='text-right'>" . ($data['over_30'] > 0 ? number_format($data['over_30'], 0, ',', '.') : '') . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- REKAP PER CUSTOMER -->
    <div class="card" style="flex: 1; min-width: 400px;">
        <h3 style="color: #004d40; border-bottom: 2px solid #00897b; padding-bottom:10px;">Rekap Per Customer</h3>
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th class="text-right">Total Piutang</th>
                    <th class="text-right">1-30 Hari</th>
                    <th class="text-right">> 30 Hari</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rekap_cust = [];
                foreach ($data_rows as $row) {
                    $cust = $row['cust'] ?: 'Tidak Didefinisikan';
                    $umur = intval($row['umur']);
                    $sisa = floatval($row['sisa']);
                    
                    if (!isset($rekap_cust[$cust])) {
                        $rekap_cust[$cust] = ['total' => 0, 'under_30' => 0, 'over_30' => 0];
                    }
                    $rekap_cust[$cust]['total'] += $sisa;
                    if ($umur <= 30) $rekap_cust[$cust]['under_30'] += $sisa;
                    else $rekap_cust[$cust]['over_30'] += $sisa;
                }
                
                foreach ($rekap_cust as $cust => $data) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($cust) . "</td>";
                    echo "<td class='text-right'>" . number_format($data['total'], 0, ',', '.') . "</td>";
                    echo "<td class='text-right'>" . ($data['under_30'] > 0 ? number_format($data['under_30'], 0, ',', '.') : '') . "</td>";
                    echo "<td class='text-right'>" . ($data['over_30'] > 0 ? number_format($data['over_30'], 0, ',', '.') : '') . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</body>
</html>