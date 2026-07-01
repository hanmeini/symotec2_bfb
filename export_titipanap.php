<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config1.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=titipanAP_belum_digunakan.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Koneksi utama (titipanap)

if ($conn->connect_error) die("Koneksi ke database pertama gagal: " . $conn->connect_error);

// Koneksi kedua (customer)

if ($conn->connect_error) die("Koneksi ke database kedua gagal: " . $conn->connect_error);

// Ambil filter customer dari GET
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';
$customer_ids_filtered = [];

// Jika ada filter, ambil ID customer yang sesuai dari conn2
if (!empty($filter_customer)) {
    $stmt = $conn->prepare("SELECT id FROM customer WHERE customer LIKE ?");
    $like = "%{$filter_customer}%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result_cust = $stmt->get_result();
    while ($row = $result_cust->fetch_assoc()) {
        $customer_ids_filtered[] = $row['id'];
    }
    $stmt->close();
}

// Query data dari titipanap di conn
$sql = "
    SELECT id, kode_booking, tanggal, nominal, description, id_parent, cust_id
    FROM titipanap
    WHERE (inv = '' OR inv IS NULL)
      AND nominal > 0
";
if (!empty($customer_ids_filtered)) {
    $ids_in = implode(",", array_map('intval', $customer_ids_filtered));
    $sql .= " AND cust_id IN ($ids_in)";
}
$sql .= " ORDER BY id, cust_id";

$result = $conn->query($sql);

// Cache data customer dari conn2
$customer_cache = [];
$data = [];
$total_nominal = 0;

if ($result && $result->num_rows > 0) {
    // Ambil semua cust_id yang akan dicari
    $all_customer_ids = [];
    while ($row = $result->fetch_assoc()) {
        $all_customer_ids[$row['cust_id']] = true;
        $data[] = $row;
    }

    if (!empty($all_customer_ids)) {
        $ids = implode(",", array_keys($all_customer_ids));
        $q = $conn->query("SELECT id, customer FROM customer WHERE id IN ($ids)");
        while ($r = $q->fetch_assoc()) {
            $customer_cache[$r['id']] = $r['customer'];
        }
    }

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

    foreach ($data as $row) {
        $nominal = $row['nominal'];
        $total_nominal += $nominal;
        $customer_name = $customer_cache[$row['cust_id']] ?? '(Tidak ditemukan)';

        echo "<tr>
                <td>" . htmlspecialchars($row['id'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['kode_booking'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['tanggal'] ?? '') . "</td>
                <td style='mso-number-format:\"\\#\\,\\#\\#0\\.00\"; text-align: right;'>" . number_format($nominal, 2) . "</td>
                <td>" . htmlspecialchars($row['description'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['id_parent'] ?? '') . "</td>
                <td>" . htmlspecialchars($customer_name ?? '') . "</td>
              </tr>";
    }

    // Baris total
    echo "<tr style='font-weight: bold; background-color: #e8e8e8;'>
            <td colspan='3' style='text-align: center;'>TOTAL</td>
            <td style='mso-number-format:\"\\#\\,\\#\\#0\\.00\"; text-align: right;'>" . number_format($total_nominal, 2) . "</td>
            <td colspan='3'></td>
          </tr>";
    echo "</table>";
} else {
    echo "<table border='1'>";
    echo "<tr><td colspan='7'>Tidak ada data</td></tr>";
    echo "</table>";
}

$conn->close();
?>
