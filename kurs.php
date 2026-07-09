<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://bfb.symotech.my.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $allowed_referer_domain) !== 0) {
    header("Location: https://bfb.symotech.my.id");
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi dari config.php
require_once 'config.php';



// Ambil variabel dari environment
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("Kesalahan: DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");


// Buat koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Periksa koneksi pertama
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Master Kurs</title>
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

        .home-icon i, .left-icon i {
            color: maroon;
            font-size: 24px;
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
    <h2>DAFTAR KURS</h2>

    <!-- Filter input and Add button -->
    <div class="search-box">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari berdasarkan tanggal atau kurs...">
        <button onclick="location.href='kursin.php'"><i class="fa-solid fa-plus"></i> Add</button>
    </div>

   <?php


// Query untuk mengambil data dari tabel kurs
$sql = "SELECT * FROM kurs ORDER by idkrus DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table id='coaTable'>";
    echo "<thead><tr><th>ID</th><th>Tanggal</th><th>Kurs</th><th>Action</th></tr></thead>";
    echo "<tbody>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row["idkrus"] . "</td>";
        echo "<td>" . $row["tanggal"] . "</td>";
        echo "<td>" . number_format($row["kurs"], 0, ',', '.') . "</td>";
        echo "<td style='max-width: 70px; margin-right: 10px; white-space: nowrap;'>";
        echo "<a href='kursdel.php?id=" . $row["idkurs"] . "'><i class='fa-solid fa-trash-can' style='font-size: 20px; color: red;'></i></a>";
        echo "</td>";
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
} else {
    echo "Tidak ada data kurs.";
}

$conn->close();
?>

</div>

<script>
    function filterTable() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toLowerCase();
        table = document.getElementById("coaTable");
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; // Default hide the row
            tdTanggal = tr[i].getElementsByTagName("td")[0];
            tdKurs = tr[i].getElementsByTagName("td")[1];
            if (tdTanggal || tdKurs) {
                if (tdTanggal.innerHTML.toLowerCase().indexOf(filter) > -1 || tdKurs.innerHTML.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
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
