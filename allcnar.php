<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config1.php';

// Koneksi utama (cndn)

if ($conn->connect_error) die("Koneksi ke database pertama gagal: " . $conn->connect_error);


// Ambil filter customer dari GET
$filter_customer = isset($_GET['filter_customer']) ? trim($_GET['filter_customer']) : '';
$customer_ids_filtered = [];

// Ambil id customer jika filter digunakan
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

// Query data CN
$sql = "SELECT idn, no_cn_dn, kode_booking, cn, id_cust, tanggal, id_parent FROM cndnar WHERE cn > 0 AND inv IS NULL";
if (!empty($customer_ids_filtered)) {
    $ids_in = implode(",", array_map('intval', $customer_ids_filtered));
    $sql .= " AND id_cust IN ($ids_in)";
}
$sql .= " ORDER BY tanggal DESC, idn";

$result = $conn->query($sql);

// Ambil data customer
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

    foreach ($data as &$row) {
        $row['customer'] = $customer_cache[$row['id_cust']] ?? '(Tidak ditemukan)';
        $total_cn += $row['cn'];
    }
    unset($row);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar CN BELUM DIGUNAKAN > 0</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
<body class="container mt-4">
     <div class="table-container">
        <a href="home.php" class="home-icon1">
            <i class="fas fa-home"></i>
        </a>
        <a href="home.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
    <h3 class="mb-4">DAFTAR CN AR BELUM DIGUNAKAN</h3>

    <form method="GET" class="form-inline mb-3">
          <a href="inarcn.php" class="btn btn-info" style="margin-right: 10px;">
       <i class="fas fa-plus-circle"></i> Tambah CN AP</a>

        <label for="filter_customer" class="mr-2">Filter Customer:</label>
        <input type="text" name="filter_customer" id="filter_customer" class="form-control mr-2"
               value="<?= htmlspecialchars($filter_customer ?? '', ENT_QUOTES) ?>" placeholder="Nama Customer">
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <a href="allcnap.php" class="btn btn-secondary ml-2">Reset</a>
        <a href="exportcnar_excel.php?filter_customer=<?= urlencode($filter_customer) ?>" class="btn btn-success ml-2">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>
          </a>
         <a href="allcndonear.php" class="btn btn-info" style="margin-left: 10px;">
        <i class="fas fa-list"></i> CN Yang Sudah Digunakan
    </a>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>No CN</th>
                    <th>Kode Booking</th>
                    <th>Nominal</th>
                    <th>ID Parent</th>
                    <th>Customer</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data)): ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['idn'] ?? '', ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($row['tanggal'] ?? '', ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($row['no_cn_dn'] ?? '', ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($row['kode_booking'] ?? '', ENT_QUOTES) ?></td>
                            <td class="text-right"><?= number_format($row['cn'] ?? 0, 2) ?></td>
                            <td><?= htmlspecialchars($row['id_parent'] ?? '', ENT_QUOTES) ?></td>
                            <td><?= htmlspecialchars($row['customer'] ?? '', ENT_QUOTES) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="4" class="text-center">TOTAL CN</td>
                        <td class="text-right"><?= number_format($total_cn, 2) ?></td>
                        <td colspan="2"></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">Tidak ada data</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php
$conn->close();

?>
