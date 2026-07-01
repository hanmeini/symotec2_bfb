<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';

// Koneksi DB




// Ambil filter customer dari GET
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';

// Query dasar
$where = "inv IS NOT NULL AND inv != '' AND nominal > 0";

if ($filter_customer !== '') {
    $filter_customer_esc = $conn->real_escape_string($filter_customer);

    $sql_cust = "SELECT id FROM customer WHERE customer LIKE '%$filter_customer_esc%'";
    $res_cust = $conn->query($sql_cust);

    $cust_ids = [];
    if ($res_cust && $res_cust->num_rows > 0) {
        while ($row_cust = $res_cust->fetch_assoc()) {
            $cust_ids[] = intval($row_cust['id']);
        }
    }

    if (count($cust_ids) > 0) {
        $ids_str = implode(',', $cust_ids);
        $where .= " AND cust_id IN ($ids_str)";
    } else {
        $where .= " AND 0";
    }
}

$sql_pph23 = "
    SELECT id, kode_booking, tanggal, nominal, description, inv, id_parent, cust_id
    FROM titipanap
    WHERE $where
    ORDER BY id
";

$result_pph23 = $conn->query($sql_pph23);

if (!$result_pph23) {
    die("Query gagal: " . $conn->error);
}

// Header untuk file Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=TitipanAP_Sudah_Digunakan_" . date('Ymd') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<table border='1'>";
echo "<tr style='background-color:#4CAF50; color:white;'>
        <th>ID</th>
        <th>Kode Booking</th>
        <th>Tanggal</th>
        <th>Awal Titipan</th>
        <th>Keterangan</th>
        <th>INV</th>
        <th>Digunakan</th>
        <th>Parent</th>
        <th>ID Sisa Titipan</th>
        <th>Sisa Titipan</th>
        <th>Customer</th>
      </tr>";

$total_nominal = 0;
$total_digunakan = 0;

while ($row = $result_pph23->fetch_assoc()) {
    $customer_name = 'Tidak Ditemukan';

    $sql_customer = "SELECT customer FROM customer WHERE id = " . intval($row['cust_id']);
    $result_customer = $conn->query($sql_customer);
    if ($result_customer && $result_customer->num_rows > 0) {
        $customer_row = $result_customer->fetch_assoc();
        $customer_name = $customer_row['customer'];
    }

    $idp = null;
    $used_nominal = 0;

    $sql_used = "SELECT id, nominal FROM titipanap WHERE id_parent = " . intval($row['id']) . " LIMIT 1";
    $result_used = $conn->query($sql_used);
    if ($result_used && $used_row = $result_used->fetch_assoc()) {
        $used_nominal = floatval($used_row['nominal']);
        $idp = intval($used_row['id']);
    }

    $sisa_nominal = floatval($row['nominal']) - $used_nominal;

    $total_nominal += floatval($row['nominal']);
    $total_digunakan += $used_nominal;

    echo "<tr>
            <td>" . $row['id'] . "</td>
            <td>" . htmlspecialchars($row['kode_booking']) . "</td>
            <td>" . $row['tanggal'] . "</td>
            <td>" . number_format($row['nominal'], 2) . "</td>
            <td>" . htmlspecialchars($row['description']) . "</td>
            <td>" . htmlspecialchars($row['inv']) . "</td>
          <td>" . number_format($sisa_nominal, 2) . "</td>
            <td>" . htmlspecialchars($row['id_parent']) . "</td>
            <td>" . htmlspecialchars($idp) . "</td>
           
               <td>" . number_format($used_nominal, 2) . "</td>
            <td>" . htmlspecialchars($customer_name) . "</td>
          </tr>";
}

$total_sisa = $total_nominal - $total_digunakan;

echo "<tr style='font-weight:bold; background:#f0f0f0;'>
        <td colspan='3'>TOTAL</td>
        <td>" . number_format($total_nominal, 2) . "</td>
        <td></td>
        <td></td>
    <td>" . number_format($total_sisa, 2) . "</td>
        <td></td>
        <td></td>
      
              <td>" . number_format($total_digunakan, 2) . "</td>
        <td></td>
      </tr>";

echo "</table>";

$conn->close();

