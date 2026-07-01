<?php











require_once 'config1.php';

// Proses simpan/update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_cabang = trim($_POST['nama_cabang']);
    $alamat = trim($_POST['alamat']);
    $telp = trim($_POST['telp']);
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if (empty($nama_cabang) || empty($alamat) || empty($telp)) {
        echo "<script>alert('Semua kolom harus diisi');</script>";
    } else {
        if ($id_edit == 0) {
            $stmt = $conn->prepare("INSERT INTO location (nama_cabang, alamat, telp) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nama_cabang, $alamat, $telp);
            if ($stmt->execute()) {
                echo "<script>alert('Data berhasil ditambahkan');</script>";
            } else {
                echo "<script>alert('Gagal menambahkan data');</script>";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE location SET nama_cabang = ?, alamat = ?, telp = ? WHERE idl = ?");
            $stmt->bind_param("sssi", $nama_cabang, $alamat, $telp, $id_edit);
            if ($stmt->execute()) {
                echo "<script>alert('Data berhasil diperbarui'); window.location='" . $_SERVER['PHP_SELF'] . "';</script>";
            } else {
                echo "<script>alert('Gagal memperbarui data');</script>";
            }
            $stmt->close();
        }
    }
}

// Mode edit
$editData = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT idl, nama_cabang, alamat, telp FROM location WHERE idl = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($idl, $nc, $almt, $tlp);
    if ($stmt->fetch()) {
        $editData = [
            'idl' => $idl,
            'nama_cabang' => $nc,
            'alamat' => $almt,
            'telp' => $tlp
        ];
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM location");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input dan Tampil Data Cabang</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; }
        .container { width: 80%; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        form { margin-bottom: 20px; }
        label { display: block; margin-top: 10px; }
        input[type="text"], textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { margin-top: 15px; background-color: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        input[type="submit"]:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .action-button { background-color: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; }
        .action-button:hover { background-color: #0056b3; }
        .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; 
    </style>
</head>
<body>
    <div class="table-container">
    <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <a href="daftaruser.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
<div class="container">
    <h1><?= $editData ? 'Edit' : 'Input' ?> Data Cabang</h1>
    <form method="POST">
        <label>Nama Cabang:</label>
        <input type="text" name="nama_cabang" value="<?= $editData ? htmlspecialchars($editData['nama_cabang']) : '' ?>" required>

        <label>Alamat:</label>
        <textarea name="alamat" rows="3" required><?= $editData ? htmlspecialchars($editData['alamat']) : '' ?></textarea>

        <label>Telepon:</label>
        <input type="text" name="telp" value="<?= $editData ? htmlspecialchars($editData['telp']) : '' ?>" required>

        <?php if ($editData): ?>
            <input type="hidden" name="id_edit" value="<?= $editData['idl'] ?>">
        <?php endif; ?>
        <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
    </form>

    <h2>Data Cabang</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Cabang</th>
                <th>Alamat</th>
                <th>Telepon</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['idl'] ?></td>
                    <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                    <td><?= htmlspecialchars($row['alamat']) ?></td>
                    <td><?= htmlspecialchars($row['telp']) ?></td>
                    <td><a class="action-button" href="?edit=<?= $row['idl'] ?>">Edit</a></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">Tidak ada data</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php $conn->close(); ?>
