<?php



  
    



require_once 'config1.php';


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input Master Barang</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body {
        color: maroon;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9);
    }

    .container {
        overflow: hidden;
    }

    .form-container {
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    label {
        font-weight: bold;
    }

    input[type="text"], select {
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

    .table-container {
        width: 100%;
        overflow-x: auto;
        margin-top: 20px;
        color: white;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        color: white;
    }

    table, th, td {
        border: 1px solid #ddd;
        padding: 8px;
        text-align: left;
    }

    th {
        background-color: maroon;
    }

    th, td {
        padding: 2px;
    }

    .home-icon i, .left-icon i {
        color: maroon;
        font-size: 24px;
    }

    .logo {
        width: 100px;
        height: auto;
        display: block;
        margin: 0 auto;
    }

    .home-icon {
        position: absolute;
        left: 0;
        top: 0;
        padding-left: 10px;
    }

    .left-icon {
        position: absolute;
        right: 0;
        top: 0;
        padding-right: 10px;
    }

    h2 {
        text-align: center;
        margin-bottom: 20px;
        color: maroon;
    }

    /* Media Queries for Mobile */
    @media (max-width: 600px) {
        .form-container, .table-container {
            width: 100%;
            margin: 0px;
        }

        table, th, td {
            font-size: 10px;
        }

        th, td {
            padding: 4px;
        }

        .logo {
            width: 80px;
        }

        .home-icon i, .left-icon i {
            font-size: 20px;
        }
    }

    /* Filter style */
    .search-box {
        margin-bottom: 15px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .search-box input {
        padding: 8px;
        width: 50%;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    .search-box button {
        margin-left: 10px;
        padding: 8px 15px;
        background-color: maroon;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.3s;
    }

    .search-box button:hover {
        background-color: #8b0000;
    }
</style>
</head>
<body>
<!-- Modal Scanner -->
<div id="scannerModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.8); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:15px; border-radius:10px; width:90%; max-width:400px;">
        <h3 style="text-align:center;color:maroon;">Scan Barcode</h3>
        <video id="scanPreview" style="width:100%; border-radius:8px;"></video>
        <button onclick="closeScanner()" style="margin-top:10px;width:100%;background:maroon;color:white;border:none;padding:8px;border-radius:5px;">
            Tutup
        </button>
    </div>
</div>
<script src="https://unpkg.com/@zxing/library@latest"></script>

<div class="table-container">
    <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h2>DAFTAR BARANG</h2>

    <!-- Filter input and Add button -->
<div class="search-box">
    <form method="GET" action="barang.php" style="display:flex; width:100%; gap:5px;">
        <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Cari kode/nama barang...">
        
        <button type="submit" title="Cari">
            <i class="fa-solid fa-search"></i>
        </button>

        <button type="button" onclick="openScanner()" title="Scan Barcode">
            <i class="fa-solid fa-barcode"></i>
        </button>

        <button type="button" onclick="location.href='add_b.php'">
            <i class="fa-solid fa-plus"></i> Add
        </button>
    </form>
</div>


    <?php


$conn = new mysqli($servername, $db_username, $db_password, $database);

    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    $limit = 15;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $where_clause = "";
    if ($search != '') {
        $where_clause = " WHERE b.kode_b LIKE '%$search%' OR b.nama_b LIKE '%$search%' ";
    }

    $total_res = $conn->query("SELECT COUNT(*) as total FROM b $where_clause");
    $total_rows = $total_res->fetch_assoc()['total'];
    $total_pages = ceil($total_rows / $limit);

    $sql = "SELECT b.*, COALESCE(SUM(s.jumlah_m) - SUM(s.jumlah_k), 0) AS stok 
            FROM b 
            LEFT JOIN stock s ON b.kode_b = s.kodeb 
            $where_clause
            GROUP BY b.kode_b 
            ORDER BY b.kode_b 
            LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);

 if ($result->num_rows > 0) {
    echo "<table id='coaTable'>";
    echo "<thead>
            <tr>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Jenis</th>
                <th>Brand</th>
                <th>Stok Tersedia</th>
                <th>Harga Beli terakhir</th>
                <th>Harga Jual Include</th>
                <th>% Mark-up</th>
                <th>Action</th>
            </tr>
          </thead>";
    echo "<tbody>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row["kode_b"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["nama_b"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["jenis"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["brand"]) . "</td>";
        
        echo "<td style='text-align: center; font-weight: bold; font-size: 14px;'>" . (int)$row["stok"] . "</td>";
        
        // Pastikan nilai angka ditampilkan dengan format yang benar

        echo "<td style='text-align: right;'>" . number_format($row["dpp"], 2, ',', '.') . "</td>";
 
        echo "<td style='text-align: center; white-space: nowrap;'>
                <form method='POST' action='update_harga_jual.php' style='display:flex; align-items:center; justify-content:center; gap:5px; margin:0;'>
                    <input type='hidden' name='kode_b' value='" . htmlspecialchars($row["kode_b"]) . "'>
                    <input type='hidden' name='old_harga' value='" . $row["hargat_b"] . "'>
                    <input type='number' name='new_harga' value='" . $row["hargat_b"] . "' style='width: 90px; padding: 4px; text-align:right; margin:0;' step='0.01' required>
                    <button type='submit' style='background-color: green; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer;' title='Update Harga'>
                        <i class='fa-solid fa-save'></i>
                    </button>
                </form>
              </td>";

        // Perhitungan Mark-up (tanpa simbol % dan hanya 2 desimal)
        if ($row["dpp"] > 0) {
            $markup = (($row["hargat_b"] - $row["dpp"]) / $row["dpp"]) * 100;
        } else {
            $markup = 0; // Menghindari pembagian dengan nol
        }
        echo "<td style='text-align: right; color: " . ($markup >= 0 ? 'white' : 'red') . ";'>" . number_format($markup, 2, ',', '.') . "</td>";

        // Aksi edit dan hapus
        echo "<td style='max-width: 70px; white-space: nowrap;'>
            <a href='editb.php?id=" . urlencode($row["id"]) . "'>
                <i class='fa-solid fa-screwdriver-wrench' style='font-size: 20px; color: blue;'></i>
            </a>
           <br>  <br>
            <a href='barcode.php?harga=" . urlencode($row['hargat_b']) . "&kode=" . urlencode($row['kode_b']) . "'>
                <i class='fa-solid fa-barcode' style='font-size: 20px; color: blue;'></i>
            </a>
            <br><br>
            <a href='deleteb.php?id=" . urlencode($row["id"]) . "' onclick='return confirm(\"Apakah Anda yakin ingin menghapus?\")'>
                <i class='fa-solid fa-trash-can' style='font-size: 20px; color: red;'></i>
            </a>
          </td>";
}


    echo "</tbody>";
    echo "</table>";

    // Pagination controls
    if ($total_pages > 1) {
        echo "<div style='text-align: center; margin-top: 20px;'>";
        for ($i = 1; $i <= $total_pages; $i++) {
            $bgColor = ($i === $page) ? '#8b0000' : 'maroon';
            $search_param = ($search != '') ? "&search=".urlencode($search) : "";
            echo "<a href='?page=$i$search_param' style='display:inline-block; padding:8px 12px; margin:2px; background-color:$bgColor; color:white; text-decoration:none; border-radius:5px;'>$i</a>";
        }
        echo "</div>";
    }

} else {
    echo "<p style='text-align: center;'>Tidak ada data Barang.</p>";
}

$conn->close();



    ?>

</div>

<script>
    // Fungsi filterTable() dinonaktifkan karena search sekarang dilakukan melalui PHP server-side
    function filterTable() {
        // kosong
    }</script>
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
<script>
let codeReader;
let scanning = false;

function openScanner() {
    document.getElementById('scannerModal').style.display = 'flex';

    if (!codeReader) {
        codeReader = new ZXing.BrowserMultiFormatReader();
    }

    scanning = true;

    codeReader.decodeFromVideoDevice(null, 'scanPreview', (result, err) => {
        if (result && scanning) {
            scanning = false;

            // Ambil nilai barcode
            const barcodeValue = result.text;

            // Masukkan ke input search
            const input = document.getElementById('searchInput');
            input.value = barcodeValue;

            // Jalankan filter
            filterTable();

            // Tutup scanner
            closeScanner();
        }
    });
}

function closeScanner() {
    scanning = false;
    if (codeReader) {
        codeReader.reset();
    }
    document.getElementById('scannerModal').style.display = 'none';
}
</script>

</body>
</html>
