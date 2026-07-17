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



// Periksa apakah pengguna sudah login
if (!isset($_SESSION['userid'])) {
    // Jika belum login, redirect ke halaman login
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
    die("Koneksi ke database gagal: " . $conn->connect_error);
  die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil nilai 'J' dari URL
$J = isset($_GET['J']) ? $_GET['J'] : null;

// Query pertama: transaksiNK
$sql = "SELECT tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_k, harga_k, ppn_k, hargat_k 
        FROM transaksiHO1
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

// Query kedua: penjualanNK
$sql2 = "SELECT J, cust, diskon, harga, ppn, jumlah , po, uang, kembalian, userbayar
         FROM penjualanHO1
         WHERE J = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $J);
$stmt2->execute();
$stmt2->bind_result($j_value, $cust, $diskon, $harga2, $ppn2, $jumlah, $po,  $uang,  $kembalian,  $userbayar);

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
         'uang' => $uang,
           'kembalian' => $kembalian,
            'userbayar' => $userbayar,
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
    <title>Surat Jalan Pengiriman</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header-table {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-info h2 {
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        .company-info p {
            margin: 0;
            font-size: 13px;
        }
        .sj-title {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
            text-decoration: underline;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .data-table th, .data-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        .data-table th {
            background-color: #f0f0f0;
            text-align: center;
        }
        .footer-table {
            width: 100%;
            margin-top: 40px;
            text-align: center;
        }
        .footer-table td {
            width: 33.33%;
            vertical-align: bottom;
            height: 100px;
        }
        .button-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            padding: 10px 20px;
            font-size: 14px;
            background-color: #2196F3;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-print:hover {
            background-color: #1976D2;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .container { box-shadow: none; max-width: 100%; padding: 0; }
            .button-container { display: none; }
        }
    </style>
</head>
<body>

<div class="button-container">
    <button onclick="window.print();" class="btn-print"><i class="fas fa-print"></i> Cetak Surat Jalan</button>
</div>

<div class="container">
    <table class="header-table">
        <tr>
            <td style="width: 120px;">
                <img src="/assets/img/logo.png" alt="Logo" style="max-width: 100px; height: auto;">
            </td>
            <td class="company-info">
                <h2>RODA MAS</h2>
                <p>Jl. Anggrek no 9, Panjang Kidul, Ambarawa.</p>
                <p>Telp: 0812-XXXX-XXXX</p>
            </td>
        </tr>
    </table>

    <div class="sj-title">SURAT JALAN PENGIRIMAN</div>

    <table class="info-table">
        <tr>
            <td style="width: 100px;"><strong>No. SJ</strong></td>
            <td style="width: 10px;">:</td>
            <td style="width: 300px;"><?= htmlspecialchars($J) ?></td>
            <td style="width: 100px;"><strong>Kepada</strong></td>
            <td style="width: 10px;">:</td>
            <td><?= htmlspecialchars($penjualan_data['cust'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong>Tanggal</strong></td>
            <td>:</td>
            <td><?= isset($tanggal_transaksi) ? date('d-m-Y', strtotime($tanggal_transaksi)) : date('d-m-Y') ?></td>
            <td><strong>Admin</strong></td>
            <td>:</td>
            <td><?= htmlspecialchars($penjualan_data['userbayar'] ?? '-') ?></td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th style="width: 150px;">Kode Barang</th>
                <th>Nama Barang</th>
                <th style="width: 100px;">Jumlah (Qty)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($transaksi_data)): ?>
                <?php $no = 1; foreach ($transaksi_data as $data): ?>
                    <tr>
                        <td style="text-align: center;"><?= $no++ ?></td>
                        <td><?= htmlspecialchars($data['kode_b']) ?></td>
                        <td><?= htmlspecialchars($data['nama_b']) ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($data['jumlah_k']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: red;">(Rincian item tidak tersedia)</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div style="margin-top: 20px; font-style: italic;">
        <p>Catatan:</p>
        <p>1. Harap periksa barang sebelum diterima.</p>
        <p>2. Barang yang sudah dibeli tidak bisa dikembalikan.</p>
    </div>

    <h4 style="text-align: center; margin: 40px 0 20px 0;">Terimakasih telah berbelanja di toko kami</h4>

    <table class="footer-table">
        <tr>
            <td>
                Penerima / Pembeli
                <br><br><br><br><br>
                (_______________________)
            </td>
            <td>
                Pengirim / Supir
                <br><br><br><br><br>
                (_______________________)
            </td>
            <td>
                Hormat Kami,
                <br><br><br><br><br>
                (_______________________)
            </td>
        </tr>
    </table>
</div>

</body>
</html>
