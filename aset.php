<?php






  
    










require_once 'config1.php';


?>
<script>
function openPopup(url) {
    window.open(url, '_blank', 'width=900,height=1050,scrollbars=yes,resizable=yes');
}
</script>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Aset - symotech.id</title>
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

<div class="table-container">
    <a href="home.php" class="home-icon">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h2>DAFTAR ASET</h2>

    <!-- Filter input and Add button -->
   <div class="search-box">
    <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari berdasarkan nama atau lokasi...">
    <button onclick="location.href='asetin.php'"><i class="fa-solid fa-plus"></i> Add (via bank)</button>
      
    <button onclick="location.href='asetinc.php'"><i class="fa-solid fa-plus"></i> Add (via cash)</button>
    <button onclick="location.href='asetcut.php'"></i> Aset cut-off</button>
    <button onclick="location.href='download_excel.php'"><i class="fa-solid fa-file-excel"></i> Download Excel</button>
      <!-- <button onclick="location.href='download_pdf.php'"><i class="fa-solid fa-file-pdf"></i> Download PDF</button> -->
</div>


<?php


// Query untuk mengambil data dari tabel aset dan nama_cabang dari tabel location
$sql = "SELECT aset.*, location.nama_cabang 
        FROM aset 
        LEFT JOIN location ON aset.location = location.idl 
        ORDER BY aset.id";
$result = $conn->query($sql);

$total_beli = 0;
$total_susut = 0;
$total_nilai_buku = 0;

if ($result->num_rows > 0) {
    echo "<table id='asetTable'>";
    echo "<thead><tr>
            <th>No</th>
            <th>Kode</th>
            <th>Nama</th>
            <th>Tanggal</th>
            <th>Lokasi</th>
           
            <th>Beli</th>
            <th>Akum Susut</th>
            <th>Nilai Buku</th>
            <th>No Recuring</th>
            <th>Action</th>
          </tr></thead>";
    echo "<tbody>";

    $no = 1;

    while ($row = $result->fetch_assoc()) {
        // Ambil total dari kolom `susut` di tabel `susut` berdasarkan `kode`
        $kode = $row["kode"];
        $sql_susut = "SELECT SUM(susut) FROM susut WHERE kode = ?";
        $stmt = $conn->prepare($sql_susut);
        $stmt->bind_param("s", $kode);
        $stmt->execute();
        $stmt->bind_result($total_susut_item);
        $stmt->fetch();
        $stmt->close();

        $total_susut_item = $total_susut_item ?? 0;

        // Hitung nilai buku
        $nilai_buku = $row["beli"] - $total_susut_item;

        // Tambahkan ke total keseluruhan
        $total_beli += $row["beli"];
        $total_susut += $total_susut_item;
        $total_nilai_buku += $nilai_buku;

        echo "<tr>";
        echo "<td>" . $no++ . "</td>";
        echo "<td>" . htmlspecialchars($row["kode"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["nama"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["tanggal"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["nama_cabang"]) . "</td>";
       
        echo "<td>" . number_format($row["beli"], 2, ',', '.') . "</td>";
        echo "<td>" . number_format($total_susut_item, 2, ',', '.') . "</td>";
        echo "<td>" . number_format($nilai_buku, 2, ',', '.') . "</td>";
        echo "<td>" . ($row["recuring"] !== null ? htmlspecialchars($row["recuring"]) : "") . "</td>";
        echo "<td style='max-width: 40px; padding: 2px;'>
                <div style='display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px;'>
                    <a href='#' onclick=\"openPopup('asetrecur.php?id=" . urlencode($row["id"]) . "')\" title='Lihat BDM Recur'>
                        <i class='fa-solid fa-chart-gantt' style='font-size: 20px; color: white;'></i>
                    </a>
                    <a href='#' onclick=\"openPopup('asetrecurdetail.php?kode=" . urlencode($row["kode"]) . "')\" title='Lihat Detail'>
                        <i class='fa-solid fa-list' style='font-size: 20px; color: oceanblue;'></i>
                    </a>
                    <a href='#' onclick=\"openPopup('asetedit.php?id=" . urlencode($row["id"]) . "')\" title='Edit BDM'>
                        <i class='fa-solid fa-screwdriver-wrench' style='font-size: 20px; color: blue;'></i>
                    </a>
                    <a href='#' onclick=\"openPopup('asetjual.php?id=" . urlencode($row["id"]) . "')\" title='Jual Aset'>
                        <i class='fa-solid fa-cart-shopping' style='font-size: 20px; color: yellow;'></i>
                    </a>
                    <a href='barcode.php?kode=" . urlencode($row["kode"]) ."'>
                        <i class='fa-solid fa-qrcode' style='font-size: 20px; color: blue;'></i>
                    </a>
                    <a href='asetdel.php?id=" . urlencode($row["id"]) . "' onclick=\"return confirm('Yakin ingin menghapus aset ini?')\">
                        <i class='fa-solid fa-trash-can' style='font-size: 20px; color: red;'></i>
                    </a>
                </div>
              </td>";
        echo "</tr>";
    }

    // Baris total
    echo "<tr style='font-weight: bold; background-color: #f0f0f0;'>";
    echo "<td colspan='6' style='text-align:right;'>Total:</td>";
    echo "<td>" . number_format($total_beli, 2, ',', '.') . "</td>";
    echo "<td>" . number_format($total_susut, 2, ',', '.') . "</td>";
    echo "<td>" . number_format($total_nilai_buku, 2, ',', '.') . "</td>";
    echo "<td colspan='2'></td>";
    echo "</tr>";

    echo "</tbody>";
    echo "</table>";
} else {
    echo "<p>Tidak ada data aset.</p>";
}

$conn->close();
?>



</div>

<script>
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toLowerCase();
        table = document.getElementById("asetTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; // Default hide the row
            tdNama = tr[i].getElementsByTagName("td")[1]; // Nama
            tdLokasi = tr[i].getElementsByTagName("td")[3]; // Lokasi
            if (tdNama || tdLokasi) {
                if (tdNama.innerHTML.toLowerCase().indexOf(filter) > -1 || tdLokasi.innerHTML.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = ""; // Show the row
                }
            }
        }
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
