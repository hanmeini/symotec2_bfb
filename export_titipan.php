<?php
require_once 'config1.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=titipan_belum_digunakan.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Koneksi database ke server kedua
$servername2 = getenv('DB_HOST2');
$username2 = getenv('DB_USER2');
$password2 = getenv('DB_PASS2');
$dbname2   = getenv('DB_NAME2');


if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil filter customer dari URL (GET)
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';

// Query data
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

// Inisialisasi total
$total_nominal = 0;

// Output ke Excel
echo "<table border='1'>";
echo "<tr style='background-color: #f2f2f2; font-weight: bold;'>
        <th>ID</th>
        <th>Kode Booking</th>
        <th>Tanggal</th>
        <th>Nominal</th>
        <th>Keterangan</th>
        <th>Parent</th>
        <th>Customer</th>
      </tr>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $nominal = $row['nominal'];
        $total_nominal += $nominal;

        echo "<tr>
                <td>" . htmlspecialchars($row['id']) . "</td>
                <td>" . htmlspecialchars($row['kode_booking']) . "</td>
                <td>" . htmlspecialchars($row['tanggal']) . "</td>
                <td style='mso-number-format:\"\\#\\,\\#\\#0\\.00\"; text-align: right;'>" . number_format($nominal, 2) . "</td>
                <td>" . htmlspecialchars($row['description']) . "</td>
                <td>" . htmlspecialchars($row['id_parent']) . "</td>
                <td>" . htmlspecialchars($row['nama']) . "</td>
              </tr>";
    }

    // Baris total
    echo "<tr style='font-weight: bold; background-color: #e8e8e8;'>
            <td colspan='3' style='text-align: center;'>TOTAL</td>
            <td style='mso-number-format:\"\\#\\,\\#\\#0\\.00\"; text-align: right;'>" . number_format($total_nominal, 2) . "</td>
            <td colspan='3'></td>
          </tr>";
} else {
    echo "<tr><td colspan='7'>Tidak ada data</td></tr>";
}

echo "</table>";

$conn->close();
