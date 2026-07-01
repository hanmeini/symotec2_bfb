<?php














require_once 'config1.php';

// Proses penyimpanan data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = $conn->real_escape_string($_POST['brand']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);

    $sql = "INSERT INTO brand_b (brand, deskripsi) VALUES ('$brand', '$deskripsi')";
    if ($conn->query($sql) === TRUE) {
        $message = "Data berhasil disimpan.";
    } else {
        $message = "Error: " . $conn->error;
    }
}

// Ambil data dari tabel
$sql = "SELECT * FROM brand_b ORDER BY id_brand DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Brand</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f7f9fc; }
h1 { text-align: center; color: #333; }
.form-container, .table-container { max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
form { display: flex; flex-direction: column; gap: 15px; }
input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
button { background-color: #007bff; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; }
button:hover { background-color: #0056b3; }
.edit-button { padding: 5px 10px; background: #f0ad4e; color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; }
.edit-button:hover { background: #ec971f; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
table th, table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
table th { background-color: #007bff; color: white; }
table tr:nth-child(even) { background-color: #f2f2f2; }
.message { text-align: center; color: green; margin-top: 10px; }
.home-icon, .left-icon { font-size: 24px; color: maroon; position: absolute; top: 0; padding: 10px; }
.home-icon { left: 0; }
.left-icon { right: 0; }
</style>
</head>
<body>

<div class="table-container">
<a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
<a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<h1>Data Brand</h1>

<div class="form-container">
<h2>Tambah Data Brand</h2>
<form method="POST">
    <input type="text" name="brand" placeholder="Masukkan Nama Brand" required>
    <textarea name="deskripsi" placeholder="Masukkan Deskripsi" rows="4"></textarea>
    <button type="submit">Simpan</button>
</form>
<?php if (isset($message)) : ?>
    <p class="message"><?php echo $message; ?></p>
<?php endif; ?>
</div>

<div class="table-container">
<h2>Daftar Data Brand</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Brand</th>
            <th>Deskripsi</th>
            <th>Dibuat Pada</th>
            <th>Diperbarui Pada</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($result->num_rows > 0) : ?>
            <?php while ($row = $result->fetch_assoc()) : ?>
                <tr>
                    <td><?php echo $row['id_brand']; ?></td>
                    <td><?php echo htmlspecialchars($row['brand']); ?></td>
                    <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td><?php echo $row['updated_at']; ?></td>
                    <td>
                        <a href="brand_edit.php?id=<?php echo $row['id_brand']; ?>" class="edit-button">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else : ?>
            <tr>
                <td colspan="6" style="text-align:center;">Tidak ada data.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
</div>

</div>
</body>
</html>

<?php
$conn->close();
?>
