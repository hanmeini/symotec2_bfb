<?php
require_once 'config1.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"dn_AR_" . date('Ymd') . ".xls\"");




$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';
$customer_ids_filtered = [];

if (!empty($filter_customer)) {
    $stmt = $conn->prepare("SELECT id FROM customer WHERE customer LIKE ?");
    $like = "%{$filter_customer}%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $stmt->bind_result($id);
    while ($stmt->fetch()) {
        $customer_ids_filtered[] = $id;
    }
    $stmt->close();
}

$sql = "SELECT idn, no_cn_dn, kode_booking, dn, id_cust, tanggal, id_parent FROM cndnar WHERE dn > 0 AND inv IS NULL";
if (!empty($customer_ids_filtered)) {
    $ids_in = implode(",", array_map('intval', $customer_ids_filtered));
    $sql .= " AND id_cust IN ($ids_in)";
}
$sql .= " ORDER BY tanggal DESC, idn";

$result = $conn->query($sql);

$customer_cache = [];
$data = [];
$total_dn = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    $ids = array_column($data, 'id_cust');
    if (!empty($ids)) {
        $ids_in = implode(",", array_unique($ids));
        $q = $conn->query("SELECT id, customer FROM customer WHERE id IN ($ids_in)");
        while ($r = $q->fetch_assoc()) {
            $customer_cache[$r['id']] = $r['customer'];
        }
    }
}

echo "<table border='1'>";
echo "<tr><th>ID</th><th>Tanggal</th><th>No DN</th><th>Kode Booking</th><th>Nominal</th><th>ID Parent</th><th>Customer</th></tr>";

foreach ($data as $row) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['idn']) . "</td>";
    echo "<td>" . htmlspecialchars($row['tanggal']) . "</td>";
    echo "<td>" . htmlspecialchars($row['no_cn_dn']) . "</td>";
    echo "<td>" . htmlspecialchars($row['kode_booking']) . "</td>";
    echo "<td>" . number_format($row['dn'], 2) . "</td>";
    echo "<td>" . htmlspecialchars($row['id_parent']) . "</td>";
    echo "<td>" . htmlspecialchars($customer_cache[$row['id_cust']] ?? '') . "</td>";
    echo "</tr>";
    $total_dn += $row['dn'];
}
echo "<tr><td colspan='4'><strong>TOTAL</strong></td><td>" . number_format($total_dn, 2) . "</td><td colspan='2'></td></tr>";
echo "</table>";

$conn->close();
?>
