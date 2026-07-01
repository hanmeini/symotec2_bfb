<?php
require_once 'config1.php';

// Set header untuk download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=export_cn_AR_" . date("Ymd_His") . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Koneksi utama (cndn)

if ($conn->connect_error) die("Koneksi ke database pertama gagal: " . $conn->connect_error);

// Koneksi kedua (customer)

if ($conn->connect_error) die("Koneksi ke database kedua gagal: " . $conn->connect_error);

// Ambil filter dari GET
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';
$customer_ids_filtered = [];

// Ambil ID customer jika ada filter
if (!empty($filter_customer)) {
    $stmt = $conn->prepare("SELECT id FROM customer WHERE customer LIKE ?");
    $like = "%{$filter_customer}%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->bind_result($cust_id);
    while ($stmt->fetch()) {
        $customer_ids_filtered[] = $cust_id;
    }
    $stmt->close();
}

// Query utama
$sql = "SELECT idn, no_cn_dn, kode_booking, cn, id_cust, tanggal, id_parent FROM cndnar WHERE cn > 0 AND inv IS NULL";
if (!empty($customer_ids_filtered)) {
    $ids_in = implode(",", array_map('intval', $customer_ids_filtered));
    $sql .= " AND id_cust IN ($ids_in)";
}
$sql .= " ORDER BY tanggal DESC, idn";

$result = $conn->query($sql);

// Ambil cache customer
$customer_cache = [];
$data = [];
$total_cn = 0;

if ($result && $result->num_rows > 0) {
    $all_customer_ids = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
        $all_customer_ids[$row['id_cust']] = true;
    }

    if (!empty($all_customer_ids)) {
        $ids = implode(",", array_keys($all_customer_ids));
        $q = $conn->query("SELECT id, customer FROM customer WHERE id IN ($ids)");
        while ($r = $q->fetch_assoc()) {
            $customer_cache[$r['id']] = $r['customer'];
        }
    }

    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th>
            <th>Tanggal</th>
            <th>No CN</th>
            <th>Kode Booking</th>
            <th>Nominal</th>
            <th>ID Parent</th>
            <th>Customer</th>
        </tr>";

    foreach ($data as $row) {
        $customer_name = $customer_cache[$row['id_cust']] ?? '(Tidak ditemukan)';
        echo "<tr>
                <td>{$row['idn']}</td>
                <td>{$row['tanggal']}</td>
                <td>{$row['no_cn_dn']}</td>
                <td>{$row['kode_booking']}</td>
                <td align='right'>" . number_format($row['cn'], 2) . "</td>
                <td>{$row['id_parent']}</td>
                <td>{$customer_name}</td>
            </tr>";
        $total_cn += $row['cn'];
    }

    echo "<tr style='font-weight:bold; background:#eee'>
            <td colspan='4' align='center'>TOTAL CN</td>
            <td align='right'>" . number_format($total_cn, 2) . "</td>
            <td colspan='2'></td>
        </tr>";
    echo "</table>";
} else {
    echo "Tidak ada data yang bisa diekspor.";
}

$conn->close();
