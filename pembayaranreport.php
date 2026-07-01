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
    <title>Laporan Pembayaran Harian</title>
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
        .action-icon a i {
            font-size: 20px;
            margin: 0 10px;
            color: #007bff;
        }
        .action-icon a:hover i {
            color: #333;
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
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    <h1>Laporan Pembayaran</h1>

    <form method="POST" action="">
        <label for="start_date">Start Date:</label>
        <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
        
        <label for="end_date">End Date:</label>
        <input type="date" name="end_date" value="<?php echo date('Y-m-d'); ?>" required>

        <label for="cust">Customer:</label>
        <select name="cust">
            <option value="">All Customers</option>
            <?php
             $result = $conn->query("SELECT id, nama FROM cust ORDER BY nama");
             while ($row = $result->fetch_assoc()) {
                 echo "<option value='" . htmlspecialchars((string)$row['id']) . "'>" . htmlspecialchars((string)$row['nama']) . "</option>";
             }
            ?>
        </select>
        <button type="submit">Tampilkan Laporan</button>
    </form>

   <?php
$start_date = $_POST['start_date'] ?? date('Y-m-d');
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$cust_filter = $_POST['cust'] ?? '';

// Ambil data cabang/sales
$is_sales = false;
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    $is_sales = true;
}

$query = "SELECT tanggal, j_value, cust, bayar, bank, userbayar, cabang FROM pembayaranho1 WHERE DATE(tanggal) BETWEEN ? AND ? AND bayar > 0";
$types = 'ss';
$params = [$start_date, $end_date];

if ($is_sales) {
    $query .= " AND userbayar = ?";
    $types .= 's';
    $params[] = $_SESSION['username'];
}

    if (!empty($cust_filter)) {
        $query .= " AND cust = ?";
        $types .= 's';
        $params[] = $cust_filter;
    }

$query .= " ORDER BY tanggal, userbayar, bank, cabang";

$stmt = $conn->prepare($query);

// Bind parameters secara dinamis
$stmt->bind_param($types, ...$params);

$stmt->execute();
$stmt->bind_result($tanggal, $j_value, $cust, $bayar, $bank, $userbayar, $cabang);

$totals = ['bayar' => 0];
if ($stmt->store_result() && $stmt->num_rows > 0) {
    echo "<h2>Laporan Pembayaran: " . htmlspecialchars($start_date) . " - " . htmlspecialchars($end_date) . "</h2>";
    echo "<table border='1' cellpadding='5'>
            <tr>
                <th>Tanggal</th><th>No Inv</th><th>Customer</th><th>Bayar</th><th>Bank</th><th>User Pembayar</th><th>Cabang</th>
            </tr>";

    while ($stmt->fetch()) {
        $totals['bayar'] += $bayar;

        echo "<tr>
                <td>" . htmlspecialchars($tanggal) . "</td>
                <td>" . htmlspecialchars($j_value) . "</td>
                <td>" . htmlspecialchars($cust) . "</td>
                <td style='text-align:right'>" . number_format($bayar, 2) . "</td>
                <td>" . htmlspecialchars($bank) . "</td>
                <td>" . htmlspecialchars($userbayar) . "</td>
                <td>" . htmlspecialchars($cabang) . "</td>
            </tr>";
    }

    echo "<tr><td colspan='3'><strong>Total</strong></td>
            <td style='text-align:right'><strong>" . number_format($totals['bayar'], 2) . "</strong></td>
            <td colspan='3'></td>
          </tr>";
    echo "</table>";
} else {
    echo "<p>Tidak ada data untuk tanggal tersebut.</p>";
}

$stmt->close();


    // Laporan Total Bayar per User Pembayar per Bank
    echo "<h3>Laporan Total Bayar per User Pembayar per Bank</h3>";

$sql_report = "
    SELECT cabang, bank, userbayar, SUM(bayar) AS total_bayar
    FROM pembayaranho1
    WHERE DATE(tanggal) BETWEEN ? AND ? AND bayar > 0
";

if ($is_sales) {
    $sql_report .= " AND userbayar = ?";
}

$sql_report .= "
    GROUP BY cabang, bank, userbayar
    ORDER BY cabang, bank, userbayar
";

$stmt_report = $conn->prepare($sql_report);

if ($is_sales) {
    $stmt_report->bind_param("sss", $start_date, $end_date, $_SESSION['username']);
} else {
    $stmt_report->bind_param("ss", $start_date, $end_date);
}

// Execute query
$stmt_report->execute();

// Bind result variables
$stmt_report->bind_result($cabang, $bank, $userbayar, $total_bayar);

if ($stmt_report->store_result() && $stmt_report->num_rows > 0) {
    $current_cabang = "";
    $subtotal = 0;

    echo "<table>
            <tr>
                <th>Cabang</th><th>User Pembayar</th><th>Bank</th><th>Total Bayar</th>
            </tr>";

    while ($stmt_report->fetch()) {
        // Check if cabang has changed
        if ($current_cabang !== $cabang) {
            // Display subtotal for the previous cabang
            if ($current_cabang !== "") {
                echo "<tr>
                        <td colspan='3' style='text-align:right; font-weight:bold;'>Subtotal for $current_cabang</td>
                        <td style='font-weight:bold;'>" . number_format($subtotal, 2) . "</td>
                    </tr>";
            }

            // Reset subtotal and update current cabang
            $current_cabang = $cabang;
            $subtotal = 0;
        }

        // Display row data
        echo "<tr>
                <td>" . htmlspecialchars($cabang) . "</td>
                <td>" . htmlspecialchars($userbayar) . "</td>
                <td>" . htmlspecialchars($bank) . "</td>
                <td>" . number_format($total_bayar, 2) . "</td>
            </tr>";

        // Add to subtotal
        $subtotal += $total_bayar;
    }

    // Display subtotal for the last cabang
    if ($current_cabang !== "") {
        echo "<tr>
                <td colspan='3' style='text-align:right; font-weight:bold;'>Subtotal for $current_cabang</td>
                <td style='font-weight:bold;'>" . number_format($subtotal, 2) . "</td>
            </tr>";
    }

    echo "</table>";
} else {
    echo "<p>Tidak ada data untuk laporan ini.</p>";
}


    $stmt_report->close();
    $conn->close();
    ?>
</body>
</html>
