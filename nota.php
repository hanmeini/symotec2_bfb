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

// Query kedua: penjualanNK
$sql2 = "SELECT J, cust, diskon, harga, ppn, jumlah , po, uang, kembalian, userbayar
         FROM penjualanho1
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

    <title>Nota Penjualan</title>
       <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
       body {
    font-family: "Courier New", monospace;
    font-size: 14px;
    margin: 1px;
    padding: 0;
    width: 82mm;
    font-weight: bold;
    line-height: 1;   /* rapat, hampir tanpa jarak */
}
        .container {
            padding: 5px;
            box-sizing: border-box;
            border: 0px solid #000;
            margin: 2px;
            width: 100%;
            background: #fff;
        }
        h1 {
            text-align: center;
            font-size: 14px;
            margin: 0;
        }
        
         h2 {
            text-align: center;
            font-size: 13px;
            margin: 0;
        }
        
          h3 {
            text-align: center;
            font-size: 13px;
            margin: 0;
        }
          h4 {
            text-align: center;
            font-size: 10px;
            margin: 0;
        }
        p {
            margin: 3px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 4px;
            border-bottom: 1px dashed #000;
        }
        th {
            font-size: 8px;
            background: #f0f0f0;
        }
        .total {
            text-align: right;
            margin-top: 5px;
        }
        .total h3 {
            margin: 3px 0;
        }
        .button-container {
            text-align: center;
            margin: 10px 0;
        }
        button {
            padding: 5px 10px;
            font-size: 12px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #45a049;
        }
  @media print {
    @page {
        size: 82mm auto; /* Lebar tetap 58mm, panjang otomatis */
        margin: 0; /* Hilangkan margin agar kertas berkelanjutan */
    }
    body {
        margin: 0;
        padding: 0;
        font-family: Verdana, sans-serif;
        font-size: 14px;
    }
    .container {
        width: 100%;
        page-break-inside: avoid; /* Hindari pemisahan halaman */
        overflow: hidden; /* Hindari pemisahan konten */
    }
    .total {
        text-align: right;
    }
    .button-container {
        display: none; /* Sembunyikan tombol cetak saat mencetak */
    }
    h1, h2, h3, h4 {
        text-align: center;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        text-align: left;
        padding: 4px;
        border-bottom: 1px dashed #000;
    }
    th {
        font-size: 14px;
        background: #f0f0f0;
    }
    p {
        margin: 3px 0;
    }
    h2, h3 {
        font-size: 12px;
    }
    h1 {
        font-size: 14px;
    }
     h4 {
        font-size: 14px;
    }
    .total h3 {
        font-size: 14px;
    }
    /* Hindari pemisahan konten */
    .container, .total, table, tr, td {
        page-break-inside: avoid;
    }
}


.abc {
    display: flex;
    justify-content: space-between; /* Membuat elemen menyebar di kiri dan kanan */
    align-items: center; /* Menjaga posisi elemen secara vertikal terpusat */
    width: 95%; /* Membuat div mengambil seluruh lebar */
    padding: 10px; /* Optional, untuk menambahkan jarak antar elemen dan tepi */

            
        }


.home-icon1 {
    font-size: 24px; /* Sesuaikan ukuran ikon */
    color: #333; /* Sesuaikan warna ikon */
}

.left-icon {
    font-size: 24px; /* Sesuaikan ukuran ikon */
    color: #333; /* Sesuaikan warna ikon */
}


    </style>
    <script>
function openPopup(url) {
    const popupWidth = 800; // Lebar popup
    const popupHeight = 850; // Tinggi popup
    const left = (screen.width - popupWidth) / 2;
    const top = (screen.height - popupHeight) / 2;

    window.open(
        url,
        '_blank',
        `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=yes,scrollbars=yes`
    );
}
</script>
</head>
<body>

<div class="button-container">
       <div class="abc">
  <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="pos.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>

    </div>
    <button onclick="window.print();" style="font-size: 14px; padding: 10px 20px; background-color: #2196F3;"><i class="fas fa-print"></i> Print Nota</button>
</div>
<div class="container">
          <h1><img src="logo.png" alt="Logo" style="max-width: 250px; height: auto;"></h1>

    <h2>Jl. Anggrek no 9,
        <br>Panjang Kidul, Ambarawa.</h2>
 <h2>
    <i class="fa-brands fa-whatsapp"></i> 081556622215
  </h2> <br>
    <h3>Rincian Belanja</h3>

    <hr>
    <?php if (!empty($transaksi_data)): ?>
        <table>
            <?php foreach ($transaksi_data as $data): ?>
                <tr>
                    <td>
                        <?php
                        $jumlah_k = htmlspecialchars($data['jumlah_k']);
                        $nama_b = htmlspecialchars($data['nama_b']);
                        $harga_k = $data['harga_k'];
                        $ppn_k = $data['ppn_k'];
                        $hargat_k = $data['hargat_k'];
                        $hu = $jumlah_k > 0 ? $hargat_k / $jumlah_k : 0;
                        $total = $harga_k + $ppn_k;

                        echo "<div style='line-height: 2;'>"
                            . $jumlah_k . " (unit) " . 
                            $nama_b . " @ Rp" . 
                            number_format($hu, 0). "<br>" .
                            "(Sub Total) RP " . 
                            number_format($hargat_k, 0) .
                            "</div>";
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="text-align:center; color: red;">(Rincian item tidak tersedia)</p>
    <?php endif; ?>
    
        <hr>
        <div class="total">
            <h3>Total: <?php echo isset($penjualan_data['jumlah']) ? number_format($penjualan_data['jumlah'], 0) : '0'; ?></h3>
            <p>dibayar: <?php echo isset($penjualan_data['uang']) ? number_format($penjualan_data['uang'], 0, ',', '.') : '0'; ?></p>
            <p>kembalian: <?php echo isset($penjualan_data['kembalian']) ? number_format($penjualan_data['kembalian'], 0, ',', '.') : '0'; ?></p>
        </div>
        
        <hr>
        <p>Nomor: <?php echo htmlspecialchars($J); ?></p>
        <p>kasir: <?php echo htmlspecialchars($penjualan_data['userbayar'] ?? '-'); ?></p>
        <p>pembeli: <?php echo htmlspecialchars($penjualan_data['cust'] ?? '-'); ?></p>
        <p>Tanggal: <?php echo isset($tanggal_transaksi) ? htmlspecialchars($tanggal_transaksi) : date('Y-m-d'); ?></p>
        
        <h4>Terimakasih telah berbelanja di toko kami</h4>


<br>
</div>
</div>
</div>
</body>
</html>
