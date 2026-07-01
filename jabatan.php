<?php











require_once 'config1.php';

// Proses simpan/update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $jabatan = trim($_POST['jabatan']);
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if (empty($jabatan)) {
        echo "<script>alert('Jabatan harus diisi');</script>";
    } else {
        if ($id_edit == 0) {
            $cek = $conn->prepare("SELECT idj FROM jabatan WHERE jabatan = ?");
            $cek->bind_param("s", $jabatan);
            $cek->execute();
            $cek->store_result();
            if ($cek->num_rows > 0) {
                echo "<script>alert('Jabatan sudah ada');</script>";
            } else {
                $stmt = $conn->prepare("INSERT INTO jabatan (jabatan) VALUES (?)");
                $stmt->bind_param("s", $jabatan);
                if ($stmt->execute()) {
                    echo "<script>alert('Data berhasil ditambahkan');</script>";
                } else {
                    echo "<script>alert('Gagal menambahkan data');</script>";
                }
                $stmt->close();
            }
            $cek->close();
        } else {
            $stmt = $conn->prepare("UPDATE jabatan SET jabatan = ? WHERE idj = ?");
            $stmt->bind_param("si", $jabatan, $id_edit);
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
    $stmt = $conn->prepare("SELECT idj, jabatan FROM jabatan WHERE idj = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($eid, $ejabatan);
    if ($stmt->fetch()) {
        $editData = [
            'idj' => $eid,
            'jabatan' => $ejabatan
        ];
    }
    $stmt->close();
}

// Ambil semua data
$result = $conn->query("SELECT idj, jabatan FROM jabatan");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input dan Tampil Data Jabatan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; margin: 0; padding: 0; }
        .container { width: 80%; max-width: 800px; margin: 30px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #333; }
        .form-input { margin-bottom: 20px; }
        .form-input label { font-weight: bold; display: block; margin-bottom: 5px; }
        .form-input input[type="text"] { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .form-input input[type="submit"] { padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .form-input input[type="submit"]:hover { background-color: #218838; }
        .table-data { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-data th, .table-data td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        .table-data th { background-color: #4CAF50; color: white; }
        .table-data tr:nth-child(even) { background-color: #f2f2f2; }
        .action-button { padding: 5px 10px; background-color: #007bff; color: white; border-radius: 4px; text-decoration: none; }
        .action-button:hover { background-color: #0056b3; }
        .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 { left: 10px; }
        .left-icon { right: 10px; }
    </style>
    <script>
        function confirmEdit() {
            alert("Perhatian: Pengeditan hanya untuk pembetulan atas kesalahan penulisan!");
            return confirm("Pengeditan hanya boleh dilakukan untuk koreksi penulisan. Apakah Anda yakin?");
        }
    </script>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
    <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
</div>

<div class="container">
    <h1><?= $editData ? 'Edit' : '' ?> Jabatan</h1>
    <form method="POST" class="form-input">
        <label for="jabatan">Input Jabatan:</label>
        <input type="text" id="jabatan" name="jabatan" value="<?= $editData ? htmlspecialchars($editData['jabatan']) : '' ?>" required>
        <?php if ($editData): ?>
            <input type="hidden" name="id_edit" value="<?= $editData['idj'] ?>">
        <?php endif; ?>
        <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
    </form>

    <h2>Data Jabatan</h2>
    <table class="table-data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Jabatan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['idj'] ?></td>
                    <td><?= htmlspecialchars($row['jabatan']) ?></td>
                    <td>
                        <a class="action-button" href="?edit=<?= $row['idj'] ?>" onclick="return confirmEdit();">Edit</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">Tidak ada data</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php $conn->close(); ?>
