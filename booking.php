<?php
session_start();
if (!isset($_SESSION['username'])) {
    die("Akses ditolak");
}
require_once 'config1.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query transaction codes (j) from transaksiho1
$sql = "SELECT DISTINCT j, MIN(tanggal_transaksi) as tanggal, MIN(cus) as customer 
        FROM transaksiho1";
if ($search !== '') {
    $sql .= " WHERE j LIKE '%" . $conn->real_escape_string($search) . "%'";
}
$sql .= " GROUP BY j ORDER BY tanggal DESC LIMIT 100";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pilih Kode Booking / Transaksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 700px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h3 { margin-top: 0; color: #333; text-align: center; }
        .search-box { display: flex; margin-bottom: 20px; gap: 10px; }
        .search-box input { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .search-box button { padding: 10px 15px; background: #4CAF50; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f2f2f2; font-weight: bold; }
        tr:hover { background: #eef9f0; cursor: pointer; }
        .btn-select { padding: 5px 10px; background: #007bff; color: #fff; border: none; border-radius: 4px; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h3>Pilih Kode Booking / Transaksi</h3>
    <form method="GET" class="search-box">
        <input type="text" name="search" placeholder="Cari Kode Transaksi..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Cari</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr onclick="selectCode('<?= htmlspecialchars($row['j']) ?>')">
                        <td><strong><?= htmlspecialchars($row['j']) ?></strong></td>
                        <td><?= htmlspecialchars($row['tanggal']) ?></td>
                        <td><button class="btn-select">Pilih</button></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center; color: #777;">Data tidak ditemukan</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function selectCode(code) {
    if (window.opener && !window.opener.closed) {
        window.opener.setbookingCode(code);
    }
    window.close();
}
</script>
</body>
</html>
<?php $conn->close(); ?>
