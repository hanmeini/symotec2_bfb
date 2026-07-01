<?php



require_once 'config1.php';

// Ambil daftar bank dan cabang
$banks = $conn->query("SELECT id_bank, n_bank FROM bank");
$branches = $conn->query("SELECT idl, nama_cabang FROM location");

// FILTER
$filter_bank = isset($_GET['bank']) ? intval($_GET['bank']) : 0;
$filter_cabang = isset($_GET['cabang']) ? intval($_GET['cabang']) : 0;

$where = "1=1";
if ($filter_bank) $where .= " AND bc.id_bank = $filter_bank";
if ($filter_cabang) $where .= " AND bc.idl = $filter_cabang";

// MODE EDIT
$editData = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT id_bc, id_bank, idl, norek, status FROM bank_cabang WHERE id_bc = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($id_bc, $id_bank, $idl, $norek, $status);
    if ($stmt->fetch()) {
        $editData = compact('id_bc', 'id_bank', 'idl', 'norek', 'status');
    }
    $stmt->close();
}

// DELETE → SET NONAKTIF
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $conn->query("UPDATE bank_cabang SET status = 'nonaktif' WHERE id_bc = $del_id");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// SIMPAN/UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_bank = intval($_POST['id_bank']);
    $idl = intval($_POST['idl']);
    $norek = trim($_POST['norek']);
    $status = $_POST['status'] ?? 'aktive';
    $tanggal = date("Y-m-d H:i:s");
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if ($id_bank && $idl && !empty($norek)) {
        if ($id_edit == 0) {
            $stmt = $conn->prepare("INSERT INTO bank_cabang (id_bank, idl, norek, status, tanggal) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $id_bank, $idl, $norek, $status, $tanggal);
        } else {
            $stmt = $conn->prepare("UPDATE bank_cabang SET id_bank = ?, idl = ?, norek = ?, status = ? WHERE id_bc = ?");
            $stmt->bind_param("iissi", $id_bank, $idl, $norek, $status, $id_edit);
        }

        if ($stmt->execute()) {
            echo "<script>alert('Data berhasil disimpan'); window.location='" . $_SERVER['PHP_SELF'] . "';</script>";
        } else {
            echo "<script>alert('Gagal menyimpan data');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Semua kolom harus diisi');</script>";
    }
}

// AMBIL DATA
$result = $conn->query("
    SELECT bc.id_bc, b.n_bank, l.nama_cabang, bc.norek, bc.status, bc.tanggal
    FROM bank_cabang bc
    JOIN bank b ON bc.id_bank = b.id_bank
    JOIN location l ON bc.idl = l.idl
    WHERE $where
    ORDER BY bc.tanggal DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Bank Cabang</title>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial; background-color: #f4f4f9; }
        .container { width: 90%; max-width: 900px; margin: 30px auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { text-align: center; }

        form { margin-bottom: 30px; }
        label { display: block; margin-top: 10px; }
        select, input[type="text"] {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;
        }
        input[type="submit"], .btn {
            margin-top: 15px; background-color: #28a745; color: white;
            padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;
        }
        .btn:hover { background-color: #218838; }

        .btn-secondary { background-color: #007bff; }
        .btn-secondary:hover { background-color: #0056b3; }

        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #a71d2a; }

        table { width: 100%; border-collapse: collapse; margin-top: 30px; }
        th, td { border: 1px solid #ddd; padding: 10px; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }

        .filter { margin-bottom: 20px; padding: 10px; background: #eef; border-radius: 5px; }
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
    <h1><?= $editData ? 'Edit' : 'Tambah' ?> Bank Cabang</h1>

    <form method="POST">
        <label>Nama Bank:</label>
        <select name="id_bank" required>
            <option value="">-- Pilih Bank --</option>
            <?php $banks->data_seek(0); while ($b = $banks->fetch_assoc()): ?>
                <option value="<?= $b['id_bank'] ?>" <?= ($editData && $editData['id_bank'] == $b['id_bank']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['n_bank']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Cabang:</label>
        <select name="idl" required>
            <option value="">-- Pilih Cabang --</option>
            <?php $branches->data_seek(0); while ($c = $branches->fetch_assoc()): ?>
                <option value="<?= $c['idl'] ?>" <?= ($editData && $editData['idl'] == $c['idl']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nama_cabang']) ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>No. Rekening:</label>
        <input type="text" name="norek" value="<?= $editData ? htmlspecialchars($editData['norek']) : '' ?>" required>

        <label>Status:</label>
        <select name="status">
            <option value="aktive" <?= (!$editData || $editData['status'] == 'aktive') ? 'selected' : '' ?>>Aktif</option>
            <option value="nonaktif" <?= ($editData && $editData['status'] == 'nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
        </select>

        <?php if ($editData): ?>
            <input type="hidden" name="id_edit" value="<?= $editData['id_bc'] ?>">
        <?php endif; ?>

        <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
    </form>

    <div class="filter">
        <form method="GET">
            <label>Filter Bank:</label>
            <select name="bank">
                <option value="0">-- Semua --</option>
                <?php $banks->data_seek(0); while ($b = $banks->fetch_assoc()): ?>
                    <option value="<?= $b['id_bank'] ?>" <?= ($filter_bank == $b['id_bank']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($b['n_bank']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label>Filter Cabang:</label>
            <select name="cabang">
                <option value="0">-- Semua --</option>
                <?php $branches->data_seek(0); while ($c = $branches->fetch_assoc()): ?>
                    <option value="<?= $c['idl'] ?>" <?= ($filter_cabang == $c['idl']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nama_cabang']) ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <input type="submit" class="btn btn-secondary" value="Filter">
        </form>
    </div>

    <h2>Data Bank Cabang</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Bank</th>
                <th>Cabang</th>
                <th>No. Rekening</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id_bc'] ?></td>
                    <td><?= htmlspecialchars($row['n_bank']) ?></td>
                    <td><?= htmlspecialchars($row['nama_cabang']) ?></td>
                    <td><?= htmlspecialchars($row['norek']) ?></td>
                    <td><?= htmlspecialchars($row['status']) ?></td>
                    <td><?= $row['tanggal'] ?></td>
                    <td>
                        <a class="btn btn-secondary" href="?edit=<?= $row['id_bc'] ?>">Edit</a>
                        <a class="btn btn-danger" href="?delete=<?= $row['id_bc'] ?>" onclick="return confirm('Nonaktifkan data ini?')">Nonaktif</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">Tidak ada data</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php $conn->close(); ?>
