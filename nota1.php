<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);



$allowed_referer_domain = "https://symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $allowed_referer_domain) !== 0) {
    header("Location: https://symotech.id");
    exit();
}


// Periksa apakah pengguna sudah login
if (!isset($_SESSION['userid'])) {
    // Jika belum login, redirect ke halaman login
    header("Location: index.html");
    exit();
}

// Periksa apakah session location adalah 'HO' atau 'HO1'
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    // Jika lokasi bukan 'HO' atau 'HO1', redirect ke halaman login
    header("Location: index.html");
    exit();
}
require_once 'config.php';

// Konfigurasi koneksi database
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("Kesalahan: DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
  die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil nilai 'J' dari URL
$J = isset($_GET['J']) ? $_GET['J'] : null;

// Query pertama: transaksiho1
$sql = "SELECT tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_k, harga_k, ppn_k, hargat_k 
        FROM transaksiho1
        WHERE J = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $J);
$stmt->execute();
$stmt->bind_result($tanggal_transaksi, $j_value, $cus, $kode_b, $nama_b, $jumlah_k, $harga_k, $ppn_k, $hargat_k);

$total_harga_k = 0;
$total_ppn_k = 0;
$total_hargat_k = 0;

// Proses hasil query pertama
$transaksi_data = [];
if ($stmt->store_result() && $stmt->num_rows > 0) {
    while ($stmt->fetch()) {
        $transaksi_data[] = [
            'kode_b' => $kode_b,
            'nama_b' => $nama_b,
            'jumlah_k' => $jumlah_k,
            'harga_k' => $harga_k,
            'ppn_k' => $ppn_k,
            'hargat_k' => $hargat_k,
        ];

        $total_harga_k += $harga_k;
        $total_ppn_k += $ppn_k;
        $total_hargat_k += $hargat_k;
    }
}
$stmt->close(); // Pastikan query pertama selesai

// Query kedua: penjualanho1
$sql2 = "SELECT J, cust, diskon, harga, ppn, jumlah , po
         FROM penjualanho1
         WHERE J = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $J);
$stmt2->execute();
$stmt2->bind_result($j_value, $cust, $diskon, $harga2, $ppn2, $jumlah, $po);

// Proses hasil query kedua
$penjualan_data = [];
if ($stmt2->store_result() && $stmt2->num_rows > 0) {
    $stmt2->fetch();
    $penjualan_data = [
        'cust' => $cust,
        'diskon' => $diskon,
        'harga2' => $harga2,
        'ppn2' => $ppn2,
        'jumlah' => $jumlah,
    ];
}

$stmt2->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Penjualan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .total {
            margin-top: 20px;
            text-align: right;
        }
        .home-icon1 {
            position: absolute;
            left: 0;
            top: 0;
            padding-left: 10px;
            color: maroon;
            font-size: 24px;
        }
        .left-icon {
            position: absolute;
            right: 0;
            top: 0;
            padding-right: 10px;
            color: maroon;
            font-size: 24px;
        }
        .total h3 {
            margin: 5px 0;
            color: #4CAF50;
        }
        .no-data {
            text-align: center;
            color: red;
            font-size: 18px;
        }
        .button-container {
            margin-top: 20px;
            text-align: center;
        }
        .button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 4px 2px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="pos.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <?php if (!empty($transaksi_data)): ?>
        <h1>Nota Penjualan</h1>
        <p>Nomor: <?php echo htmlspecialchars($J); ?></p>
        <p>Tanggal Transaksi: <?php echo htmlspecialchars($tanggal_transaksi); ?></p>
    <p>Nomor PO/Kontrak: <?php echo htmlspecialchars($po); ?></p>
        <table>
            <tr>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Jumlah</th>
                <th>Harga</th>
                <th>PPN</th>
                <th>Total Harga</th>
            </tr>
            <?php foreach ($transaksi_data as $data): ?>
                <tr>
                    <td><?php echo htmlspecialchars($data['kode_b']); ?></td>
                    <td><?php echo htmlspecialchars($data['nama_b']); ?></td>
                    <td><?php echo htmlspecialchars($data['jumlah_k']); ?></td>
                    <td><?php echo number_format($data['harga_k'], 2); ?></td>
                    <td><?php echo number_format($data['ppn_k'], 2); ?></td>
                    <td><?php echo number_format($data['hargat_k'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="total">
            <h3>Total: <?php echo number_format($total_harga_k, 2); ?></h3>
            <h3>Diskon: <?php echo number_format($penjualan_data['diskon'], 2); ?></h3>
            <h3>Total Setelah Diskon: <?php echo number_format($penjualan_data['harga2'], 2); ?></h3>
            <h3>PPN: <?php echo number_format($penjualan_data['ppn2'], 2); ?></h3>
            <h3>Total Harga Termasuk PPN: <?php echo number_format($penjualan_data['jumlah'], 2); ?></h3>
        </div>

        <div class="button-container">
            <button class="button" onclick="window.print();">Print</button>
            <a href="home.php" class="button">Back to Home</a>
             <a href="pelunasan.php?J=<?php echo urlencode($J); ?>" class="button">Pelunasan</a>
                <a href="sjSK.php?J=<?php echo urlencode($J); ?>" class="button">Surat jalan</a>
        </div>
    <?php else: ?>
        <div class="no-data">
            <p>Tidak ada transaksi untuk kolom J: <?php echo htmlspecialchars($J); ?></p>
        </div>
    <?php endif; ?>
</div>
</body>
<script>
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    document.addEventListener('keydown', function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
            e.preventDefault();
        }
    });
</script>
</html>
