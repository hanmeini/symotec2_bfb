<?php



  
    



require_once 'config1.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Stok</title>
       <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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

        form {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            margin-right: 10px;
        }

        input[type="text"] {
            width: calc(100% - 110px);
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        .suggestions {
            border: 1px solid #ccc;
            max-height: 150px;
            overflow-y: auto;
            position: absolute;
            width: calc(100% - 110px);
            background-color: white;
        }

        .suggestion-item {
            padding: 8px;
            cursor: pointer;
        }

        .suggestion-item:hover {
            background-color: #f0f0f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        @media screen and (max-width: 600px) {
            input[type="text"] {
                width: 100%;
            }

            button {
                width: 100%;
                padding: 12px;
            }

            .suggestions {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    
<div class="container">
    <h1>Kartu Stok</h1>
    <?php
    $kode_b_val = $_POST['kode_b'] ?? $_GET['kode_b'] ?? '';
    ?>
    <form method="POST">
        <div class="item">
            <label for="kode_b">Kode Barang:</label>
            <input type="text" name="kode_b" id="kode_b" onkeyup="getBarang(this.value, this)" placeholder="Masukkan kode barang" value="<?= htmlspecialchars($kode_b_val) ?>" required>
            <div class="suggestions"></div>
        </div>
        <button type="submit">Cari</button>
    </form>

    <?php
    if ($kode_b_val !== '') {
        $kode_b = $kode_b_val;

        $conn = new mysqli($servername, $db_username, $db_password, $database);

        // Periksa koneksi
        if ($conn->connect_error) {
            die("Koneksi gagal: " . $conn->connect_error);
        }

        // Query untuk mengambil data berdasarkan kode barang dari tabel stock
        $sql = "
            SELECT 
                stock.tanggal_transaksi, 
                stock.sj AS J, 
                stock.kodeb AS kode_b, 
                b.nama_b AS nama_b, 
                stock.jumlah_m, 
                stock.jumlah_k,
                'Tersedia' AS status_gudang
            FROM 
                stock 
            LEFT JOIN b ON stock.kodeb = b.kode_b
            WHERE 
                stock.kodeb = ? 
            ORDER BY 
                stock.tanggal_transaksi ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $kode_b);
        $stmt->execute();
        
        // Bind hasil dari query
        $stmt->bind_result($tanggal_transaksi, $J, $kode_b_result, $nama_b, $jumlah_m, $jumlah_k, $status_gudang);

        echo "<h2>Kartu Stok untuk Kode Barang: " . htmlspecialchars($kode_b) . "</h2>";
        echo "<table>
                <tr>
                    <th>Tanggal Transaksi</th>
                    <th>Nomor nota</th>
                    <th>Kode Barang</th>
                    <th>Nama Barang</th>
                    <th>Status</th>
                    <th>Jumlah Masuk</th>
                    <th>Jumlah Keluar</th>
                    <th>Saldo</th>
                </tr>";

        $saldo_sebelumnya = 0;
        while ($stmt->fetch()) {
            $saldo = $jumlah_m - $jumlah_k + $saldo_sebelumnya;
            echo "<tr>
                    <td>" . htmlspecialchars($tanggal_transaksi ?? '') . "</td>
                    <td>" . htmlspecialchars($J ?? '') . "</td>
                    <td>" . htmlspecialchars($kode_b_result ?? '') . "</td>
                    <td>" . htmlspecialchars($nama_b ?? '') . "</td>
                    <td>" . htmlspecialchars($status_gudang ?? 'Tersedia') . "</td>
                    <td>" . htmlspecialchars(number_format((float)$jumlah_m, 2, ',', '.')) . "</td>
                    <td>" . htmlspecialchars(number_format((float)$jumlah_k, 2, ',', '.')) . "</td>
                    <td>" . htmlspecialchars(number_format((float)$saldo, 2, ',', '.')) . "</td>
                </tr>";
            $saldo_sebelumnya = $saldo;
        }

        echo "</table>";

        $stmt->close();
        $conn->close();
    }
    ?>
</div>

<script>
function getBarang(kode_b, el) {
    if (kode_b.length === 0) {
        el.closest('.item').querySelector('.suggestions').innerHTML = "";
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
            var suggestions = JSON.parse(this.responseText);
            var suggestionBox = el.closest('.item').querySelector('.suggestions');
            suggestionBox.innerHTML = "";

            suggestions.forEach(function(suggestion) {
                var div = document.createElement("div");
                div.innerHTML = suggestion.kode_b + " - " + suggestion.nama_b;
                div.classList.add("suggestion-item");
                div.onclick = function() {
                    el.value = suggestion.kode_b;
                    el.closest('.item').querySelector('.suggestions').innerHTML = "";
                };
                suggestionBox.appendChild(div);
            });
        }
    };
    xhr.open("GET", "search_barang.php?kode_b=" + kode_b, true);
    xhr.send();
}
</script>
<script>
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    document.addEventListener('keydown', function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>
