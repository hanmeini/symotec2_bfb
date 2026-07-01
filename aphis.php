<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start();
require_once 'config1.php';

// --- Koneksi ke DB utama dan customer ---


if ($conn->connect_error)  die("Koneksi DB utama gagal: " . $conn->connect_error);


// --- Ambil filter tanggal akhir ---
$end_date = $_POST['end_date'] ?? date('Y-m-t');

// --- Ambil semua invoice sampai tanggal akhir ---
$sql = "
    SELECT inv, tanggal, tagihan AS nilai_inv, pph23, bukpot23, fp, cust_id
    FROM BELI
    WHERE tanggal <= ?
    ORDER BY cust_id ASC, tanggal ASC
";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Query gagal: " . $conn->error);
$stmt->bind_param("s", $end_date);
$stmt->execute();
$result = $stmt->get_result();
$invoices = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// --- Ambil semua customer dari DB customer ---
$customer_map = [];
$resCust = $conn->query("SELECT id, nama FROM cust");
while ($r = $resCust->fetch_assoc()) {
    $customer_map[$r['id']] = $r['id'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SOA Detail Semua Vendor</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body { font-family: Arial, sans-serif; background: #f4f7fa; padding: 20px; }
.container { background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.btn { background: #007bff; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; }
.btn:hover { background: #0056b3; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
th, td { border: 1px solid #ddd; padding: 8px; }
th { background: #007bff; color: #fff; text-align: center; }
tr:nth-child(even) { background: #f9f9f9; }
.text-right { text-align: right; }
.group { background: #e0f0ff; font-weight: bold; }
.info-box { background: #eef6ff; padding: 10px; border-radius: 6px; margin: 10px 0; }
tfoot td { font-weight: bold; background: #dfeaff; }
</style>
</head>
<body>
<a href="home.php" class="home-icon1">
    <i class="fas fa-home"></i>
</a>

<div class="container">
<form method="POST">
    <label>s/d Tanggal:</label>
    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
    <button type="submit" class="btn"><i class="fa-solid fa-search"></i> Tampilkan</button>
    <?php if (!empty($invoices)): ?>
    <button type="submit" formaction="export_soavendor.php" formmethod="POST" class="btn" style="background:#28a745;">
        <i class="fa-solid fa-file-excel"></i> Download Excel
    </button>
    <?php endif; ?>
</form>

<?php if (!empty($invoices)): ?>
<div class="info-box">
    <strong>Periode:</strong> s/d <?= $end_date ?><br>
    <strong>Total Invoice:</strong> <?= count($invoices) ?> data
</div>

<table>
    <thead>
        <tr>
            <th>Customer</th>
            <th>Tanggal</th>
            <th>Invoice</th>
            <th class="text-right">Nilai Inv</th>
            <th class="text-right">PPh 23</th>
            <th class="text-right">Dibayar</th>
            <th class="text-right">Sisa</th>
            <th>Bukpot</th>
            <th>Faktur</th>
        </tr>
    </thead>
    <tbody>
<?php
$total_inv = 0;
$total_pph = 0;
$total_bayar = 0;
$total_sisa = 0;
$current_customer = '';

foreach ($invoices as $inv) {
    $inv_no    = $inv['inv'];
    $inv_tgl   = $inv['tanggal'];
    $nilai_inv = $inv['nilai_inv'];
    $pph23     = $inv['pph23'];
    $bukpot    = $inv['bukpot23'];
    $fp        = $inv['fp'];
    $cust_id   = $inv['cust_id'];
    $customer  = $customer_map[$cust_id] ?? 'Tidak Dikenal';

    // Ambil total bayar sampai tanggal akhir
    $sql_total_bayar = "SELECT COALESCE(SUM(bayar1),0) FROM apby WHERE inv = ? AND tanggal <= ?";
    $stx = $conn->prepare($sql_total_bayar);
    $stx->bind_param("ss", $inv_no, $end_date);
    $stx->execute();
    $stx->bind_result($total_bayar_inv);
    $stx->fetch();
    $stx->close();

    $sisa = $nilai_inv - $pph23 - $total_bayar_inv;
    if ($sisa == 0) continue;

    // header per customer
    if ($current_customer !== $customer) {
        echo "<tr class='group'><td colspan='9'>{$customer}</td></tr>";
        $current_customer = $customer;
    }

    echo "<tr>
            <td></td>
            <td>{$inv_tgl}</td>
            <td>{$inv_no}</td>
            <td class='text-right'>".number_format($nilai_inv,0,',','.')."</td>
            <td class='text-right'>".number_format($pph23,0,',','.')."</td>
            <td class='text-right'>".number_format($total_bayar_inv,0,',','.')."</td>
            <td class='text-right'>".number_format($sisa,0,',','.')."</td>
            <td>".htmlspecialchars($bukpot ?: '-')."</td>
            <td>".htmlspecialchars($fp ?: '-')."</td>
        </tr>";

    $total_inv += $nilai_inv;
    $total_pph += $pph23;
    $total_bayar += $total_bayar_inv;
    $total_sisa += $sisa;
}
?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="text-right">TOTAL</td>
            <td class="text-right"><?= number_format($total_inv,0,',','.') ?></td>
            <td class="text-right"><?= number_format($total_pph,0,',','.') ?></td>
            <td class="text-right"><?= number_format($total_bayar,0,',','.') ?></td>
            <td class="text-right"><?= number_format($total_sisa,0,',','.') ?></td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    <p><em>Tidak ada data ditemukan.</em></p>
<?php endif; ?>
</div>
</body>
</html>
