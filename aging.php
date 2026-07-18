<?php
require_once 'config1.php';
// Validasi Hak Akses (Jika butuh proteksi lebih lanjut bisa diletakkan di sini, 
// tapi config1.php sudah memvalidasi login).
?>
<!DOCTYPE html>
<html lang="en">
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
        button:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto; /* Tambahkan scroll horizontal untuk tabel */
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: right; /* Rata kanan untuk semua kolom */
        }
        th {
            text-align: center; /* Rata tengah untuk header */
            background-color: #4CAF50;
            color: white;
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
            text-align: center; /* Rata tengah untuk kolom action */
        }
        /* Untuk semua ikon dalam kolom action-icon */
        .action-icon a i {
            font-size: 20px; /* Ukuran ikon */
            margin: 0 10px;  /* Spasi horizontal antar ikon */
            color: #007bff;     /* Warna default ikon */
        }

        /* Opsional: Tambahkan efek hover */
        .action-icon a:hover i {
            color: #333;  /* Warna ikon saat hover */
        }

        /* Media Query untuk perangkat mobile */
        @media (max-width: 768px) {
            th, td {
                padding: 8px; /* Mengurangi padding untuk kolom pada layar kecil */
            }
            th {
                font-size: 14px; /* Mengurangi ukuran font pada header */
            }
            td {
                font-size: 12px; /* Mengurangi ukuran font pada sel */
            }
        }
    </style>
</head>
<body>
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    <h1>Laporan Aging</h1>
    <form method="POST" action="">
      
        <label for="end_date">cut off inv:</label>
        <input type="date" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>
         <label for="cust">Customer:</label>
        <select name="cust">
            <option value="">All Customers</option>
            <?php
            $result = $conn->query("SELECT DISTINCT cust FROM penjualanho1");
            while ($row = $result->fetch_assoc()) {
                echo "<option value='" . $row['cust'] . "'>" . htmlspecialchars($row['cust']) . "</option>";
            }
            ?>
               </select>
        <button type="submit">Tampilkan Laporan</button>
    </form>

   <?php

$end_date = $_POST['end_date'] ?? date('Y-m-d'); // Tanggal akhir dari input POST, atau tanggal hari ini jika kosong.
$cust_filter = $_POST['cust'] ?? ''; // Mendapatkan customer filter dari POST

// Koneksi database ditangani config1.php

// Ambil data cabang/sales
$is_sales = false;
$user_sales_ids = [];
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    $is_sales = true;
    $userid = $_SESSION['userid'];
    $stmt_sales = $conn->prepare("SELECT id_gudang FROM master_sales WHERE userid = ?");
    $stmt_sales->bind_param("i", $userid);
    $stmt_sales->execute();
    $res_sales = $stmt_sales->get_result();
    while($row = $res_sales->fetch_assoc()){
        $user_sales_ids[] = $row['id_gudang'];
    }
    $stmt_sales->close();
}

// Menentukan klausa WHERE tambahan untuk filter userinv jika dia sales
$sales_filter = "";
if ($is_sales) {
    $sales_filter = " AND userinv = '" . $conn->real_escape_string($_SESSION['username']) . "'";
}


// Query SQL yang diperbarui dengan filter customer
$sql = "
    SELECT
        tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, '' as bank, 0 as bayar, jumlah as sisa, '' as fp_k, userinv, '' as userbayar, IF(userinv IN ('admin', 'HO', 'HO1'), 'Pusat', UPPER(userinv)) as cabang
    FROM penjualanho1
    WHERE DATE(tanggal_transaksi) <= ?
      AND (jumlah - 0) > 0
      AND J IS NOT NULL$sales_filter";

if ($cust_filter) {
    $sql .= " AND cust = ?";
}

$sql .= " ORDER BY tanggal_transaksi";

// Prepare the statement
$stmt = $conn->prepare($sql);

// Binding parameters based on the presence of $cust_filter
if ($cust_filter) {
    $stmt->bind_param("ss", $end_date, $cust_filter);
} else {
    $stmt->bind_param("s", $end_date);
}

// Execute the query
$stmt->execute();

// Binding result
$stmt->bind_result($tanggal_transaksi, $j_value, $cust, $diskon, $harga, $ppn, $jumlah, $bank, $bayar, $sisa, $fp_k, $userinv, $userbayar, $cabang);

$totals = [
    'diskon' => 0, 'harga' => 0, 'ppn' => 0,
    'jumlah' => 0, 'bayar' => 0, 'sisa' => 0
];

$summaries = [];

// Display the main table
if ($stmt->store_result() && $stmt->num_rows > 0) {
    echo "<h2>Laporan Aging: " . htmlspecialchars($end_date) . "</h2>";
    echo "<table>
            <tr>
                <th>Tanggal</th><th>No Inv</th><th>Customer</th><th>Diskon</th>
                <th>DPP</th><th>PPN</th><th>Jumlah</th><th>Bayar</th>
                <th>Sisa</th><th>Hari Tertunda</th><th>No FP</th><th>Cabang</th>
            </tr>";

    while ($stmt->fetch()) {
        $totals['diskon'] += $diskon;
        $totals['harga'] += $harga;
        $totals['ppn'] += $ppn;
        $totals['jumlah'] += $jumlah;
        $totals['bayar'] += $bayar;
        $totals['sisa'] += $sisa;

        // Menghitung selisih hari antara $end_date dan $tanggal_transaksi
        $tanggal_transaksi_timestamp = strtotime($tanggal_transaksi);
        $end_date_timestamp = strtotime($end_date);
        $days_diff = ($end_date_timestamp - $tanggal_transaksi_timestamp) / (60 * 60 * 24);

        // Summarizing per customer per cabang
        if (!isset($summaries[$cabang][$cust])) {
            $summaries[$cabang][$cust] = ['jumlah' => 0, 'invoice_count' => 0];
        }
        $summaries[$cabang][$cust]['jumlah'] += $jumlah;
        $summaries[$cabang][$cust]['invoice_count']++;

        echo "<tr>
                <td>$tanggal_transaksi</td>
                <td>$j_value</td><td>$cust</td>
                <td>" . number_format($diskon, 2) . "</td>
                <td>" . number_format($harga, 2) . "</td><td>" . number_format($ppn, 2) . "</td>
                <td>" . number_format($jumlah, 2) . "</td><td>" . number_format($bayar, 2) . "</td>
                <td>" . number_format($sisa, 2) . "</td><td>" . $days_diff . " Hari</td><td>$fp_k</td>
                <td>$cabang</td>
            </tr>";
    }

    echo "<tr><td colspan='3'>Total</td>
            <td>" . number_format($totals['diskon'], 2) . "</td>
            <td>" . number_format($totals['harga'], 2) . "</td>
            <td>" . number_format($totals['ppn'], 2) . "</td>
            <td>" . number_format($totals['jumlah'], 2) . "</td>
            <td>" . number_format($totals['bayar'], 2) . "</td>
            <td>" . number_format($totals['sisa'], 2) . "</td>
          </tr>";
    echo "</table>";
} else {
    echo "<p>Tidak ada data untuk tanggal tersebut.</p>";
}








echo "<h3>Summary Per Customer Per Cabang</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>
        <tr>
            <th>Cabang dan Customer</th>
            <th>Total Jumlah</th>
            <th>Jumlah Invoice</th>
        </tr>";

$cabang_totals = []; // Untuk menghitung total per cabang

foreach ($summaries as $cabang_key => $customers) {
    $cabang_totals[$cabang_key] = ['jumlah' => 0, 'invoice_count' => 0]; // Inisialisasi total per cabang

    foreach ($customers as $cust_key => $summary) {
        echo "<tr>
                <td>$cabang_key - $cust_key</td>
                <td>" . number_format($summary['jumlah'], 2) . "</td>
                <td>" . $summary['invoice_count'] . "</td>
              </tr>";

        // Akumulasi total untuk cabang
        $cabang_totals[$cabang_key]['jumlah'] += $summary['jumlah'];
        $cabang_totals[$cabang_key]['invoice_count'] += $summary['invoice_count'];
    }
}

echo "</table>";

// Summary Total Per Cabang
echo "<h3>Total Per Cabang</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>
        <tr>
            <th>Cabang</th>
            <th>Total Jumlah</th>
            <th>Jumlah Invoice</th>
        </tr>";

foreach ($cabang_totals as $cabang_key => $summary) {
    echo "<tr>
            <td>$cabang_key</td>
            <td>" . number_format($summary['jumlah'], 2) . "</td>
            <td>" . $summary['invoice_count'] . "</td>
          </tr>";
}

echo "</table>";

$stmt->close();
$conn->close();
?>



</body>
</html>
