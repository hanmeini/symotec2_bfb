<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}


// Ambil parameter sj
if (!isset($_GET['sj']) || $_GET['sj'] === '') {
    die("Parameter sj tidak ditemukan.");
}
$sj_value = $_GET['sj'];

// Query transaksi berdasarkan sj
$sql = "SELECT tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_m, harga_m, dpp, ppn_m, hargat_m, user, cabang, sj
        FROM transaksiHO1
        WHERE sj = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $sj_value);
$stmt->execute();

// Bind hasil
$stmt->bind_result(
    $tanggal_transaksi,
    $J,
    $cus,
    $kode_b,
    $nama_b,
    $jumlah_m,
    $harga_m,
    $dpp,
    $ppn_m,
    $hargat_m,
    $user,
    $cabang,
    $sj
);

$data = [];
while ($stmt->fetch()) {
    $data[] = [
        'tanggal_transaksi' => $tanggal_transaksi,
        'J' => $J,
        'cus' => $cus,
        'kode_b' => $kode_b,
        'nama_b' => $nama_b,
        'jumlah_m' => $jumlah_m,
        'harga_m' => $harga_m,
        'dpp' => $dpp,
        'ppn_m' => $ppn_m,
        'hargat_m' => $hargat_m,
        'user' => $user,
        'cabang' => $cabang,
        'sj' => $sj,
    ];
}

if (count($data) === 0) {
    die("Data tidak ditemukan untuk SJ = " . htmlspecialchars($sj_value));
}

// Ambil header dari baris pertama
$header = $data[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Nota Pembelian - <?php echo htmlspecialchars($header['sj']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; padding:20px; background:#f9f9f9; }
        h1 { text-align:center; color:#4CAF50; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th, td { border:1px solid #ccc; padding:8px; text-align:right; }
        th { background:#4CAF50; color:#fff; text-align:center; }
        tr:nth-child(even){ background:#f2f2f2; }
        tr:hover { background:#ddd; }
        .header-info { margin:20px 0; }
        .header-info strong { display:inline-block; width:150px; }
    </style>
</head>
<body>
    <h1>Detail Nota Pembelian</h1>

    <div class="header-info">
        <p><strong>No Transaksi (J):</strong> <?php echo htmlspecialchars($header['J']); ?></p>
        <p><strong>Customer/Supplier:</strong> <?php echo htmlspecialchars($header['cus']); ?></p>
        <p><strong>Tanggal:</strong> <?php echo htmlspecialchars($header['tanggal_transaksi']); ?></p>
        <p><strong>Surat Jalan:</strong> <?php echo htmlspecialchars($header['sj']); ?></p>
        <p><strong>Cabang:</strong> <?php echo htmlspecialchars($header['cabang']); ?></p>
    </div>

    <table>
        <tr>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>Qty</th>
            <th>Harga</th>
            <th>DPP</th>
            <th>PPN</th>
            <th>Total</th>
            <th>User</th>
        </tr>
        <?php
        $tot_qty = $tot_dpp = $tot_ppn = $tot_total = 0;

        foreach ($data as $row) {
            $tot_qty   += $row['jumlah_m'];
            $tot_dpp   += $row['dpp'];
            $tot_ppn   += $row['ppn_m'];
            $tot_total += $row['hargat_m'];

            echo "<tr>
                    <td>{$row['kode_b']}</td>
                    <td style='text-align:left;'>{$row['nama_b']}</td>
                    <td>" . number_format($row['jumlah_m']) . "</td>
                    <td>" . number_format($row['harga_m'],2) . "</td>
                    <td>" . number_format($row['dpp'],2) . "</td>
                    <td>" . number_format($row['ppn_m'],2) . "</td>
                    <td>" . number_format($row['hargat_m'],2) . "</td>
                    <td>{$row['user']}</td>
                </tr>";
        }
        ?>
        <tr style="font-weight:bold; background:#eee;">
            <td colspan="2" style="text-align:center;">TOTAL</td>
            <td><?php echo number_format($tot_qty); ?></td>
            <td>-</td>
            <td><?php echo number_format($tot_dpp,2); ?></td>
            <td><?php echo number_format($tot_ppn,2); ?></td>
            <td><?php echo number_format($tot_total,2); ?></td>
            <td>-</td>
        </tr>
    </table>

    <p style="margin-top:20px;">
        <a href="javascript:history.back()" style="text-decoration:none; color:#007bff;">
            <i class="fa fa-arrow-left"></i> Kembali
        </a>
    </p>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
