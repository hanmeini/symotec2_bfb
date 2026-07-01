<?php
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
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
?>
<!DOCTYPE html>
<html lang="en">
<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
?>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan Harian</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #4CAF50;
        }
        form {
            margin-bottom: 20px;
            text-align: center;
        }
        label {
            font-weight: bold;
        }
        input[type="date"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
        }
        button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: right;
        }
        th {
            text-align: center;
            background-color: #4CAF50;
            color: white;
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
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #ddd;
        }
        h2, h3 {
            color: #333;
            text-align: center;
        }
        .action-icon {
            text-align: center;
        }

        @media (max-width: 768px) {
            th, td {
                padding: 8px;
            }
            th {
                font-size: 14px;
            }
            td {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h1>Laporan Barang Keluar Harian</h1>
    <form method="POST" action="">
        <label for="tanggal_awal">Tanggal Awal:</label>
        <input type="date" name="tanggal_awal" required>
        <label for="tanggal_akhir">Tanggal Akhir:</label>
        <input type="date" name="tanggal_akhir" required>
        <button type="submit">Tampilkan Laporan</button>
    </form>

  <?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Koneksi ke database
    $servername = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'symotech_gm';

    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Koneksi gagal, silakan coba lagi nanti.");
    }

    // Ambil rentang tanggal dari form
    $tanggal_awal = $_POST['tanggal_awal'];
    $tanggal_akhir = $_POST['tanggal_akhir'];

    // Query untuk mendapatkan laporan penjualan
    $sql = "
    SELECT 
        J, cus, kode_b, nama_b, jumlah_k, user, cabang
    FROM (
        SELECT J, cus, kode_b, nama_b, jumlah_k, user, cabang, tanggal_transaksi FROM transaksi_b
        UNION ALL
        SELECT J, cus, kode_b, nama_b, jumlah_k, user, cabang, tanggal_transaksi FROM transaksiNK
        UNION ALL
           SELECT J, cus, kode_b, nama_b, jumlah_k, user, cabang, tanggal_transaksi FROM transaksiHO1
        UNION ALL
        SELECT J, cus, kode_b, nama_b, jumlah_k, user, cabang, tanggal_transaksi FROM transaksiLMD
        UNION ALL
        SELECT J, cus, kode_b, nama_b, jumlah_k, user, cabang, tanggal_transaksi FROM transaksiSEPEDA
        UNION ALL
        SELECT J, cus, kode_b, nama_b, jumlah_k, user, cabang, tanggal_transaksi FROM transaksiFurniture
    ) AS semua_transaksi
    WHERE 
        DATE(tanggal_transaksi) BETWEEN ? AND ?
        AND J IS NOT NULL 
        AND J <> ''
        AND jumlah_k > 0
    ORDER BY J";

    $stmt = $conn->prepare($sql);

    // Bind 2 parameter untuk tanggal awal dan akhir
    $stmt->bind_param("ss", $tanggal_awal, $tanggal_akhir);
    $stmt->execute();

    // Bind hasil ke variabel
    $stmt->bind_result($j_value, $cus, $kode_b, $nama_b, $jumlah_k, $user, $cabang);

    // Variabel untuk menyimpan summary per kode barang dan cabang
    $summary = [];

    echo "<h2>Laporan Penjualan dari tanggal: " . htmlspecialchars($tanggal_awal) . " hingga " . htmlspecialchars($tanggal_akhir) . "</h2>";
    echo "<table>
            <tr>
                <th>Nomor In</th>
                <th>Supplyer</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Jumlah</th>
                <th>User</th>
                <th>Cabang</th>
           
            </tr>";

    while ($stmt->fetch()) {
        echo "<tr>
                <td>" . htmlspecialchars($j_value) . "</td>
                <td>" . htmlspecialchars($cus) . "</td>
                <td>" . htmlspecialchars($kode_b) . "</td>
                <td>" . htmlspecialchars($nama_b) . "</td>
                <td>" . number_format($jumlah_k) . "</td>
                <td>" . htmlspecialchars($user) . "</td>
                <td>" . htmlspecialchars($cabang) . "</td>
              
            </tr>";

        // Simpan data untuk summary
        $key = $kode_b . '|' . $nama_b . '|' . $cabang;
        if (!isset($summary[$key])) {
            $summary[$key] = 0;
        }
        $summary[$key] += $jumlah_k;
    }
    echo "</table>";

    // Tampilkan summary per kode barang dan cabang
    echo "<h2>Summary Total Per Kode Barang dan Cabang</h2>";
    echo "<table>
            <tr>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Cabang</th>
                <th>Total Jumlah</th>
            </tr>";

    foreach ($summary as $key => $total_jumlah) {
        list($kode_barang, $nama_barang, $cabang_barang) = explode('|', $key);
        echo "<tr>
                <td>" . htmlspecialchars($kode_barang) . "</td>
                <td>" . htmlspecialchars($nama_barang) . "</td>
                <td>" . htmlspecialchars($cabang_barang) . "</td>
                <td>" . number_format($total_jumlah) . "</td>
            </tr>";
    }
    echo "</table>";

    // Tutup koneksi
    $stmt->close();
    $conn->close();
}

 

?>

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
