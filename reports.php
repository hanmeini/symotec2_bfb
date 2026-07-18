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
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h1>Laporan Penjualan Harian</h1>
    <form method="POST" action="">
    <label for="start_date">Pilih Tanggal Mulai:</label>
    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
    <label for="end_date">Pilih Tanggal Selesai:</label>
    <input type="date" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>
    <button type="submit">Tampilkan Laporan</button>
</form>


<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
} else {
    $start_date = date('Y-m-d'); // Default to today's date
    $end_date = date('Y-m-d');   // Default to today's date
}

// Koneksi database sudah ditangani oleh config1.php
// $conn is available from config1.php

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

// Menentukan klausa WHERE tambahan untuk filter userinv jika dia sales (karena penjualanho1 tidak menyimpan id_gudang secara langsung)
$sales_filter = "";
if ($is_sales) {
    $sales_filter = " AND userinv = '" . $conn->real_escape_string($_SESSION['username']) . "'";
}

$sql = "
    SELECT
        p.tanggal_transaksi,
        p.J,
        IFNULL(c.nama, p.cust) as cust,
        p.jumlah,
        p.bank,
        p.bayar,
        p.sisa,
        p.userinv,
        p.userbayar,
        IF(p.userinv IN ('admin', 'HO', 'HO1'), 'Pusat', UPPER(p.userinv)) as cabang
    FROM 
        penjualanho1 p
    LEFT JOIN cust c ON p.cust = c.kode
    WHERE 
        DATE(p.tanggal_transaksi) BETWEEN ? AND ?$sales_filter
    ORDER BY p.tanggal_transaksi";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$stmt->bind_result(
    $tanggal_transaksi,
    $J,
    $cust,
    $jumlah,
    $bank,
    $bayar,
    $sisa,
    $userinv,
    $userbayar,
    $cabang
);

// Variabel untuk total dan summary
$total_diskon = $total_harga = $total_ppn = $total_jumlah = $total_bayar = $total_sisa = 0;
$summary = [];

echo "<h2>Laporan Penjualan dari tanggal: " . htmlspecialchars($start_date) . " sampai " . htmlspecialchars($end_date) . "</h2>";

$html_detail = ""; $html_detail .= "<table border='1' cellpadding='5' cellspacing='0'>
        <tr>
            <th>Tanggal</th>
            <th>No Inv</th>
            <th>Nama Customer</th>
            <th>Total Tagihan (Jumlah)</th>
            <th>Pelunasan</th>
            <th>Sisa</th>
            <th>Bank / Pembayaran</th>
            <th>Cabang</th>
        </tr>";

while ($stmt->fetch()) {
    $total_jumlah += $jumlah;
    $total_bayar += $bayar;
    $total_sisa += $sisa;

    if (!isset($summary[$cabang])) {
        $summary[$cabang] = [
            'row_count' => 0,
            'total_jumlah' => 0,
            'total_bayar' => 0,
            'total_sisa' => 0
        ];
    }

    $summary[$cabang]['row_count']++;
    $summary[$cabang]['total_jumlah'] += $jumlah;
    $summary[$cabang]['total_bayar'] += $bayar;
    $summary[$cabang]['total_sisa'] += $sisa;

    // Hitung per bank
    if (!empty($bank)) {
        if (!isset($summary_bank[$bank])) {
            $summary_bank[$bank] = 0;
        }
        $summary_bank[$bank] += $bayar;
    }

    $html_detail .= "<tr>
            <td>" . htmlspecialchars($tanggal_transaksi) . "</td>
            <td>" . htmlspecialchars($J) . "<br>" . htmlspecialchars($userinv) . "</td>
            <td>" . htmlspecialchars($cust) . "</td>
            <td>" . number_format($jumlah, 2) . "</td>
            <td>" . number_format($bayar, 2) . "</td>
            <td>" . number_format($sisa, 2) . "</td>
            <td>" . htmlspecialchars($bank) . "<br>" . htmlspecialchars($userbayar) . "</td>
            <td>" . htmlspecialchars($cabang) . "</td>
        </tr>";
}
$html_detail .= "</table>";

$summary_bank = [];
// Hitung summary_bank dari data sudah ter-fetch
// Summary per cabang
$html_cabang = ""; $html_cabang .= "<h2>Summary Per Cabang</h2><table border='1' cellpadding='5' cellspacing='0'>
        <tr>
            <th>Cabang</th>
            <th>Total Nota</th>
            <th>Total Jumlah</th>
            <th>Total Pelunasan</th>
            <th>Total Sisa</th>
        </tr>";

$total_nota_all = $total_jumlah_all = $total_bayar_all = $total_sisa_all = 0;

foreach ($summary as $cabang => $data) {
    $html_cabang .= "<tr>
            <td>" . htmlspecialchars($cabang) . "</td>
            <td>" . $data['row_count'] . "</td>
            <td>" . number_format($data['total_jumlah'], 2) . "</td>
            <td>" . number_format($data['total_bayar'], 2) . "</td>
            <td>" . number_format($data['total_sisa'], 2) . "</td>
        </tr>";

    $total_nota_all += $data['row_count'];
    $total_jumlah_all += $data['total_jumlah'];
    $total_bayar_all += $data['total_bayar'];
    $total_sisa_all += $data['total_sisa'];
}

// Tambahkan baris total keseluruhan
$html_cabang .= "<tr style='font-weight:bold; background-color:#eee;'>
        <td>TOTAL</td>
        <td>$total_nota_all</td>
        <td>" . number_format($total_jumlah_all, 2) . "</td>
        <td>" . number_format($total_bayar_all, 2) . "</td>
        <td>" . number_format($total_sisa_all, 2) . "</td>
    </tr>";

$html_cabang .= "</table>";

$html_bank = "";
$html_bank .= "<h2>Summary Per Bank / Setoran</h2><table border='1' cellpadding='5' cellspacing='0'>
        <tr>
            <th>Nama Bank</th>
            <th>Total Setoran / Pelunasan</th>
        </tr>";

$total_setoran_all = 0;
if (isset($summary_bank) && is_array($summary_bank)) {
    foreach ($summary_bank as $nm_bank => $tot_bayar) {
        $html_bank .= "<tr>
                <td>" . htmlspecialchars($nm_bank) . "</td>
                <td>" . number_format($tot_bayar, 2) . "</td>
              </tr>";
        $total_setoran_all += $tot_bayar;
    }
}
$html_bank .= "<tr style='font-weight:bold; background-color:#eee;'>
        <td>TOTAL SETORAN</td>
        <td>" . number_format($total_setoran_all, 2) . "</td>
      </tr>";
$html_bank .= "</table>";

echo $html_bank . "<br>" . $html_cabang . "<br><h2>Detail Tiap Penjualan</h2>" . $html_detail;

$stmt->close();
$conn->close();
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
</script
