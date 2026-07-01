<?php

// Load konfigurasi dari config.php
require_once 'config1.php';


// Ambil parameter bulan dari form atau URL
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m'); // Default ke bulan sekarang
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y'); // Default ke tahun sekarang

// Query untuk mendapatkan data berdasarkan bulan dan mengabaikan kolom ab yang terisi
$query = "SELECT id, no_recuring, kode, tanggal, keterangan, lampiran, coad, account_named, debet, coak, account_namek, kredit, location, devisi, ab 
          FROM recuring 
          WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? AND (ab IS NULL OR ab = '')";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $bulan, $tahun);
$stmt->execute();

// Bind hasil query ke variabel
$stmt->bind_result($id, $no_recuring, $kode, $tanggal, $keterangan, $lampiran, $coad, $account_named, $debet, $coak, $account_namek, $kredit, $location, $devisi, $ab);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <title>Detail Data Recurring</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
            color: #333;
        }

        h1 {
            color: #444;
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px auto;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
            color: #333;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }

        a {
            text-decoration: none;
            font-size: 20px;
            color: #555;
        }

        a:hover {
            color: #ff0000;
        }

        .filter-form {
            text-align: center;
            margin-bottom: 20px;
        }

        .filter-form select, .filter-form button {
            padding: 10px;
            font-size: 16px;
        }

        .action-buttons {
            text-align: center;
            margin-top: 20px;
        }

        .action-buttons button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
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

    </style>
    <script>
        function toggleSelectAll(source) {
            checkboxes = document.getElementsByName('selected_ids[]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>
<body>
     <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h1>Proses ASET/BDM Recurring</h1>

    <div class="filter-form">
        <form method="GET" action="">
            <label for="bulan">Bulan:</label>
            <select name="bulan" id="bulan">
                <?php
                for ($i = 1; $i <= 12; $i++) {
                    $selected = ($i == $bulan) ? "selected" : "";
                    echo "<option value='$i' $selected>" . date('F', mktime(0, 0, 0, $i, 10)) . "</option>";
                }
                ?>
            </select>

            <label for="tahun">Tahun:</label>
            <select name="tahun" id="tahun">
                <?php
                for ($i = date('Y') - 5; $i <= date('Y'); $i++) {
                    $selected = ($i == $tahun) ? "selected" : "";
                    echo "<option value='$i' $selected>$i</option>";
                }
                ?>
            </select>

            <button type="submit">Filter</button>
        </form>
    </div>

    <form method="POST" action="save_recurjurnal.php">
        <?php
        $totalDebet = 0;
        $totalKredit = 0;

        if ($stmt->fetch()) {
            echo "<table>";
            echo "<tr>
                    <th><input type='checkbox' onClick='toggleSelectAll(this)'></th>
                    <th>ID</th>
                    <th>No Recurring</th>
                    <th>Kode</th>
                    <th>Tanggal</th>
                    <th>Keterangan</th>
                    <th>Lampiran</th>
                    <th>COA Debit</th>
                    <th>Account Name Debit</th>
                    <th>Debet</th>
                    <th>COA Kredit</th>
                    <th>Account Name Kredit</th>
                    <th>Kredit</th>
                    <th>Location</th>
                    <th>Devisi</th>
                  </tr>";

            // Tampilkan data
            do {
                $totalDebet += $debet;
                $totalKredit += $kredit;

                echo "<tr>";
                echo "<td><input type='checkbox' name='selected_ids[]' value='" . $id . "'></td>";
                echo "<td>" . $id . "</td>";
                echo "<td>" . htmlspecialchars($no_recuring) . "</td>";
                echo "<td>" . htmlspecialchars($kode) . "</td>";
                echo "<td>" . htmlspecialchars($tanggal) . "</td>";
                echo "<td>" . htmlspecialchars($keterangan) . "</td>";
                echo "<td>" . htmlspecialchars($lampiran) . "</td>";
                echo "<td>" . htmlspecialchars($coad) . "</td>";
                echo "<td>" . htmlspecialchars($account_named) . "</td>";
                echo "<td>" . number_format($debet, 2) . "</td>";
                echo "<td>" . htmlspecialchars($coak) . "</td>";
                echo "<td>" . htmlspecialchars($account_namek) . "</td>";
                echo "<td>" . number_format($kredit, 2) . "</td>";
                echo "<td>" . htmlspecialchars($location) . "</td>";
                echo "<td>" . htmlspecialchars($devisi) . "</td>";
                echo "</tr>";
            } while ($stmt->fetch());

            // Tampilkan total debet dan kredit
            echo "<tr style='font-weight: bold; background-color: #f4f4f4;'>";
            echo "<td colspan='9' style='text-align: right;'>Total</td>";
            echo "<td>" . number_format($totalDebet, 2) . "</td>";
            echo "<td colspan='2'></td>";
            echo "<td>" . number_format($totalKredit, 2) . "</td>";
            echo "<td colspan='2'></td>";
            echo "</tr>";

            echo "</table>";
        } else {
            echo "<p>Data tidak ditemukan untuk bulan: " . htmlspecialchars($bulan) . " dan tahun: " . htmlspecialchars($tahun) . "</p>";
        }

        // Tutup koneksi
        $stmt->close();
        $conn->close();
        ?>

        <div class="action-buttons">
            <button type="submit">Submit Selected</button>
        </div>
    </form>
</body>
</html>
