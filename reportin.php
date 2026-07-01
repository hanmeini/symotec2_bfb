<?php
require_once 'config1.php';
// Validasi Hak Akses
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}
$is_sales = ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1');

// Form Submit Date Filter
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$sup_filter = $_GET['sup'] ?? '';

// Kita akan query dari pembelianho1
// Karena ini menu Sales, mereka mungkin hanya melihat barang masuk untuk cabang mereka
// Sales filtering via 'userinv' atau 'cabang'
$where_conds = ["DATE(tanggal_transaksi) BETWEEN ? AND ?"];
$params = [$start_date, $end_date];
$types = "ss";

if ($sup_filter !== '') {
    $where_conds[] = "sup = ?";
    $params[] = $sup_filter;
    $types .= "s";
}

if ($is_sales) {
    $where_conds[] = "userinv = ?";
    $params[] = $_SESSION['username'];
    $types .= "s";
}

$where_sql = implode(" AND ", $where_conds);

$sql = "SELECT tanggal_transaksi, j, sup, jumlah_m, harga_m, ppn_m, hargat_m, sj, userinv, cabang 
        FROM pembelianho1 
        WHERE $where_sql 
        ORDER BY tanggal_transaksi DESC, j DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$totals = [
    'jumlah_m' => 0,
    'hargat_m' => 0
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Report Barang Masuk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #4CAF50; }
        .form-inline { display: flex; gap: 15px; margin-bottom: 20px; align-items:flex-end; justify-content:center;}
        .form-inline label { font-weight: bold; margin-bottom:5px; display:block;}
        .form-inline input, .form-inline button, .form-inline select { padding: 8px; border: 1px solid #ccc; border-radius:4px; }
        .form-inline button { background-color: #4CAF50; color: white; cursor: pointer; border: none; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top:20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        th { background-color: #4CAF50; color: white; }
        .home-icon { font-size: 24px; color: maroon; text-decoration: none; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>

<div class="container">
    <a href="home.php" class="home-icon"><i class="fas fa-home"></i> Kembali ke Home</a>
    <h2>Laporan Barang Masuk (Pembelian)</h2>

    <form class="form-inline" method="GET" action="">
        <div>
            <label>Mulai Tanggal</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
        </div>
        <div>
            <label>Sampai Tanggal</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
        </div>
        <div>
            <label>Supplier</label>
            <input type="text" name="sup" value="<?= htmlspecialchars($sup_filter) ?>" placeholder="Semua Supplier...">
        </div>
        <div>
            <button type="submit"><i class="fas fa-search"></i> Tampilkan</button>
        </div>
    </form>

    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tanggal</th>
                    <th>No Ref (J)</th>
                    <th>Supplier</th>
                    <th>Surat Jalan</th>
                    <th>Jml Masuk</th>
                    <?php if (!$is_sales): ?>
                    <th>Total Harga</th>
                    <?php endif; ?>
                    <th>User</th>
                    <th>Cabang</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result->num_rows > 0) {
                    $no = 1;
                    while ($r = $result->fetch_assoc()) {
                        $totals['jumlah_m'] += $r['jumlah_m'];
                        $totals['hargat_m'] += $r['hargat_m'];
                        
                        echo "<tr>";
                        echo "<td>{$no}</td>";
                        echo "<td>" . htmlspecialchars($r['tanggal_transaksi']) . "</td>";
                        echo "<td>" . htmlspecialchars($r['j']) . "</td>";
                        echo "<td>" . htmlspecialchars($r['sup']) . "</td>";
                        echo "<td>" . htmlspecialchars($r['sj']) . "</td>";
                        echo "<td>" . number_format($r['jumlah_m'], 0) . "</td>";
                        
                        if (!$is_sales) {
                            echo "<td style='text-align:right'>" . number_format($r['hargat_m'], 2) . "</td>";
                        }
                        
                        echo "<td>" . htmlspecialchars($r['userinv']) . "</td>";
                        echo "<td>" . htmlspecialchars($r['cabang']) . "</td>";
                        echo "</tr>";
                        $no++;
                    }
                    
                    // Baris Total
                    echo "<tr style='font-weight:bold; background-color:#f2f2f2;'>";
                    echo "<td colspan='5'>TOTAL</td>";
                    echo "<td>" . number_format($totals['jumlah_m'], 0) . "</td>";
                    
                    if (!$is_sales) {
                        echo "<td style='text-align:right'>" . number_format($totals['hargat_m'], 2) . "</td>";
                    }
                    
                    echo "<td colspan='2'></td>";
                    echo "</tr>";
                } else {
                    echo "<tr><td colspan='10'>Tidak ada data barang masuk.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
