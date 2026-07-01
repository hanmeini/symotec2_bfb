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


// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan


// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi dari config.php
require_once 'config1.php';

// Koneksi database ke server kedua



// Ambil filter customer dari form GET
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';

// Query data titipan yang belum digunakan
$sql = "
    SELECT 
        t.id, 
        t.kode_booking, 
        t.tanggal, 
        t.nominal, 
        t.description,
        t.id_parent,
        t.cust_id,
        c.nama
    FROM 
        titipan t
    LEFT JOIN cust c ON t.cust_id = c.id
    WHERE 
        (t.inv = '' OR t.inv IS NULL)
        AND t.nominal > 0
";

if (!empty($filter_customer)) {
    $sql .= " AND c.nama LIKE '%" . $conn->real_escape_string($filter_customer) . "%'";
}

$sql .= " ORDER BY t.id, t.cust_id";

$result = $conn->query($sql);

// Inisialisasi total nominal
$total_nominal = 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>daftar titipan www.symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
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
        </style>
</head>
<body>
      <div class="table-container">
        <a href="home.php" class="home-icon1">
            <i class="fas fa-home"></i>
        </a>
        <a href="daftartitipan.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
    <title>Daftar Titipan Belum Digunakan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="container mt-4">
    <h3>Daftar Titipan Belum Digunakan</h3>

    <form method="GET" class="form-inline mb-3">
        <label for="filter_customer" class="mr-2">Filter Customer:</label>
        <input type="text" name="filter_customer" id="filter_customer" class="form-control mr-2" 
               value="<?= htmlspecialchars($filter_customer ?? '') ?>" placeholder="Nama Customer">
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <a href="alldp.php" class="btn btn-secondary ml-2">Reset</a>
        <a href="export_titipan.php?filter_customer=<?= urlencode($filter_customer) ?>" 
           class="btn btn-success ml-2">
            Export Excel
        </a>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Kode Booking</th>
                    <th>Tanggal</th>
                    <th>Nominal</th>
                    <th>Keterangan</th>
                    <th>Parent</th>
                    <th>Customer</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php $total_nominal += $row['nominal']; ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['kode_booking'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['tanggal'] ?? '') ?></td>
                            <td class="text-right"><?= number_format($row['nominal'], 2) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['id_parent'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['nama'] ?? '') ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="3" class="text-center">TOTAL</td>
                        <td class="text-right"><?= number_format($total_nominal, 2) ?></td>
                        <td colspan="3"></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">Tidak ada data</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

<?php $conn->close(); ?>
