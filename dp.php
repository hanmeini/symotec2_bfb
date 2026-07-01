<?php
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
    <a href='home.php' class='home-icon1'>
        <i class='fas fa-home'></i>
    </a>
    <a href='home.php' class='left-icon'>
        <i class='fa-solid fa-circle-left'></i>
    </a>
        </div>
    <div class="container">
        <h2>Jurnal Titipan Customer</h2>
        <form action="save_titipan.php" method="post" enctype="multipart/form-data" >
            <label for="tanggal">Tanggal:</label><br>
             <input type="date" id="tanggal" name="tanggal" required>
               <label for="location">Location:</label>
            <select name="location" required>
                <option value="" disabled selected>Pilih Lokasi</option>
                <?php
                $sql2 = "SELECT kode_cabang, nama_cabang FROM cabang";
                $result2 = $conn->query($sql2);
                if ($result2->num_rows > 0) {
                    while ($row = $result2->fetch_assoc()) {
                        echo "<option value='{$row["nama_cabang"]}'>{$row["nama_cabang"]}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada cabang tersedia</option>";
                }
                ?>
            </select>

            <label for="devisi">Devisi:</label>
            <select name="devisi" id="devisi" required>
                <option value="" disabled selected>Select Devisi</option>
                <?php
                $query = "SELECT id, nama_devisi FROM devisi";
                $result = $conn->query($query);
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='{$row['nama_devisi']}'>{$row['nama_devisi']}</option>";
                    }
                } else {
                    echo "<option value='' disabled>No Devisi Available</option>";
                }
                ?>
            </select>
            
              <select name="cust_id" required>
                <option value="" disabled selected>Customer</option>
                <?php
                $sql5 = "SELECT id, customer FROM cust";
                $result5 = $conn->query($sql5);
                if ($result5->num_rows > 0) {
                    while ($row = $result5->fetch_assoc()) {
                
                        echo "<option value='{$row["id"]}'>{$row["customer"]}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada cabang tersedia</option>";
                }
                ?>
            </select>
          <textarea name="keterangan[]" placeholder="Keterangan Jurnal" required rows="3"></textarea><br>
           <label for="lampiran">Lampiran File:</label>
    <input type="file" id="lampiran" name="lampiran[]"><br>
    
             <input type="text" id="kode_booking" name="kode_booking" placeholder="" readonly >
<button type="button" onclick="openbookingPopup()">Pilih kode booking</button>
<br><br>
            <table id="jurnal_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Account Code</th>
                        <th>Account Name</th>
                       
                        <th>total bank</th>
                  <th>nominal</th>
                      
               
                    </tr>
                </thead>
                <tbody>
                    <td>1</td>
                        <td>
                            <select name="coa[]" required onchange="getAccountName(this)">
                                <option value="">Pilih COA Bank</option>
                                <?php
                             $sql = "SELECT account_code, account_name 
        FROM coa 
        WHERE layer = 4 AND account_code LIKE '112%'";
;
                                $result = $conn->query($sql);
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        $account_code = $row["account_code"];
                                        $account_name = $row["account_name"];
                                        echo "<option value='$account_code'>$account_code - $account_name</option>";
                                    }
                                } else {
                                    echo "<option value='' disabled>Tidak ada COA tersedia</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="account_name[]" >
                        </td>
               
<td id="total_kredit_container">
    <span id="total_kredit">0</span>
    <input type="hidden" id="hidden_total_kredit" name="debet[]" value="0" readonly>
</td>



                   <td><input type="hidden" name="kredit[]" min="0" value="0" readonly></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right;">Total uang masuk</td>
                        <td id="total_kredit">0</td>
                        
                    </tr>
            
                    </tr>
                </tfoot>
            </table>
            
      
            <br>
            <br>
           <input type="submit" id="submit_button" value="Submit">

        </form>
    </div>

    <script>
        function calculateTotal() {
    var table = document.getElementById("jurnal_table");
    var tbody = table.getElementsByTagName('tbody')[0];
    var rows = tbody.getElementsByTagName('tr');
    var totalKredit = 0;

    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var kreditInput = cells[4].querySelector('input[name="kredit[]"]');
        
        if (kreditInput) {
            var kreditValue = kreditInput.value.replace(/,/g, ''); 
            var kredit = parseFloat(kreditValue);

            if (!isNaN(kredit)) {
                totalKredit += kredit;
            }
        }
    }

    function formatRibuan(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    var formattedTotal = formatRibuan(totalKredit.toFixed(2));
    document.getElementById("total_kredit").innerText = formattedTotal;
    document.getElementById("hidden_total_kredit").value = totalKredit.toFixed(2);
}




        

        function getAccountName(select) {
            var accountCode = select.value;
            var accountNameInput = select.parentNode.nextElementSibling.querySelector('input[name="account_name[]"]');

            // Send AJAX request to fetch account name based on account code
            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4 && xhr.status == 200) {
                    accountNameInput.value = xhr.responseText;
                }
            };
            xhr.open("GET", "get_account_name.php?account_code=" + accountCode, true);
            xhr.send();
        }
         // Panggil calculateTotal() setelah dokumen dimuat
window.onload = function() {
    // addRow(); // Hapus atau komentari baris ini agar baris tidak ditambahkan otomatis
    calculateTotal(); // Panggil calculateTotal() untuk memastikan nilai saldo sudah diperbarui
};


        function addRow() {
            var table = document.getElementById("jurnal_table").getElementsByTagName('tbody')[0];
            var newRow = document.createElement("tr");

            newRow.innerHTML = `
                <td>${table.rows.length + 1}</td>
                <td>
                  
                    <input type="text" name="coa[]" value="22107" required onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>

                    </select>
                </td>
                <td>
               <input type="text" name="account_name[]" value="Titipan" readonly>

                </td>


           <td><input type="hidden" name="debet[]" min="0" value="0" readonly></td>

 <td>
 <input 
    type="text" 
    name="kredit[]" 
    min="0" 
    value="0" 
    oninput="formatNumber(this)" 
    onfocus="removeFormatting(this)" 
    onblur="applyFormatting(this)" 
    style="text-align: right;">
</td>
            `;

            table.appendChild(newRow);

            // Set Account Name for the newly added row
            getAccountName(newRow.querySelector('select[name="coa[]"]'));

            // Calculate total after adding row
            calculateTotal();
        }

        window.onload = function() {
            addRow();
        };

        document.getElementById("jurnal_table").addEventListener('input', function() {
            calculateTotal();
        });
        function openbookingPopup() {
    // Buka halaman cust.php sebagai popup
    window.open('booking.php', 'Pilih Pelanggan', 'width=800,height=600');
}
// Fungsi untuk menerima kode dari popup
function setbookingCode(kode_booking) {
    document.getElementById('kode_booking').value = kode_booking;

}
function formatRibuan(input) {
    // Menghapus karakter selain angka
    let value = input.value.replace(/[^\d]/g, '');

    // Format dengan pemisah ribuan
    if (value.length > 3) {
        value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Update nilai input dengan pemisah ribuan
    input.value = value;
}

function updateNumberValue(input) {
    // Ambil nilai input dengan pemisah ribuan
    let value = input.value.replace(/[^\d]/g, '');

    // Cari input hidden untuk menyimpan nilai dalam format number
    let hiddenInput = input.parentElement.querySelector('input[type="number"]');
    hiddenInput.value = value;
}

// Menghapus format ribuan saat fokus
  function removeFormatting(input) {
    input.value = input.value.replace(/,/g, ''); // Hapus koma
  }

  // Menambahkan format ribuan saat blur
  function applyFormatting(input) {
    let value = input.value.replace(/,/g, ''); // Hapus koma untuk mendapatkan nilai mentah
    if (value !== '' && !isNaN(value)) {
      input.value = formatWithCommas(value); // Tambahkan format ribuan
    }
  }

  // Format angka besar saat pengguna mengetik
  function formatLargeNumber(input) {
    let value = input.value.replace(/,/g, ''); // Hapus koma
    // Pastikan hanya angka yang diizinkan
    if (/^\d*$/.test(value)) {
      input.value = formatWithCommas(value); // Tampilkan dengan format ribuan
    } else {
      input.value = input.value.slice(0, -1); // Hapus karakter non-angka terakhir
    }
  }

  // Fungsi untuk menambahkan format ribuan
  function formatWithCommas(value) {
    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ','); // Tambahkan koma
  }
  
  
   document.getElementById('formJurnal').addEventListener('submit', function(event) {
            // Tampilkan notifikasi konfirmasi
            const confirmation = window.confirm("Apakah Anda sudah yakin dengan jurnalnya?");
            if (!confirmation) {
                // Jika pengguna memilih 'Tidak', batalkan submit
                event.preventDefault();
            }
        });
  
function validateKodeBooking() {
    const kodeBooking = document.getElementById('kode_booking').value;

    if (!kodeBooking) {
        alert("Kode Booking wajib diisi! Silakan pilih kode booking.");
        return false; // Form tidak dikirim
    }

    return true; // Form boleh dikirim
}


    </script>
</body>
</html>