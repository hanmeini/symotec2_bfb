<?php











require_once 'config1.php';


// Toggle status aktif/nonaktif
if (isset($_GET['toggle'])) {
    $id_toggle = intval($_GET['toggle']);
    $stmt = $conn->prepare("SELECT status FROM budget WHERE idb = ?");
    $stmt->bind_param("i", $id_toggle);
    $stmt->execute();
    $stmt->bind_result($current_status);
    if ($stmt->fetch()) {
        $new_status = ($current_status === 'aktive') ? 'nonaktive' : 'aktive';
    }
    $stmt->close();

    if (isset($new_status)) {
        $stmt = $conn->prepare("UPDATE budget SET status = ? WHERE idb = ?");
        $stmt->bind_param("si", $new_status, $id_toggle);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: budget.php");
    exit();
}

// Simpan data baru atau update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nominal = preg_replace('/[^\d]/', '', $_POST['nominal']);
    $keterangan = trim($_POST['keterangan']);
    $id_edit = isset($_POST['id_edit']) ? intval($_POST['id_edit']) : 0;

    if ($id_edit == 0) {
        $namab = trim($_POST['namab']);
        $negara = trim($_POST['negara']);

        if ($namab === '' || $nominal === '' || $negara === '') {
            echo "<script>alert('Lengkapi semua data!');</script>";
        } else {
            $stmt = $conn->prepare("INSERT INTO budget (namab, nominal, negara, keterangan, status) VALUES (?, ?, ?, ?, 'aktive')");
            $stmt->bind_param("sdss", $namab, $nominal, $negara, $keterangan);
            $stmt->execute();
            $stmt->close();
            echo "<script>alert('Data budget berhasil ditambahkan');</script>";
        }
    } else {
        $stmt = $conn->prepare("UPDATE budget SET nominal = ?, keterangan = ? WHERE idb = ?");
        $stmt->bind_param("dsi", $nominal, $keterangan, $id_edit);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('Data budget berhasil diperbarui'); window.location='budget.php';</script>";
    }
}

// Data dropdown negara
$negaraOpt = [];
$qneg = $conn->query("SELECT negara FROM negara WHERE status = 'aktive' ORDER BY negara");
while ($row = $qneg->fetch_assoc()) {
    $negaraOpt[] = $row['negara'];
}

// Mode edit
$editData = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT idb, namab, nominal, negara, keterangan FROM budget WHERE idb = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $stmt->bind_result($idb, $namab, $nominal, $negara, $keterangan);
    if ($stmt->fetch()) {
        $editData = [
            'idb' => $idb,
            'namab' => $namab,
            'nominal' => $nominal,
            'negara' => $negara,
            'keterangan' => $keterangan
        ];
    }
    $stmt->close();
}

$result = $conn->query("SELECT * FROM budget ORDER BY namab ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input & Tampil Budget</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; }
        .container { width: 80%; margin: 30px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; }
        label { display: block; margin-top: 10px; }
        input, select, textarea {
            width: 100%; padding: 10px; margin-top: 5px;
            border: 1px solid #ccc; border-radius: 4px;
        }
        input[type="submit"] {
            background-color: #28a745; color: white; border: none;
            cursor: pointer; margin-top: 15px;
        }
        input[type="submit"]:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .action-btn {
            background-color: #007bff; color: white; padding: 5px 10px;
            text-decoration: none; border-radius: 4px; margin-right: 5px;
        }
        .action-btn:hover { background-color: #0056b3; }
        .status-a { color: green; font-weight: bold; }
        .status-n { color: red; font-weight: bold; }
        .home-icon {
            position: fixed; top: 15px; left: 15px;
            font-size: 24px; color: maroon; z-index: 999;
        }
    </style>
</head>
<body>
<a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
<div class="container">
    <h1><?= $editData ? 'Edit' : 'Input' ?> Budget</h1>
    <form method="POST">
        <?php if (!$editData): ?>
            <label>Nama Budget:</label>
            <input type="text" name="namab" required>

            <label>Negara:</label>
            <select name="negara" required>
                <option value="">-- Pilih Negara --</option>
                <?php foreach ($negaraOpt as $neg): ?>
                    <option value="<?= htmlspecialchars($neg) ?>"><?= htmlspecialchars($neg) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <label>Nama Budget:</label>
            <input type="text" value="<?= htmlspecialchars($editData['namab']) ?>" disabled>

            <label>Negara:</label>
            <input type="text" value="<?= htmlspecialchars($editData['negara']) ?>" disabled>
        <?php endif; ?>

        <label>Nominal:</label>
        <input type="number" name="nominal" min="0" step="1" value="<?= $editData ? $editData['nominal'] : '' ?>" required>

        <label>Keterangan (waktu rilis):</label>
        <textarea name="keterangan" rows="3"><?= $editData ? htmlspecialchars($editData['keterangan']) : '' ?></textarea>

        <?php if ($editData): ?>
            <input type="hidden" name="id_edit" value="<?= $editData['idb'] ?>">
        <?php endif; ?>

        <input type="submit" value="<?= $editData ? 'Update' : 'Simpan' ?>">
    </form>

    <h2>Daftar Budget</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Budget</th>
                <th>Nominal</th>
                <th>Negara</th>
                <th>Keterangan (waktu rilis)</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['idb'] ?></td>
                    <td><?= htmlspecialchars($row['namab']) ?></td>
                    <td><?= number_format($row['nominal'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($row['negara']) ?></td>
                    <td><?= nl2br(htmlspecialchars($row['keterangan'])) ?></td>
                    <td class="<?= $row['status'] === 'aktive' ? 'status-a' : 'status-n' ?>">
                        <?= $row['status'] ?>
                    </td>
                    <td>
                        <a class="action-btn" href="?edit=<?= $row['idb'] ?>">Edit</a>
                        <a class="action-btn" href="?toggle=<?= $row['idb'] ?>" onclick="return confirm('Ubah status budget ini?')">
                            <?= $row['status'] === 'aktive' ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="7">Tidak ada data budget</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>

<?php $conn->close(); ?>
