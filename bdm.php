<?php

  
    



require_once 'config1.php';


?>
<script>
function openPopup(url) {
    window.open(url, '_blank', 'width=800,height=1050,scrollbars=yes,resizable=yes');
}
</script>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BDM - symotech.id</title>
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
    <h2>DAFTAR BDM</h2>

    <!-- Filter input and Add button -->
    <div class="search-box">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari berdasarkan nama atau lokasi...">
        <button onclick="location.href='bdmin.php'"><i class="fa-solid fa-plus"></i> Add (via bank)</button>
          <button onclick="location.href='bdminc.php'"><i class="fa-solid fa-plus"></i> Add (via cash)</button>
            <button onclick="location.href='bdmc.php'"></i>BDM cut-off</button>
           <button onclick="location.href='download_excelbdm.php'"><i class="fa-solid fa-file-excel"></i> Download Excel</button>
      <!-- <button onclick="location.href='download_pdf.php'"><i class="fa-solid fa-file-pdf"></i> Download PDF</button> -->
    </div>

 <?php

// Query untuk mengambil data dari tabel bdm
$sql = "SELECT * FROM bdm ORDER BY id";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table id='asetTable'>";
    echo "<thead><tr><th>No</th><th>Kode</th><th>Nama</th><th>Tanggal</th><th>Lokasi</th><th>Devisi</th><th>Awal</th><th>Dibebankan</th><th>Sisa</th><th>No Recuring</th><th>Action</th></tr></thead>";
    echo "<tbody>";

    $no = 1; // Nomor urut dimulai dari 1

    while ($row = $result->fetch_assoc()) {
        // Ambil total dari kolom `susut` di tabel `susut` berdasarkan `recuring`
        $kode = $row["kode"];
        $sql_susut = "SELECT SUM(susut) AS total_susut FROM susut WHERE kode = '$kode'";
        $result_susut = $conn->query($sql_susut);
        $total_susut = 0;

        if ($result_susut->num_rows > 0) {
            $susut_row = $result_susut->fetch_assoc();
            $total_susut = $susut_row['total_susut'];
        }

        // Hitung nilai buku
        $nilai_buku = $row["beli"] - $total_susut;

        echo "<tr>";
        echo "<td>" . $no++ . "</td>"; // Menampilkan nomor urut
        echo "<td>" . $row["kode"] . "</td>";
        echo "<td>" . $row["nama"] . "</td>";
        echo "<td>" . $row["tanggal"] . "</td>";
        echo "<td>" . $row["location"] . "</td>";
        echo "<td>" . $row["devisi"] . "</td>";
        echo "<td>" . number_format($row["beli"], 2, ',', '.') . "</td>";
        echo "<td>" . number_format($total_susut, 2, ',', '.') . "</td>";
        echo "<td>" . number_format($nilai_buku, 2, ',', '.') . "</td>";
        echo "<td>" . $row["recuring"] . "</td>";

        echo "<td style='max-width: 40px; padding: 2px;'>";
        echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 5px;'>";

        echo "<a href='#' onclick=\"openPopup('bdmrecur.php?id=" . urlencode($row["id"]) . "')\" title='Lihat BDM Recur'>";
        echo "<i class='fa-solid fa-chart-gantt' style='font-size: 20px; color: white;'></i></a>";

        echo "<a href='#' onclick=\"openPopup('bdmrecurdetail.php?kode=" . urlencode($row["kode"]) . "')\" title='Lihat Detail'>";
        echo "<i class='fa-solid fa-list' style='font-size: 20px; color: oceanblue;'></i></a>";

        echo "<a href='#' onclick=\"openPopup('bdmedit.php?id=" . urlencode($row["id"]) . "')\" title='Edit BDM'>";
        echo "<i class='fa-solid fa-screwdriver-wrench' style='font-size: 20px; color: blue;'></i></a>";

        echo "<a href='bdmdel.php?id=" . $row["id"] . "'><i class='fa-solid fa-trash-can' style='font-size: 20px; color: red;'></i></a>";
        echo "</div>";
        echo "</td>";

        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
} else {
    echo "Tidak ada data bdm.";
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
