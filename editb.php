<?php
require_once 'config1.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("ID Barang tidak valid.");
}

// Ambil data barang berdasarkan ID
$stmt = $conn->prepare("SELECT * FROM b WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$barang = $result->fetch_assoc();
$stmt->close();

if (!$barang) {
    die("Barang tidak ditemukan.");
}

// Tentukan tipe PPN berdasarkan nilai ppn di database
$ppn_jual_type = ($barang['ppn_b'] > 0) ? 11 : 0;
$ppn_beli_type = ($barang['ppn_m'] > 0) ? 11 : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Master Barang</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body {
        color: maroon;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9);
    }

    h2 {
        text-align: center;
        margin-bottom: 20px;
        color: maroon;
    }

    .form-container {
        width: 100%;
        max-width: 450px;
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.15);
        margin: 40px auto;
    }

    .home-icon1 {
        position: absolute;
        left: 0;
        top: 0;
        padding-left: 10px;
        color: maroon;
        font-size: 24px;
        margin-top: 10px;
    }

    .left-icon {
        position: absolute;
        right: 0;
        top: 0;
        padding-right: 10px;
        color: maroon;
        font-size: 24px;
        margin-top: 10px;
    }

    label {
        font-weight: bold;
        display: block;
        margin-top: 12px;
        margin-bottom: 5px;
    }

    input[type="text"],
    select,
    input[type="number"] {
        width: 100%;
        padding: 10px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
    }

    input[type="submit"] {
        width: 100%;
        padding: 12px;
        background-color: maroon;
        color: #fff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        transition: background-color 0.3s;
        margin-top: 15px;
    }

    input[type="submit"]:hover {
        background-color: #8b0000;
    }

    .logo {
        width: 150px;
        height: auto;
        display: block;
        margin: 0 auto 10px;
    }

    .modal {
        display: none; 
        position: fixed;
        z-index: 1000;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        width: 80%;
        border-radius: 8px;
        position: relative;
    }

    .close {
        position: absolute;
        right: 20px;
        top: 10px;
        font-size: 28px;
        cursor: pointer;
    }
</style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>

    <a href="barang.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    
    <div class="container">
        <div class="form-container">
            <div style="display:flex; justify-content:space-between; margin-bottom:15px; font-size:12px;">
                <a href="javascript:void(0)" onclick="openModal('jenis.php')">master jenis</a>
                <a href="javascript:void(0)" onclick="openModal('brand.php')">master brand</a>
            </div>
            
            <div id="myModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal()">&times;</span>
                    <iframe id="modalFrame" src="" width="100%" height="500px" frameborder="0"></iframe>
                </div>
            </div>

            <form action="update_b.php" method="post">
                <img src="logo.png" alt="Logo" class="logo"> 
                <h2>Edit Master Barang</h2>
                
                <input type="hidden" name="id" value="<?= $barang['id'] ?>">

                <label for="kode_b">Kode Barang:</label>
                <input type="text" id="kode_b" name="kode_b" value="<?= htmlspecialchars($barang['kode_b']) ?>" required readonly>

                <label for="nama_b">Nama Barang:</label>
                <input type="text" id="nama_b" name="nama_b" value="<?= htmlspecialchars($barang['nama_b']) ?>" required>

                <label for="jenis">Jenis Barang:</label>
                <select id="jenis" name="jenis" required>
                    <?php
                    $result_jenis = $conn->query("SELECT jenis FROM jenis_b");
                    while ($row = $result_jenis->fetch_assoc()) {
                        $selected = ($row['jenis'] === $barang['jenis']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($row['jenis']) . "' $selected>" . htmlspecialchars($row['jenis']) . "</option>";
                    }
                    ?>
                </select>

                <label for="brand">Brand:</label>
                <select id="brand" name="brand" required>
                    <?php
                    $result_brand = $conn->query("SELECT brand FROM brand_b");
                    while ($row = $result_brand->fetch_assoc()) {
                        $selected = ($row['brand'] === $barang['brand']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($row['brand']) . "' $selected>" . htmlspecialchars($row['brand']) . "</option>";
                    }
                    ?>
                </select>

                <label for="rasio_tengah">1 Lusin = berapa Pcs?</label>
                <input type="number" id="rasio_tengah" name="rasio_tengah" value="<?= (float)($barang['rasio_tengah'] ?? 12) ?>" step="0.01" required>

                <label for="rasio_besar">1 Box = berapa Pcs?</label>
                <input type="number" id="rasio_besar" name="rasio_besar" value="<?= (float)($barang['rasio_besar'] ?? 24) ?>" step="0.01" required>

                <label for="dpp">Harga Beli:</label>
                <input type="number" id="dpp" name="dpp" value="<?= (float)$barang['dpp'] ?>" step="0.01" required>

                <div>
                    <label for="ppn_beli_type">Pajak Beli (PPN):</label>
                    <select id="ppn_beli_type" name="ppn_beli_type">
                        <option value="11" <?= ($ppn_beli_type === 11) ? 'selected' : '' ?>>PPN 11%</option>
                        <option value="0" <?= ($ppn_beli_type === 0) ? 'selected' : '' ?>>Non-PPN</option>
                    </select>
                </div>

                <label for="harga">Harga Jual (Normal):</label>
                <input type="number" id="harga" name="harga" value="<?= (float)$barang['hargat_b'] ?>" step="0.01" required>

                <label for="harga_retail">Harga Retail:</label>
                <input type="number" id="harga_retail" name="harga_retail" value="<?= (float)($barang['harga_retail'] ?? 0) ?>" step="0.01" required>

                <div>
                    <label for="ppn_jual_type">Pajak Jual (PPN):</label>
                    <select id="ppn_jual_type" name="ppn_jual_type">
                        <option value="11" <?= ($ppn_jual_type === 11) ? 'selected' : '' ?>>PPN 11%</option>
                        <option value="0" <?= ($ppn_jual_type === 0) ? 'selected' : '' ?>>Non-PPN</option>
                    </select>
                </div>

                <input type="submit" value="Update Data">
            </form>
        </div>
    </div>
</div>
<script>
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    document.addEventListener('keydown', function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
            e.preventDefault();
        }
    });

    function openModal(url) {
        document.getElementById('modalFrame').src = url;
        document.getElementById('myModal').style.display = "block";
    }

    function closeModal() {
        document.getElementById('myModal').style.display = "none";
        document.getElementById('modalFrame').src = "";
    }

    window.onclick = function(event) {
        let modal = document.getElementById('myModal');
        if (event.target == modal) {
            closeModal();
        }
    }
</script>
</body>
</html>
<?php
$conn->close();
?>
