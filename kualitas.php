<?php
require_once 'config1.php';

// Handle aksi CRUD (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            $sql = "INSERT INTO kualitas (`f`, `m`, `e`, `bf`, `cf`, `bcf`, `150`, `100`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['f'], $_POST['m'], $_POST['e'], 
                $_POST['bf'], $_POST['cf'], $_POST['bcf'], 
                $_POST['150'], $_POST['100']
            ]);
            $msg = "Data berhasil ditambahkan!";
            
        } elseif ($action === 'edit') {
            $sql = "UPDATE kualitas SET 
                    `f`=?, `m`=?, `e`=?, `bf`=?, `cf`=?, `bcf`=?, `150`=?, `100`=? 
                    WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['f'], $_POST['m'], $_POST['e'], 
                $_POST['bf'], $_POST['cf'], $_POST['bcf'], 
                $_POST['150'], $_POST['100'], $_POST['id']
            ]);
            $msg = "Data berhasil diubah!";
            
        } elseif ($action === 'delete') {
            $sql = "DELETE FROM kualitas WHERE id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['id']]);
            $msg = "Data berhasil dihapus!";
        }
    } catch (PDOException $e) {
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Fetch Data
$stmt = $pdo->query("SELECT * FROM kualitas ORDER BY id DESC");
$kualitasData = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Kualitas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --bg: #f8f9fa;
            --text: #333;
            --border: #e2e8f0;
            --primary: #475569;
            --primary-hover: #334155;
            --danger: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 15px;
        }

        h2 {
            margin: 0;
            font-weight: 600;
            color: var(--primary);
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); color: white; padding: 6px 12px; }
        .btn-edit { background: var(--border); color: var(--text); padding: 6px 12px; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background-color: #f1f5f9;
            font-weight: 600;
            color: var(--primary);
            font-size: 14px;
        }

        tr:hover { background-color: #f8fafc; }

        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 { margin: 0; }
        .close { cursor: pointer; font-size: 20px; color: #94a3b8; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus { border-color: var(--primary); }

        .modal-footer {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h2><i class="fa-solid fa-boxes-stacked"></i> Master Kualitas</h2>
        <button class="btn btn-primary" onclick="openModal('add')">
            <i class="fa-solid fa-plus"></i> Tambah Data
        </button>
    </div>

    <?php if (isset($msg)) echo "<div class='alert alert-success'>$msg</div>"; ?>
    <?php if (isset($error)) echo "<div class='alert alert-error'>$error</div>"; ?>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>F</th>
                <th>M</th>
                <th>E</th>
                <th>BF</th>
                <th>CF</th>
                <th>BCF</th>
                <th>150</th>
                <th>100</th>
                <th style="text-align: center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($kualitasData) > 0): ?>
                <?php $no = 1; foreach ($kualitasData as $row): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['f']) ?></td>
                    <td><?= htmlspecialchars($row['m']) ?></td>
                    <td><?= htmlspecialchars($row['e']) ?></td>
                    <td><?= htmlspecialchars($row['bf']) ?></td>
                    <td><?= htmlspecialchars($row['cf']) ?></td>
                    <td><?= htmlspecialchars($row['bcf']) ?></td>
                    <td><?= htmlspecialchars($row['150']) ?></td>
                    <td><?= htmlspecialchars($row['100']) ?></td>
                    <td style="text-align: center; gap: 5px; display: flex; justify-content: center;">
                        <button class="btn btn-edit" onclick='openModal("edit", <?= json_encode($row) ?>)'>
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus data ini?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="10" style="text-align: center; color: #94a3b8;">Belum ada data kualitas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Form -->
<div id="kualitasModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Kualitas</h3>
            <span class="close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></span>
        </div>
        <form method="POST" id="kualitasForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Facing (F)</label>
                    <input type="text" name="f" id="f_input" required>
                </div>
                <div class="form-group">
                    <label>Medium (M)</label>
                    <input type="text" name="m" id="m_input" required>
                </div>
                <div class="form-group">
                    <label>Fluting (E)</label>
                    <input type="text" name="e" id="e_input" required>
                </div>
                <div class="form-group">
                    <label>B-Flute (BF)</label>
                    <input type="number" step="any" name="bf" id="bf_input" required>
                </div>
                <div class="form-group">
                    <label>C-Flute (CF)</label>
                    <input type="number" step="any" name="cf" id="cf_input" required>
                </div>
                <div class="form-group">
                    <label>BC-Flute (BCF)</label>
                    <input type="number" step="any" name="bcf" id="bcf_input" required>
                </div>
                <div class="form-group">
                    <label>Gramatur 150</label>
                    <input type="number" step="any" name="150" id="150_input" required>
                </div>
                <div class="form-group">
                    <label>Gramatur 100</label>
                    <input type="number" step="any" name="100" id="100_input" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-edit" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('kualitasModal');
    
    function openModal(mode, data = null) {
        modal.style.display = 'flex';
        
        if (mode === 'add') {
            document.getElementById('modalTitle').innerText = 'Tambah Data Kualitas';
            document.getElementById('formAction').value = 'add';
            document.getElementById('kualitasForm').reset();
        } else if (mode === 'edit' && data) {
            document.getElementById('modalTitle').innerText = 'Edit Data Kualitas';
            document.getElementById('formAction').value = 'edit';
            
            // Populate data
            document.getElementById('formId').value = data.id;
            document.getElementById('f_input').value = data.f;
            document.getElementById('m_input').value = data.m;
            document.getElementById('e_input').value = data.e;
            document.getElementById('bf_input').value = data.bf;
            document.getElementById('cf_input').value = data.cf;
            document.getElementById('bcf_input').value = data.bcf;
            document.getElementById('150_input').value = data['150'];
            document.getElementById('100_input').value = data['100'];
        }
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    // Close when clicking outside
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

</body>
</html>
