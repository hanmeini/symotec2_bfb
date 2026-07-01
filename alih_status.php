<?php
require_once 'config1.php';
require_once 'functions_stock.php';

$conn->set_charset("utf8mb4");

$id_gudang = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$back_url = ($id_gudang > 0) ? "gudang/home.php?id=" . $id_gudang : "stock.php?id=" . $id_gudang;

$username = $_SESSION['username'] ?? 'system';

$kode_b_pre = $_GET['kode_b'] ?? '';
$nama_b_pre = $_GET['nama_b'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_alih'])) {
    $batch = $_POST['batch'] ?? '';
    $kode_arr = $_POST['kode'] ?? [];
    $nama_arr = $_POST['nama'] ?? [];
    $satuan_arr = $_POST['satuan'] ?? [];
    $qty_arr = $_POST['qty'] ?? [];
    $reject_arr = $_POST['reject'] ?? [];

    try {
        $conn->begin_transaction();

        // Ambil nama gudang
        $nama_gudang = 'Gudang Pusat';
        if ($id_gudang > 0) {
            $q = $conn->query("SELECT nama_gudang FROM master_gudang WHERE id_gudang = $id_gudang");
            if ($q && $r = $q->fetch_assoc()) {
                $nama_gudang = $r['nama_gudang'];
            }
        }

        $stmt_out = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_k, userid, id_gudang, batch) VALUES (NOW(), ?, ?, ?, ?, ?)");
        $stmt_in = $conn->prepare("INSERT INTO stock (tanggal_transaksi, kodeb, jumlah_m, userid, id_gudang, batch) VALUES (NOW(), ?, ?, ?, ?, ?)");

        $berhasil = false;

        foreach ($kode_arr as $i => $kode) {
            if (empty($kode)) continue;
            
            $qty = (float)($qty_arr[$i] ?? 0);
            $reject = (float)($reject_arr[$i] ?? 0);
            $nama = $nama_arr[$i] ?? '';
            $satuan = $satuan_arr[$i] ?? '';

            if ($qty > 0 && $reject > 0) {
                $berhasil = true;
                // 1. Tarik stok (keluar) dari barang Tersedia
                $stmt_out->bind_param("sdsis", $kode, $qty, $username, $id_gudang, $batch);
                $stmt_out->execute();

                // 2. Ulur stok (masuk) ke barang Reject
                $stmt_in->bind_param("sdsis", $kode, $reject, $username, $id_gudang, $batch);
                $stmt_in->execute();
                
                // Recalculate stock history for this item
                recalculate_stock_history($conn, $kode);
            }
        }

        if (!$berhasil) {
            throw new Exception("Tidak ada data valid yang diisi. Pastikan Qty dan Reject diisi.");
        }

        $stmt_out->close();
        $stmt_in->close();

        $conn->commit();
        $successMsg = "Alih status berhasil disimpan ke tabel stock!";
    } catch (Exception $e) {
        $conn->rollback();
        $errorMsg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alih Status</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            padding: 20px;
            margin: 0;
        }
        .home-icon {
            position: absolute;
            left: 15px;
            top: 5px;
            text-decoration: none;
        }
        .home-icon i {
            color: white;
            background: #28a745;
            border-radius: 50%;
            padding: 12px;
            font-size: 28px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .home-icon i:hover {
            background: #218838;
        }
        .main-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-top: 60px;
        }
        h1 {
            color: #000;
            margin: 0 0 20px 0;
            font-size: 24px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .form-group input {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            outline: none;
            width: 200px;
        }
        .table-container {
            width: 100%;
            overflow: visible;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
            font-size: 14px;
        }
        th {
            background: #28a745;
            color: white;
            font-weight: bold;
        }
        .input-cell input {
            width: 90%;
            padding: 6px;
            border: 1px solid #ccc;
            border-radius: 4px;
            outline: none;
        }
        .btn-action {
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: #f9f9f9;
            cursor: pointer;
            font-size: 12px;
            margin-right: 5px;
        }
        .btn-action:hover {
            background: #e9e9e9;
        }
        .btn-green {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: #28a745;
            color: white;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin-right: 5px;
        }
        .btn-green:hover {
            background: #218838;
        }
        .msg { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-weight: bold; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .suggestions { position: absolute; background: #fff; border: 1px solid #ccc; border-radius: 4px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); max-height: 150px; overflow-y: auto; z-index: 100; text-align: left; }
        .suggestion-item { padding: 8px; cursor: pointer; color: black; }
        .suggestion-item:hover { background: #e3e3e3; }
        .input-cell { position: relative; }
    </style>
</head>
<body>

<a href="<?= $back_url ?>" class="home-icon">
    <i class="fas fa-home"></i>
</a>

<div class="main-container">
    <h1>Alih Status</h1>
    
    <?php if(!empty($successMsg)) echo '<div class="msg success">'.htmlspecialchars($successMsg).'</div>'; ?>
    <?php if(!empty($errorMsg)) echo '<div class="msg error">'.htmlspecialchars($errorMsg).'</div>'; ?>

    <form method="POST">
        <div class="form-group">
            <label>Batch:</label>
            <input type="text" name="batch" placeholder="">
        </div>

        <div class="table-container">
            <table id="alihTable">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Satuan</th>
                        <th>Qty (Masuk ke Reject)</th>
                        <th style="background: white; border: none;"></th>
                        <th>Reject (Jumlah)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="input-cell">
                            <input type="text" name="kode[]" onkeyup="getBarang(this.value, this)" placeholder="Ketik Kode/Nama..." value="<?= htmlspecialchars($kode_b_pre) ?>" required>
                            <div class="suggestions"></div>
                        </td>
                        <td class="input-cell"><input type="text" name="nama[]" value="<?= htmlspecialchars($nama_b_pre) ?>" readonly required></td>
                        <td class="input-cell"><input type="text" name="satuan[]"></td>
                        <td class="input-cell"><input type="number" name="qty[]" min="0"></td>
                        <td style="border: none;">&rarr;</td>
                        <td class="input-cell"><input type="number" name="reject[]" min="0"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div>
            <button type="button" class="btn-action" onclick="addRow()">Tambah Baris</button>
            <button type="submit" name="simpan_alih" class="btn-green">Simpan</button>
        </div>
    </form>
</div>

<script>
    function getBarang(kode, el) {
        if (kode.length === 0) { el.parentNode.querySelector('.suggestions').innerHTML = ""; return; }
        let xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                let data = JSON.parse(this.responseText);
                let box = el.parentNode.querySelector('.suggestions');
                box.innerHTML = "";
                data.forEach(function(item) {
                    let div = document.createElement("div");
                    div.classList.add("suggestion-item");
                    div.innerHTML = item.kode_b + " - " + item.nama_b;
                    div.onclick = function() {
                        el.value = item.kode_b;
                        el.closest('tr').querySelector('[name="nama[]"]').value = item.nama_b;
                        box.innerHTML = ""; box.style.display = "none";
                    };
                    box.appendChild(div);
                });
                box.style.display = data.length > 0 ? "block" : "none";
            }
        };
        xhr.open("GET", "search_barang.php?kode_b=" + encodeURIComponent(kode), true);
        xhr.send();
    }

    function addRow() {
        const table = document.getElementById("alihTable").getElementsByTagName('tbody')[0];
        const newRow = table.insertRow();
        newRow.innerHTML = `
            <td class="input-cell">
                <input type="text" name="kode[]" onkeyup="getBarang(this.value, this)" placeholder="Ketik Kode/Nama..." required>
                <div class="suggestions"></div>
            </td>
            <td class="input-cell"><input type="text" name="nama[]" readonly required></td>
            <td class="input-cell"><input type="text" name="satuan[]"></td>
            <td class="input-cell"><input type="number" name="qty[]" min="0"></td>
            <td style="border: none;">&rarr;</td>
            <td class="input-cell"><input type="number" name="reject[]" min="0"></td>
        `;
    }
</script>

</body>
</html>
