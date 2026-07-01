<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config1.php';

/* ================= KONEKSI ================= */




/* ================= FILTER ================= */
$filter = $_GET['filter'] ?? '';
$filter = trim($filter);

$filter_sql = "";

if ($filter != "") {
    $escaped = $conn->real_escape_string(strtolower($filter));

    $filter_sql = " AND (
        LOWER(t.description) LIKE '%$escaped%' OR
        LOWER(t.sup) LIKE '%$escaped%' OR
        LOWER(s.nama) LIKE '%$escaped%'
    )";
}

/* ================= QUERY ================= */
$sql = "
SELECT 
    t.id,
    t.tanggal,
    t.nominal,
    t.description,
    t.id_parent,
    t.sup,
    s.nama AS nama_supplier
FROM titipanap t
LEFT JOIN sup s 
ON TRIM(t.sup) COLLATE utf8mb4_unicode_ci 
   = TRIM(s.kode) COLLATE utf8mb4_unicode_ci
WHERE (t.inv IS NULL OR t.inv = '')
AND t.nominal > 0
$filter_sql
ORDER BY t.id DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Query error: " . $conn->error);
}

/* ================= DATA ================= */
$data = [];
$total_nominal = 0;

while ($row = $result->fetch_assoc()) {

    $nominal = (float)$row['nominal'];
    $total_nominal += $nominal;

    $data[] = [
        'id' => $row['id'],
        'tanggal' => $row['tanggal'],
        'nominal' => $nominal,
        'description' => $row['description'],
        'id_parent' => $row['id_parent'],
        'sup' => $row['sup'],
        'nama_supplier' => $row['nama_supplier'] ?: '(Tidak ditemukan)'
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Titipan AP Belum Digunakan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
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
    </style>
</head>
<body class="container mt-4">

    <!-- Navigasi -->
    <div class="table-container">
        <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
        <a href="daftartitipan.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    </div>

    <h3 class="mb-4">Daftar Titipan AP Belum Digunakan</h3>

    <!-- Form Filter -->
    <form method="GET" class="form-inline mb-3">
        
         <a href="dpap.php" class="btn btn-info" style="margin-right: 10px;">
       <i class="fas fa-plus-circle"></i> Tambah Titipan AP</a>


        <label for="filter_customer" class="mr-2">Filter Customer:</label>
        <input type="text" name="filter_customer" id="filter_customer" class="form-control mr-2"
               value="<?= htmlspecialchars($filter_customer ?? '') ?>" placeholder="Nama Customer">
        <button type="submit" class="btn btn-primary">Tampilkan</button>
        <a href="alldpap.php" class="btn btn-secondary ml-2">Reset</a>
        <a href="export_titipanap.php?filter_customer=<?= urlencode($filter_customer ?? '') ?>"
           class="btn btn-success ml-2">Export Excel</a>
          <a href="alldpdoneap.php" class="btn btn-info" style="margin-left: 10px;">
        <i class="fas fa-list"></i> All Titipan Yang Sudah Digunakan
    </a>
    </form>

    <!-- Tabel Data -->
    <div class="table-responsive">
        <table class="table table-bordered table-striped table-sm">
            <thead class="thead-dark">
                <tr>
                    <th>ID</th>
                   
                    <th>Tanggal</th>
                    <th>Nominal</th>
                    <th>Keterangan</th>
                    <th>Parent</th>
                    <th>Supplier</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($data)): ?>
                    <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id'] ?? '') ?></td>
                            
                            <td><?= htmlspecialchars($row['tanggal'] ?? '') ?></td>
                            <td class="text-right"><?= number_format($row['nominal'] ?? 0, 2) ?></td>
                            <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['id_parent'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['nama_supplier'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="font-weight-bold bg-light">
                        <td colspan="3" class="text-center">TOTAL</td>
                        <td class="text-right"><?= number_format($total_nominal, 2) ?></td>
                        <td colspan="3"></td>
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
