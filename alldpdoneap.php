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

// Koneksi DB





// Ambil filter customer dari GET
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';

// Query dasar
$where = "inv IS NOT NULL AND inv != '' AND nominal > 0";

if ($filter_customer !== '') {
    // Escape untuk LIKE
    $filter_customer_esc = $conn->real_escape_string($filter_customer);

    // Ambil cust_id yang sesuai nama customer dari tabel customer
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
        // Jika tidak ada customer cocok, buat kondisi yang tidak pernah terpenuhi agar tidak ada data tampil
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

$total_nominal = 0;
$total_digunakan = 0;
$total_sisa = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Daftar Titipan</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
<style>
body { font-family: Arial, sans-serif; background-color: #f9f9f9; color: #333; margin: 20px; }
h1 { text-align: center; color: #4CAF50; }
form { text-align: center; margin-bottom: 20px; }
label { font-weight: bold; margin-right: 10px; }
input[type="text"] { padding: 6px; width: 200px; }
button, a.btn { padding: 6px 12px; margin-left: 5px; border-radius: 4px; text-decoration: none; color: white; }
button { background-color: #4CAF50; border: none; cursor: pointer; }
button:hover { background-color: #45a049; }
a.btn-secondary { background-color: #6c757d; }
a.btn-secondary:hover { background-color: #5a6268; }
a.btn-success { background-color: #28a745; }
a.btn-success:hover { background-color: #218838; }
table { width: 100%; border-collapse: collapse; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
th, td { border: 1px solid #ddd; padding: 10px; text-align: right; }
th { background-color: #4CAF50; color: white; }
th:first-child, td:first-child { text-align: center; }
td.action { text-align: center; }
tr:nth-child(even) { background-color: #f2f2f2; }
tr:hover { background-color: #ddd; }

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
      
@media (max-width: 768px) {
    th, td { padding: 6px; font-size: 12px; }
    input[type="text"] { width: 140px; }
}
</style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1">
            <i class="fas fa-home"></i>
        </a>
        <a href="daftartitipan.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
<h1>Titipan yang sudah digunakan</h1>

<form method="GET" class="form-inline mb-3" style="text-align:center;">
    <label for="filter_customer">Filter Customer:</label>
    <input type="text" name="filter_customer" id="filter_customer" value="<?= htmlspecialchars($filter_customer ?? '') ?>" placeholder="Nama Customer" />
    <button type="submit">Tampilkan</button>
    <a href="alldpdone.php" class="btn btn-secondary">Reset</a>
    <a href="export_titipandoneap.php?filter_customer=<?= urlencode($filter_customer) ?>" class="btn btn-success">Export Excel</a>
</form>
 
<?php
if ($result_pph23 && $result_pph23->num_rows > 0) {
    echo "<table>
        <thead>
            <tr>
                <th>ID</th>
               
                <th>Tanggal</th>
                <th>Awal Titipan</th>
                <th>Keterangan</th>
                <th>INV</th>
                <th>Digunakan</th>
                <th>Parent</th>
                <th>ID Sisa Titipan</th>
                <th>Sisa Titipan</th>
                <th>Customer</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>";

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
        $total_sisa += $sisa_nominal;

        echo "<tr>
                <td>" . htmlspecialchars($row['id'] ?? '') . "</td>
               
                <td>" . htmlspecialchars($row['tanggal'] ?? '') . "</td>
                <td>" . number_format($row['nominal'], 2) . "</td>
                <td>" . htmlspecialchars($row['description'] ?? '') . "</td>
                <td>" . htmlspecialchars($row['inv'] ?? '') . "</td>
                         <td>" . number_format($sisa_nominal, 2) . "</td>
                <td>" . htmlspecialchars($row['id_parent'] ?? '') . "</td>
                <td>" . htmlspecialchars($idp ?? '') . "</td>
      
                 <td>" . number_format($used_nominal, 2) . "</td>
                <td style='text-align:left;'>" . htmlspecialchars($customer_name ?? '') . "</td>
                <td class='action'>
                    <button onclick=\"window.open('detail_titipan.php?id=" . intval($row['id']) . "', '_blank', 'width=800,height=600,scrollbars=yes')\">
                        <i class='fas fa-eye'></i> Detail
                    </button>
                </td>
            </tr>";
    }

    $total_sisa = $total_nominal - $total_digunakan;
    echo "<tr style='font-weight:bold; background:#f0f0f0;'>
            <td colspan='2'>TOTAL</td>
            <td>" . number_format($total_nominal, 2) . "</td>
            <td colspan='2'></td>
         <td>" . number_format($total_sisa, 2) . "</td>
            <td></td>
            <td></td>
            
               <td>" . number_format($total_digunakan, 2) . "</td>
            <td colspan='2'></td>
          </tr>";

    echo "</tbody></table>";
} else {
    echo "<p style='text-align:center;'>Tidak ada data titipan yang sesuai filter.</p>";
}

$conn->close();
?>

</body>
</html>
