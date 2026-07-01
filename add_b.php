<?php



  
    


















require_once 'config1.php';


?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input COA</title>
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
        max-width: 400px;
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        margin: 20px auto;
    }

    .home-icon1 {
        position: absolute;
        left: 0;
        top: 0;
        padding-left: 10px;
        color: maroon;
        font-size: 24px;
    }

    .left-icon {
        position: absolute;
        right: 0;
        top: 0;
        padding-right: 10px;
        color: maroon;
        font-size: 24px;
    }

    label {
        font-weight: bold;
    }

    input[type="text"],
    select,
    input[type="file"],
    input[type="number"] {
        width: 100%;
        padding: 8px;
        margin-bottom: 15px;
        border: 1px solid #ccc;
        border-radius: 5px;
        box-sizing: border-box;
    }

    input[type="submit"] {
        width: 100%;
        padding: 10px;
        background-color: maroon;
        color: #fff;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    input[type="submit"]:hover {
        background-color: #8b0000;
    }

    .logo {
        width: 200px;
        height: auto;
        display: block;
        margin: 0 auto;
    }
    /* Background Gelap di Belakang Popup */
.modal {
  display: none; 
  position: fixed;
  z-index: 1000;
  left: 0; top: 0;
  width: 100%; height: 100%;
  background-color: rgba(0,0,0,0.5);
}

/* Kotak Konten */
.modal-content {
  background-color: #fff;
  margin: 5% auto;
  padding: 20px;
  width: 80%;
  border-radius: 8px;
  position: relative;
}

/* Tombol Close (X) */
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
            <a href="javascript:void(0)"  onclick="openModal('jenis.php')">
  master jenis
</a><br><br>
<a href="javascript:void(0)" onclick="openModal('brand.php')">
  master brand
</a>
<div id="myModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">&times;</span>
    <iframe id="modalFrame" src="" width="100%" height="500px" frameborder="0"></iframe>
  </div>
</div>
            <form action="save_b.php" method="post" enctype="multipart/form-data">
                <img src="logo.png" alt="Logo" class="logo"> 
                <h2>Input Master Barang</h2>
                <label for="kode_b">Kode Barang:</label>
                <input type="text" id="kode_b" name="kode_b" required>

                <label for="nama_b">Nama Barang:</label>
                <input type="text" id="nama_b" name="nama_b" required>

                <label for="jenis">Jenis Barang:</label>
                <select id="jenis" name="jenis" required>
                    <?php
                    // Ambil data jenis dari tabel jenis_b
                    $result_jenis = $conn->query("SELECT jenis FROM jenis_b");
                    while ($row = $result_jenis->fetch_assoc()) {
                        echo "<option value='" . $row['jenis'] . "'>" . $row['jenis'] . "</option>";
                    }
                    ?>
                </select>

                <label for="brand">Brand:</label>
                <select id="brand" name="brand" required>
                    <?php
                    // Ambil data brand dari tabel brand_b
                    $result_brand = $conn->query("SELECT brand FROM brand_b");
                    while ($row = $result_brand->fetch_assoc()) {
                        echo "<option value='" . $row['brand'] . "'>" . $row['brand'] . "</option>";
                    }
                    ?>
                </select>

                <label for="dpp">Harga Beli:</label>
                <input type="number" id="dpp" name="dpp" step="0.01" required>

                <div>
                    <label for="ppn_beli_type">Pajak Beli (PPN):</label>
                    <select id="ppn_beli_type" name="ppn_beli_type">
                        <option value="11">PPN 11%</option>
                        <option value="0">Non-PPN</option>
                    </select>
                </div>

                <label for="harga">Harga Jual:</label>
                <input type="number" id="harga" name="harga" step="0.01" required>

                <div>
                    <label for="ppn_jual_type">Pajak Jual (PPN):</label>
                    <select id="ppn_jual_type" name="ppn_jual_type">
                        <option value="11">PPN 11%</option>
                        <option value="0">Non-PPN</option>
                    </select>
                </div>

                <input type="submit" value="Submit">
            </form>
        </div>
    </div>
</body>
<script>

// ✅ CEK KODE_B OTOMATIS
document.getElementById("kode_b").addEventListener("change", function () {
    var kode = this.value.trim();
    if (kode.length === 0) return;

    var xhr = new XMLHttpRequest();
    xhr.open("POST", "cek_kode_b.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.onload = function () {
        if (this.responseText !== "OK") {
            var data = JSON.parse(this.responseText);

            alert(
                "Kode sudah digunakan!\n\n" +
                "Kode : " + data.kode_b + "\n" +
                "Nama : " + data.nama_b + "\n" +
                "Jenis : " + data.jenis + "\n" +
                "Brand : " + data.brand
            );

            document.getElementById("kode_b").value = "";
            document.getElementById("kode_b").focus();
        }
    };
    xhr.send("kode_b=" + encodeURIComponent(kode));
});

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
  document.getElementById('modalFrame').src = ""; // Reset konten
}

// Menutup modal jika user klik di luar kotak putih
window.onclick = function(event) {
  let modal = document.getElementById('myModal');
  if (event.target == modal) {
    closeModal();
  }
}
</script>
</html>

<?php
// Menutup koneksi database setelah script selesai
$conn->close();
?>
