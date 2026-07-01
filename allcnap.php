<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config1.php';

/* ================= KONEKSI DATABASE ================= */






/* ================= FILTER SUPPLIER ================= */
$filter_supplier = isset($_GET['filter_supplier'])
    ? trim($_GET['filter_supplier'])
    : '';

/* ================= QUERY DATA ================= */
$sql = "
    SELECT
        c.idn,
        c.no_cn_dn,
        c.kode_booking,
        c.cn,
        c.sup,
        c.tanggal,
        c.id_parent,
        c.description,
        s.nama AS supplier
    FROM cndn c
    LEFT JOIN sup s ON c.sup = s.id
    WHERE c.cn > 0
    AND c.inv IS NULL
";

/* ================= FILTER ================= */
$params = [];
$types  = '';

if (!empty($filter_supplier)) {

    $sql .= " AND s.nama LIKE ? ";

    $params[] = "%{$filter_supplier}%";
    $types    .= 's';
}

$sql .= " ORDER BY c.tanggal DESC, c.idn DESC ";

/* ================= PREPARE ================= */
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare gagal: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

/* ================= AMBIL DATA ================= */
$data = [];
$total_cn = 0;

while ($row = $result->fetch_assoc()) {

    $data[] = $row;

    $total_cn += (float)$row['cn'];
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>DAFTAR CN AP BELUM DIGUNAKAN</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
body{
    font-family:Arial,sans-serif;
    background:#f9f9f9;
    margin:20px;
    color:#333;
}

h3{
    text-align:center;
    margin-bottom:20px;
    color:#28a745;
}

table{
    background:white;
}

th{
    background:#28a745;
    color:white;
    text-align:center;
    vertical-align:middle !important;
}

td{
    vertical-align:middle !important;
}

.home-icon1{
    position:absolute;
    left:10px;
    top:10px;
    color:maroon;
    font-size:24px;
}

.left-icon{
    position:absolute;
    right:10px;
    top:10px;
    color:maroon;
    font-size:24px;
}

.form-inline{
    gap:10px;
}

.total-row{
    background:#f1f1f1;
    font-weight:bold;
}

@media(max-width:768px){

    body{
        margin:10px;
    }

    table{
        font-size:12px;
    }

    .form-inline{
        display:block;
    }

    .form-inline > *{
        margin-bottom:10px;
    }
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

</div>

<h3>DAFTAR CN AP BELUM DIGUNAKAN</h3>

<form method="GET" class="form-inline mb-3">

    <a href="inapcn.php" class="btn btn-info">
        <i class="fas fa-plus-circle"></i> Tambah CN AP
    </a>

    <label for="filter_supplier">
        Filter Supplier:
    </label>

    <input
        type="text"
        name="filter_supplier"
        id="filter_supplier"
        class="form-control"
        placeholder="Nama Supplier"
        value="<?= htmlspecialchars($filter_supplier, ENT_QUOTES) ?>"
    >

    <button type="submit" class="btn btn-primary">
        Tampilkan
    </button>

    <a href="allcnap.php" class="btn btn-secondary">
        Reset
    </a>

    <a
        href="exportcn_excel.php?filter_supplier=<?= urlencode($filter_supplier) ?>"
        class="btn btn-success"
    >
        <i class="fas fa-file-excel"></i> Export Excel
    </a>

    <a href="allcndone.php" class="btn btn-dark">
        <i class="fas fa-list"></i> CN Yang Sudah Digunakan
    </a>

</form>

<div class="table-responsive">

<table class="table table-bordered table-striped table-sm">

    <thead>
        <tr>
            <th>ID</th>
            <th>Tanggal</th>
            <th>No CN</th>
            <th>Nominal CN</th>
            <th>Supplier</th>
            <th>Description</th>
            <th>ID Parent</th>
        </tr>
    </thead>

    <tbody>

    <?php if (!empty($data)): ?>

        <?php foreach ($data as $row): ?>

            <tr>

                <td class="text-center">
                    <?= htmlspecialchars($row['idn'] ?? '', ENT_QUOTES) ?>
                </td>

                <td class="text-center">
                    <?= htmlspecialchars($row['tanggal'] ?? '', ENT_QUOTES) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['no_cn_dn'] ?? '', ENT_QUOTES) ?>
                </td>

                <td class="text-right">
                    <?= number_format((float)$row['cn'], 2) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['supplier'] ?? '', ENT_QUOTES) ?>
                </td>

                <td>
                    <?= htmlspecialchars($row['description'] ?? '', ENT_QUOTES) ?>
                </td>

                <td class="text-center">
                    <?= htmlspecialchars($row['id_parent'] ?? '', ENT_QUOTES) ?>
                </td>

            </tr>

        <?php endforeach; ?>

        <tr class="total-row">

            <td colspan="3" class="text-center">
                TOTAL CN
            </td>

            <td class="text-right">
                <?= number_format($total_cn, 2) ?>
            </td>

            <td colspan="3"></td>

        </tr>

    <?php else: ?>

        <tr>
            <td colspan="7" class="text-center">
                Tidak ada data
            </td>
        </tr>

    <?php endif; ?>

    </tbody>

</table>

</div>

</body>
</html>

<?php
$conn->close();
?>