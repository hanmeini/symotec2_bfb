<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);


// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan


// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi dari config.php
require_once 'config1.php';


// Ambil variabel dari environment


// Periksa koneksi pertama



$query = "SELECT bulan, tahun FROM close ORDER BY tahun DESC, bulan DESC LIMIT 1";
$result = $conn->query($query);

$latestMonth = 0;
$latestYear = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $latestMonth = $row["bulan"];
    $latestYear = $row["tahun"];
}


?>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        let latestMonth = <?php echo $latestMonth; ?>;
        let latestYear = <?php echo $latestYear; ?>;

        let dateInput = document.getElementById("tanggal");
        
        dateInput.addEventListener("change", function () {
            let selectedDate = new Date(this.value);
            let selectedMonth = selectedDate.getMonth() + 1; // JavaScript month starts from 0
            let selectedYear = selectedDate.getFullYear();

            if (selectedYear < latestYear || (selectedYear == latestYear && selectedMonth <= latestMonth)) {
                alert("Tanggal tidak valid! Bulan dan tahun harus lebih besar dari yang terakhir di-close.");
                this.value = "";
            }
        });
    });
</script>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bank out</title>
       <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
    <style>
        body {
        color: maroon; /* Warna teks */
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9); /* Gradien dari atas ke bawah */
    }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        form {
            margin-top: 20px;
        }
        label {
            font-weight: bold;
        }
        input[type="date"],
        select,
        textarea,
        input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        input[type="number"] {
            width: 100px;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        .balance {
            font-weight: bold;
        }
        .green {
            color: green;
        }
        .red {
            color: red;
        }
        input[readonly] {
            background-color: maroon;
            color: white; /* Warna teks putih untuk kontras dengan marun */
            border: 1px solid #800000; /* Tambahkan border agar lebih jelas */
        }
        .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 {
            left: 10px;
        }
        .left-icon {
            right: 10px;
        }
    </style>
</head>
<body>
    <div class='table-container'>
    <a href='home.php' class='home-icon1'><i class='fas fa-home'></i></a>
    <a href='home.php' class='left-icon'><i class='fa-solid fa-circle-left'></i></a>
</div>

<div class="container">
    <h2>INPUT CN AR</h2>
    <form id="formJurnal" action="save_cnar.php" method="post" enctype="multipart/form-data" onsubmit="return validateKodeBooking();">
    
        <input type="date" id="tanggal" name="tanggal" required>

      

        <select name="id_cust" required>
            <option value="" disabled selected>vendor/supplier</option>
            <?php
            $sql5 = "SELECT id, nama FROM cust ORDER BY nama ASC";
            $result5 = $conn->query($sql5);
            while ($row = $result5->fetch_assoc()) {
                echo "<option value='{$row["id"]}'>{$row["nama"]}</option>";
            }
            ?>
        </select>

        <textarea name="description" placeholder="Keterangan " required rows="3"></textarea><br>

   

        <input type="text" id="kode_booking" name="kode_booking" placeholder="" readonly>
        <button type="button" onclick="openbookingPopup()">Pilih kode booking</button>
        <br>  <br>
          <label for="lampiran">Nilai CN:</label>
<input type="text" name="cn" value="0" oninput="formatLargeNumber(this); calculateTotal();" onfocus="removeFormatting(this)" onblur="applyFormatting(this)" style="text-align:right;">

        <br><br>
        
        <br>
       
        <br><br>

        <input type="submit" id="submit_button" value="Submit">
    </form>
</div>

<script>
function calculateTotal() {
    var table = document.getElementById("jurnal_table");
    var rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    var totalDebet = 0;

    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var debetInput = cells[3].querySelector('input[name="debet[]"]');
        if (debetInput) {
            var debetValue = debetInput.value.replace(/,/g, '');
            var debet = parseFloat(debetValue);
            if (!isNaN(debet)) {
                totalDebet += debet;
            }
        }
    }

    function formatRibuan(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    var formattedTotal = formatRibuan(totalDebet.toFixed(2));
    document.getElementById("total_debet").innerText = formattedTotal;
    document.getElementById("hidden_total_debet").value = totalDebet.toFixed(2);
}

function addRow() {
    var table = document.getElementById("jurnal_table").getElementsByTagName('tbody')[0];
    var newRow = document.createElement("tr");

    newRow.innerHTML = `
        <td>${table.rows.length + 1}</td>
        <td><input type="text" name="coa[]" value="12901" readonly onchange="getAccountName(this)"></td>
        <td><input type="text" name="account_name[]" value="Titipan" readonly></td>
        <td><input type="text" name="debet[]" value="0" oninput="formatLargeNumber(this); calculateTotal();" onfocus="removeFormatting(this)" onblur="applyFormatting(this)" style="text-align:right;"></td>
        <td></td>
    `;
    table.appendChild(newRow);
    calculateTotal();
}

function getAccountName(select) {
    var accountCode = select.value;
    var accountNameInput = select.parentNode.nextElementSibling.querySelector('input[name="account_name[]"]');
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            accountNameInput.value = xhr.responseText;
        }
    };
    xhr.open("GET", "get_account_name.php?account_code=" + accountCode, true);
    xhr.send();
}

function openbookingPopup() {
    window.open('booking.php', 'Pilih Pelanggan', 'width=800,height=600');
}

function setbookingCode(kode_booking) {
    document.getElementById('kode_booking').value = kode_booking;
}

function formatLargeNumber(input) {
    let value = input.value.replace(/,/g, '');
    if (/^\d*$/.test(value)) {
        input.value = formatWithCommas(value);
    } else {
        input.value = input.value.slice(0, -1);
    }
}

function formatWithCommas(value) {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function removeFormatting(input) {
    input.value = input.value.replace(/,/g, '');
}

function applyFormatting(input) {
    let value = input.value.replace(/,/g, '');
    if (value !== '' && !isNaN(value)) {
        input.value = formatWithCommas(value);
    }
}

function validateKodeBooking() {
  
    return confirm("Apakah Anda sudah yakin dengan CN?");
}

window.onload = function() {
    addRow();
    calculateTotal();
};

document.getElementById("jurnal_table").addEventListener('input', function () {
    calculateTotal();
});
</script>

</body>
</html>