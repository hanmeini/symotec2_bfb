<?php

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);





if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';





if ($conn->connect_error) {
    die("Koneksi ke database kedua gagal: " . $conn->connect_error);
}

$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$filter_sql = '';

if ($filter !== '') {
    $escaped_filter = $conn->real_escape_string($filter);
    $escaped_filter2 = $conn->real_escape_string($filter);

    $customer_ids = [];
    $customer_query = $conn->query("SELECT id FROM customer WHERE customer LIKE '%$escaped_filter2%'");
    while ($cust = $customer_query->fetch_assoc()) {
        $customer_ids[] = intval($cust['id']);
    }
    $id_list = implode(",", $customer_ids);

    $booking_kode_list = [];
    $booking_query = $conn->query("SELECT DISTINCT kode_booking FROM booking_request WHERE marketing LIKE '%$escaped_filter2%'");
    while ($bk = $booking_query->fetch_assoc()) {
        $booking_kode_list[] = "'" . $conn->real_escape_string($bk['kode_booking']) . "'";
    }
    $kodebooking_list = implode(",", $booking_kode_list);

    $filter_sql .= " AND (
        kodebooking LIKE '%$escaped_filter%'
        OR inv LIKE '%$escaped_filter%'
        " . (!empty($id_list) ? " OR cust_id IN ($id_list)" : "") . "
        " . (!empty($kodebooking_list) ? " OR kodebooking IN ($kodebooking_list)" : "") . "
    )";
}

$sql_pph23 = "
    SELECT 
        id, tanggal, inv, kodebooking, cust_id, bukpot, pph23, tagihan, fp, bayar, sisa, location, devisi,
        DATEDIFF(CURDATE(), tanggal) AS umur
    FROM 
        pph23 
    WHERE 
        sisa > 0
        $filter_sql
    ORDER BY kodebooking
";

$result_pph23 = $conn->query($sql_pph23);

if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once 'export_pdfar.php';
    exit();
} elseif (isset($_GET['export']) && $_GET['export'] == 'excel') {
    require_once 'export_excelars.php';
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Customer Belum Bayar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f2f2f2;
            margin: 0;
            padding: 20px;
        }
        .table-container {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 8px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
        }
        form {
            margin-bottom: 20px;
            text-align: center;
        }
        input[type="text"] {
            padding: 8px;
            width: 400px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button[type="submit"] {
            padding: 8px 14px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button[type="submit"]:hover {
            background-color: #45a049;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        td {
            text-align: left;
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        th {
            background-color: #f4f4f4;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .home-icon i, .left-icon i {
            color: maroon;
            font-size: 36px;
        }
        .home-icon {
            float: left;
        }
        .left-icon {
            float: right;
        }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
    <a href="ar.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    <h1>Customer Belum Bayar</h1>

    <form method="GET">
        <input type="text" name="filter" placeholder="Cari invoice / kode booking / customer / marketing" value="<?= htmlspecialchars($filter ?? '') ?>">
        <button type="submit">Cari</button>
        <button type="submit" name="export" value="excel">Export to Excel</button>
    </form>

<?php
if ($result_pph23->num_rows > 0) {
    $total_sisa = 0;
    $total_bayar = 0;
    $total_tagihan = 0;
    $total_1_30 = 0;
    $total_31_60 = 0;
    $total_61_90 = 0;
    $total_90_plus = 0;

    $data_rows = [];
    while ($row = $result_pph23->fetch_assoc()) {
        $data_rows[] = $row;
    }

    echo "<table>
            <tr>
                <th>ID</th>
                <th>Marketing</th>
                <th>Tanggal</th>
                <th>Invoice</th>
                <th>Kode Booking</th>
                <th>Customer</th>
                <th>Sisa</th>
                <th>1-30 Hari</th>
                <th>31-60 Hari</th>
                <th>61-90 Hari</th>
                <th>>90 Hari</th>
                <th>Loc Dev</th>
               
            </tr>";

    foreach ($data_rows as $row) {
        $customer_name = 'Tidak Ditemukan';
        $sql_customer = "SELECT customer FROM customer WHERE id = " . intval($row['cust_id']);
        $result_customer = $conn->query($sql_customer);
        if ($result_customer && $result_customer->num_rows > 0) {
            $customer_name = $result_customer->fetch_assoc()['customer'];
        }

        $marketing_name = 'Tidak Ditemukan';
        $kode_booking = $conn->real_escape_string($row['kodebooking']);
        $sql_marketing = "SELECT marketing FROM booking_request WHERE kode_booking = '$kode_booking' LIMIT 1";
        $result_marketing = $conn->query($sql_marketing);
        if ($result_marketing && $result_marketing->num_rows > 0) {
            $marketing_name = $result_marketing->fetch_assoc()['marketing'];
        }

        $umur = intval($row['umur']);
        $sisa = floatval($row['sisa']);

        $sisa_1_30 = ($umur >= 1 && $umur <= 30) ? $sisa : 0;
        $sisa_31_60 = ($umur >= 31 && $umur <= 60) ? $sisa : 0;
        $sisa_61_90 = ($umur >= 61 && $umur <= 90) ? $sisa : 0;
        $sisa_90_plus = ($umur > 90) ? $sisa : 0;

        $total_sisa += $sisa;
        $total_bayar += floatval($row['bayar']);
        $total_tagihan += floatval($row['tagihan']);
        $total_1_30 += $sisa_1_30;
        $total_31_60 += $sisa_31_60;
        $total_61_90 += $sisa_61_90;
        $total_90_plus += $sisa_90_plus;

        echo "<tr>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($marketing_name) . "</td>
                <td>" . htmlspecialchars($row['tanggal']) . "</td>
                <td>" . htmlspecialchars($row['inv']) . "</td>
                <td>" . htmlspecialchars($row['kodebooking']) . "</td>
                <td>" . htmlspecialchars($customer_name) . "</td>
                <td style='text-align: right;'>" . number_format($sisa, 2) . "</td>
               <td style='text-align: right;'>" . ($sisa_1_30 > 0 ? number_format($sisa_1_30, 2) : '') ."</td>
                <td style='text-align: right;'>" . ($sisa_31_60 > 0 ? number_format($sisa_31_60, 2) : '') . "</td>
                <td style='text-align: right;'>" . ($sisa_61_90 > 0 ? number_format($sisa_61_90, 2) : '') . "</td>
                <td style='text-align: right;'>" . ($sisa_90_plus > 0 ? number_format($sisa_90_plus, 2) : '') . "</td>
                <td >" . htmlspecialchars($row['location'] . " - " . $row['devisi']) . "</td>
            </tr>";
    }

    echo "<tr style='font-weight: bold; background-color: #dff0d8'>
            <td colspan='6' style='text-align:center'>TOTAL</td>
            <td style='text-align: right;'>" . number_format($total_sisa, 2) . "</td>
            <td style='text-align: right;'>" . number_format($total_1_30, 2) . "</td>
            <td style='text-align: right;' >" . number_format($total_31_60, 2) . "</td>
            <td style='text-align: right;'>" . number_format($total_61_90, 2) . "</td>
            <td style='text-align: right;' >" . number_format($total_90_plus, 2) . "</td>
            <td></td>
          </tr>";
    echo "</table>";

    // REKAP PER SALES
// REKAP PER SALES
$rekap_sales = [];
$total_sisa_sales = 0;
$total_sisa_1_30_sales = 0;
$total_sisa_31_60_sales = 0;
$total_sisa_61_90_sales = 0;
$total_sisa_90_plus_sales = 0;

foreach ($data_rows as $row) {
    $kode_booking = $conn->real_escape_string($row['kodebooking']);
    $sql_marketing = "SELECT marketing FROM booking_request WHERE kode_booking = '$kode_booking' LIMIT 1";
    $result_marketing = $conn->query($sql_marketing);
    $marketing_name = ($result_marketing && $result_marketing->num_rows > 0) ? $result_marketing->fetch_assoc()['marketing'] : 'Tidak Ditemukan';

    $umur = intval($row['umur']);
    $sisa = floatval($row['sisa']);

    // Menghitung sisa untuk rentang hari
    $sisa_1_30 = ($umur >= 1 && $umur <= 30) ? $sisa : 0;
    $sisa_31_60 = ($umur >= 31 && $umur <= 60) ? $sisa : 0;
    $sisa_61_90 = ($umur >= 61 && $umur <= 90) ? $sisa : 0;
    $sisa_90_plus = ($umur > 90) ? $sisa : 0;

    if (!isset($rekap_sales[$marketing_name])) {
        $rekap_sales[$marketing_name] = [
            'total' => 0,
            'sisa_1_30' => 0,
            'sisa_31_60' => 0,
            'sisa_61_90' => 0,
            'sisa_90_plus' => 0
        ];
    }

    $rekap_sales[$marketing_name]['total'] += $sisa;
    $rekap_sales[$marketing_name]['sisa_1_30'] += $sisa_1_30;
    $rekap_sales[$marketing_name]['sisa_31_60'] += $sisa_31_60;
    $rekap_sales[$marketing_name]['sisa_61_90'] += $sisa_61_90;
    $rekap_sales[$marketing_name]['sisa_90_plus'] += $sisa_90_plus;

    // Tambahkan total per sales
    $total_sisa_sales += $sisa;
    $total_sisa_1_30_sales += $sisa_1_30;
    $total_sisa_31_60_sales += $sisa_31_60;
    $total_sisa_61_90_sales += $sisa_61_90;
    $total_sisa_90_plus_sales += $sisa_90_plus;
}

echo "<h2>Rekap Per Sales</h2>";
echo "<table>
        <tr>
          <th>Marketing</th>
            <th>Total Sisa</th>
            <th>1-30 Hari</th>
            <th>31-60 Hari</th>
            <th>61-90 Hari</th>
            <th>>90 Hari</th>
        </tr>";
foreach ($rekap_sales as $marketing => $data) {
    echo "<tr>
    <td>" . htmlspecialchars($marketing) . "</td>
            <td style='text-align: right;'>" . number_format($data['total'], 2) . "</td>
            <td style='text-align: right;'>" . ($data['sisa_1_30'] > 0 ? number_format($data['sisa_1_30'], 2) : '') . "</td>
            <td style='text-align: right;'>" . ($data['sisa_31_60'] > 0 ? number_format($data['sisa_31_60'], 2) : '') . "</td>
            <td style='text-align: right;'>" . ($data['sisa_61_90'] > 0 ? number_format($data['sisa_61_90'], 2) : '') . "</td>
            <td style='text-align: right;'>" . ($data['sisa_90_plus'] > 0 ? number_format($data['sisa_90_plus'], 2) : '') . "</td>
          </tr>";
}
echo "<tr style='font-weight: bold; background-color: #dff0d8'>
        <td><strong>Total</strong></td>
        <td style='text-align: right;' ><strong>" . number_format($total_sisa_sales, 2) . "</strong></td>
        <td style='text-align: right;' ><strong>" . number_format($total_sisa_1_30_sales, 2) . "</strong></td>
        <td style='text-align: right;' ><strong>" . number_format($total_sisa_31_60_sales, 2) . "</strong></td>
        <td style='text-align: right;' ><strong>" . number_format($total_sisa_61_90_sales, 2) . "</strong></td>
        <td style='text-align: right;' ><strong>" . number_format($total_sisa_90_plus_sales, 2) . "</strong></td>
      </tr>";
echo "</table>";

// REKAP PER CUSTOMER
$rekap_customer = [];
$total_sisa_customer = 0;
$total_sisa_1_30_customer = 0;
$total_sisa_31_60_customer = 0;
$total_sisa_61_90_customer = 0;
$total_sisa_90_plus_customer = 0;

foreach ($data_rows as $row) {
    $sql_customer = "SELECT customer FROM customer WHERE id = " . intval($row['cust_id']);
    $result_customer = $conn->query($sql_customer);
    $customer_name = ($result_customer && $result_customer->num_rows > 0) ? $result_customer->fetch_assoc()['customer'] : 'Tidak Ditemukan';

    $umur = intval($row['umur']);
    $sisa = floatval($row['sisa']);

    // Menghitung sisa untuk rentang hari
    $sisa_1_30 = ($umur >= 1 && $umur <= 30) ? $sisa : 0;
    $sisa_31_60 = ($umur >= 31 && $umur <= 60) ? $sisa : 0;
    $sisa_61_90 = ($umur >= 61 && $umur <= 90) ? $sisa : 0;
    $sisa_90_plus = ($umur > 90) ? $sisa : 0;

    if (!isset($rekap_customer[$customer_name])) {
        $rekap_customer[$customer_name] = [
            'total' => 0,
            'sisa_1_30' => 0,
            'sisa_31_60' => 0,
            'sisa_61_90' => 0,
            'sisa_90_plus' => 0
        ];
    }

    $rekap_customer[$customer_name]['total'] += $sisa;
    $rekap_customer[$customer_name]['sisa_1_30'] += $sisa_1_30;
    $rekap_customer[$customer_name]['sisa_31_60'] += $sisa_31_60;
    $rekap_customer[$customer_name]['sisa_61_90'] += $sisa_61_90;
    $rekap_customer[$customer_name]['sisa_90_plus'] += $sisa_90_plus;

    // Tambahkan total per customer
    $total_sisa_customer += $sisa;
    $total_sisa_1_30_customer += $sisa_1_30;
    $total_sisa_31_60_customer += $sisa_31_60;
    $total_sisa_61_90_customer += $sisa_61_90;
    $total_sisa_90_plus_customer += $sisa_90_plus;
}

echo "<h2>Rekap Per Customer</h2>";
echo "<table>
        <tr>
            <th>Customer</th>
            <th>Total Sisa</th>
            <th>1-30 Hari</th>
            <th>31-60 Hari</th>
            <th>61-90 Hari</th>
            <th>>90 Hari</th>
        </tr>";
foreach ($rekap_customer as $customer => $data) {
    echo "<tr>
            <td>" . htmlspecialchars($customer) . "</td>
            <td style='text-align: right;'>" . number_format($data['total'], 2) . "</td>
            <td style='text-align: right;'>" . ($data['sisa_1_30'] > 0 ? number_format($data['sisa_1_30'], 2) : '') . "</td>
            <td style='text-align: right;'>" . ($data['sisa_31_60'] > 0 ? number_format($data['sisa_31_60'], 2) : '') . "</td>
            <td style='text-align: right;'>" . ($data['sisa_61_90'] > 0 ? number_format($data['sisa_61_90'], 2) : '') . "</td>
            <td style='text-align: right;'>" . ($data['sisa_90_plus'] > 0 ? number_format($data['sisa_90_plus'], 2) : '') . "</td>
          </tr>";
}
echo "<tr style='font-weight: bold; background-color: #dff0d8'>
        <td ><strong>Total</strong></td>
        <td style='text-align: right;'><strong>" . number_format($total_sisa_customer, 2) . "</strong></td>
        <td style='text-align: right;'><strong>" . number_format($total_sisa_1_30_customer, 2) . "</strong></td>
        <td style='text-align: right;'><strong>" . number_format($total_sisa_31_60_customer, 2) . "</strong></td>
        <td style='text-align: right;'> <strong>" . number_format($total_sisa_61_90_customer, 2) . "</strong></td>
        <td style='text-align: right;'><strong>" . number_format($total_sisa_90_plus_customer, 2) . "</strong></td>
      </tr>";
echo "</table>";


} else {
    echo "<p>Tidak ada data invoice.</p>";
}

$conn->close();
?>
</div>
<script>
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('keydown', e => {
        if (
            e.keyCode == 123 ||
            (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) ||
            (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) ||
            (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))
        ) e.preventDefault();
    });
</script>
</body>
</html>
