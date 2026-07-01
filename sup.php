<?php
require_once 'config1.php';

// Proses pencarian
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$query = "SELECT * FROM sup";
if (!empty($search)) {
    $query .= " WHERE kode LIKE '%$search%' OR nama LIKE '%$search%' OR npwp LIKE '%$search%'";
}
$query .= " ORDER BY id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Supplier</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
.table-container { max-width: 1000px; margin: 0 auto; }
h1 { text-align: center; color: #333; }
.search-container { margin-bottom: 20px; display: flex; justify-content: space-between; }
.search-container input[type="text"] { width: calc(100% - 120px); padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
.search-container button { padding: 10px 20px; background: #5cb85c; color: white; border: none; border-radius: 5px; cursor: pointer; }
.search-container button:hover { background: #4cae4c; }
table { width: 100%; border-collapse: collapse; margin: 20px 0; }
table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
table th { background: #5cb85c; color: #fff; }
tr:nth-child(even) { background: #f9f9f9; }
tr:hover { background: #f1f1f1; }
.add-button, .select-button, .edit-button { padding: 7px 12px; border-radius: 5px; border: none; cursor: pointer; color: #fff; text-decoration: none; display: inline-block; margin-right: 5px; }
.add-button { background: #5cb85c; margin-bottom: 10px; }
.add-button:hover { background: #4cae4c; }
.select-button { background: #0275d8; }
.select-button:hover { background: #025aa5; }
.edit-button { background: #f0ad4e; }
.edit-button:hover { background: #ec971f; }
.home-icon1 { position: absolute; left: 0; top: 0; padding-left: 10px; color: maroon; font-size: 24px; }
.left-icon { position: absolute; right: 0; top: 0; padding-right: 10px; color: maroon; font-size: 24px; }
</style>
<script>
function selectsup(kode, nama) {
    if (window.opener) {
        window.opener.setsSupCode(kode, nama); // Panggil fungsi di halaman utama
        window.close(); // Tutup popup
    } else {
        alert('Tidak dapat mengirim data ke halaman utama.');
    }
}
</script>
</head>
<body>

<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

    <h1>Supplier</h1>

    <a href="supin.php" class="add-button"><i class="fas fa-plus"></i> Tambah Data</a>

    <div class="search-container">
        <form method="GET">
            <input type="text" name="search" placeholder="Kode, Nama, atau NPWP..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit"><i class="fas fa-search"></i> Cari</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kode</th>
                <th>Nama</th>
                <th>Alamat</th>
                <th>NPWP</th>
                
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['kode']) ?></td>
                        <td><?= htmlspecialchars($row['nama']) ?></td>
                        <td><?= htmlspecialchars($row['alamat']) ?></td>
                        <td><?= htmlspecialchars($row['npwp']) ?></td>
                        
                        <td>
                            <button class="select-button" onclick="selectsup('<?= htmlspecialchars($row['kode']) ?>', '<?= htmlspecialchars($row['nama']) ?>')">Pilih</button>
                            <a href="sup_edit.php?id=<?= $row['id'] ?>" class="edit-button"><i class="fas fa-edit"></i> Edit</a>
                            <a href="sup_hapus.php?id=<?= $row['id'] ?>" class="edit-button" style="background-color:red;" onclick="return confirm('Apakah Anda yakin ingin menghapus Supplier ini?')"><i class="fas fa-trash"></i> Hapus</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;">Tidak ada data ditemukan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>

<?php $conn->close(); ?>
