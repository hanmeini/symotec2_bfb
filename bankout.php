<?php

  
    



require_once 'config1.php';


?>
<?php
$query = "SELECT bulan, tahun FROM close ORDER BY tahun DESC, bulan DESC LIMIT 1";
$result = $conn->query($query);

$latestMonth = 0;
$latestYear = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $latestMonth = $row["bulan"];
    $latestYear = $row["tahun"];
}

// Pastikan require config.php jika diperlukan
require_once 'config1.php';

// DB connections are now globally provided by config1.php ($conn and $pdo)
try {
    // Ambil kurs terbaru dari database
    $sqlKurs = "SELECT kurs, tanggal FROM kurs ORDER BY tanggal DESC LIMIT 1";
    $stmtKurs = $pdo->prepare($sqlKurs);
    $stmtKurs->execute();
    $resultKurs = $stmtKurs->fetch(PDO::FETCH_ASSOC);

    $kurs = $resultKurs['kurs'] ?? 0;
    $tanggal = $resultKurs['tanggal'] ?? 'Data tidak tersedia';
} catch (PDOException $e) {
    die("Gagal mengambil data kurs: " . $e->getMessage());
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
    <title>bank out www.symotech.id</title>
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
            max-width: 1800px;
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
     <script>
    function openCalPopup() {
        window.open('cal.php', 'Pilih Kurs', 'width=800,height=600');
    }

    function setKurs(kurs, tanggal) {
        document.getElementById('kurs').value = kurs;
        document.getElementById('tanggal_kurs').value = tanggal;
    }
    
    th:nth-child(2), td:nth-child(2) {
    width: 600px; /* Set lebar tetap */
    max-width: 600px;
    word-break: break-word; /* Memastikan teks bisa terpotong */
    white-space: normal; /* Mengizinkan wrap */
}

</script>
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
        
         <h2>Jurnal Bank Out</h2>
        
        <div style="display: flex; align-items: center; gap: 10px;"> 
    <p style="font-size: 14px; color: gray; margin: 0;">
        Kurs terbaru: <b>Rp <?= number_format($kurs, 0, ',', '.') ?></b> (<?= $tanggal ?>)
    </p>
    <button type="button" onclick="openCalPopup()" style="display: flex; align-items: center; gap: 5px;">
        <i class="fa-solid fa-calendar-days"></i> calculator kurs
    </button>
</div>
        
       
        <form id="formJurnal" action="save_jurnalbank.php" method="post" enctype="multipart/form-data">
            <label for="tanggal">Tanggal:</label><br>
             <input type="date" id="tanggal" name="tanggal" required>
               <label for="location">Location:</label>
            <select name="location" required>
                <option value="" disabled selected>Pilih Lokasi</option>
                <?php
              $sql2 = "SELECT idl, nama_cabang FROM location";
                $result2 = $conn->query($sql2);
                if ($result2->num_rows > 0) {
                    while ($row = $result2->fetch_assoc()) {
                        echo "<option value='{$row["idl"]}'>{$row["nama_cabang"]}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada cabang tersedia</option>";
                }
                ?>
            </select>

           
           
            
              
         
           <label for="lampiran">Lampiran File:</label>
    <input type="file" id="lampiran" name="lampiran[]"><br>
             <input type="text" id="kode_booking" name="kode_booking[]" placeholder="kode_booking" readonly>


<button type="button" onclick="openbookingPopup()">Pilih kode booking</button>
<br><br>
            <table id="jurnal_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Account Code</th>
                        <th></th>
                        <th>nominal</th>
                        <th>total bank</th>
                 
                            <th>keterangan</th>
               
                    </tr>
                </thead>
                <tbody>
                    <td>1</td>
                        <td>
                            <select name="coa[]" required onchange="updateAccountDetails(this)">
        <option value="">Pilih COA</option>
        <?php
        $sql = "SELECT account_code, account_name 
        FROM coa 
        WHERE layer = 4 AND account_code LIKE '112%'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['account_code']}' data-name='{$row['account_name']}'>
                        {$row['account_code']} - {$row['account_name']}
                      </option>";
            }
        } else {
            echo "<option value='' disabled>Tidak ada COA tersedia</option>";
        }
        ?>
    </select>
    <input type="hidden" name="account_code[]" readonly>
    <input type="hidden" name="account_name[]" readonly>
                        </td>
                        <td>
                            
                        </td>
                 <td><input type="hidden" name="debet[]" min="0" value="0" readonly></td>
<td id="total_debet_container">
    <span id="total_debet">0</span>
    <input type="hidden" id="hidden_total_debet" name="kredit[]" value="0" readonly>
</td>
                  
                         
                  <td><input type="text" name="keterangan[]" ></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        
                    </tr>
            
                    </tr>
                </tfoot>
            </table>
            
            <button type="button" onclick="addRow()">Tambah Baris</button>
            <br>
            <br>
            <h4>Biaya Bank:</h4>
<label><input type="checkbox" name="biaya_bank" value="2500" onchange="handleBiayaBankCheckbox(this)"> Rp 2.500</label><br>
<label><input type="checkbox" name="biaya_bank" value="6000" onchange="handleBiayaBankCheckbox(this)"> Rp 6.000</label><br>
<label><input type="checkbox" name="biaya_bank" value="10000" onchange="handleBiayaBankCheckbox(this)"> Rp 10.000</label><br>
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
    var totalDebet = 0;

    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var debetInput = cells[3].querySelector('input[name="debet[]"]');
        
        // Ambil nilai input debet dan hapus koma
        var debetValue = debetInput.value.replace(/,/g, ''); 
        
        // Ubah menjadi angka
        var debet = parseFloat(debetValue);

        if (!isNaN(debet)) {
            totalDebet += debet;
        }
    }

    // Fungsi untuk memformat angka dengan ribuan
    function formatRibuan(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Format total debet dan tampilkan ke elemen
    var formattedTotal = formatRibuan(totalDebet.toFixed(2));
    document.getElementById("total_debet").innerText = formattedTotal;

    // Update input hidden untuk total debet tanpa format ribuan
    document.getElementById("hidden_total_debet").value = totalDebet.toFixed(2);
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
                     <select name="coa[]" required onchange="updateAccountDetails(this)">
        <option value="">Pilih COA</option>
        <?php
        $sql = "SELECT account_code, account_name FROM coa WHERE layer = 4 
                AND account_code NOT LIKE '11%' 
                AND account_code NOT LIKE '12%'";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['account_code']}' data-name='{$row['account_name']}'>
                        {$row['account_code']} - {$row['account_name']}
                      </option>";
            }
        } else {
            echo "<option value='' disabled>Tidak ada COA tersedia</option>";
        }
        ?>
    </select>
 <input type="hidden" name="account_code[]" readonly>
    <input type="hidden" name="account_name[]" readonly>
                </td>
                <td>
                
                </td>
<td>
 <input 
    type="text" 
    name="debet[]" 
    min="0" 
    value=" " 
    oninput="formatNumber(this)" 
    onfocus="removeFormatting(this)" 
    onblur="applyFormatting(this)" 
    style="text-align: right;">
</td>




           <td><input type="hidden" name="kredit[]" min="0" value="0" readonly></td>
  <td><input type="text" name="keterangan[]" ></td>
 <td> <button type="button" onclick="deleteRow(this)">Hapus</button> </td>

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
  
    function updateAccountDetails(select) {
    let selectedOption = select.options[select.selectedIndex]; // Ambil opsi yang dipilih
    let accountCode = selectedOption.value; // Ambil account_code
    let accountName = selectedOption.getAttribute("data-name"); // Ambil account_name

    let td = select.closest("td"); // Cari elemen <td> terdekat
    if (td) {
        td.querySelector("input[name='account_code[]']").value = accountCode;
        td.querySelector("input[name='account_name[]']").value = accountName;
        td.querySelector(".coa-display").textContent = `${accountCode} - ${accountName}`; // Tampilkan hasil
    }
}
function deleteRow(button) {
    var row = button.closest("tr"); // Cari baris terdekat
    var table = document.getElementById("jurnal_table").getElementsByTagName('tbody')[0];

    if (table.rows.length > 1) { // Pastikan setidaknya ada satu baris yang tersisa
        row.remove(); // Hapus baris dari tabel
        updateRowNumbers(); // Perbarui nomor baris
        calculateTotal(); // Hitung ulang total setelah penghapusan
    } else {
        alert("Minimal harus ada satu baris jurnal.");
    }
}

function updateRowNumbers() {
    var table = document.getElementById("jurnal_table").getElementsByTagName('tbody')[0];
    var rows = table.getElementsByTagName('tr');

    for (var i = 0; i < rows.length; i++) {
        rows[i].getElementsByTagName('td')[0].innerText = i + 1; // Update nomor urut
    }
}


function handleBiayaBankCheckbox(checkbox) {
    if (checkbox.checked) {
        addBiayaBankRow(parseFloat(checkbox.value));
    } else {
        removeBiayaBankRow(parseFloat(checkbox.value));
    }
}

function addBiayaBankRow(nominal) {
    var table = document.getElementById("jurnal_table").getElementsByTagName('tbody')[0];
    var newRow = document.createElement("tr");
    newRow.classList.add("biaya-bank-row"); // Untuk identifikasi jika perlu dihapus

    newRow.setAttribute('data-nominal', nominal); // Tandai nominal agar bisa dihapus nanti

    newRow.innerHTML = `
        <td>${table.rows.length + 1}</td>
        <td>
            <input type="text" name="coa[]" value="82002" readonly>
        </td>
        <td>
            <input type="text" name="account_name[]" value="Beban Administrasi Bank" readonly>
            <input type="hidden" name="account_code[]" value="82002" readonly>
        </td>
        <td>
            <input type="text" name="debet[]" value="${nominal}" readonly style="text-align: right;">
        </td>
        <td> <input type="hidden" name="kredit[]" value="0" readonly> </td>
        <td><input type="text" name="keterangan[]" value="Biaya administrasi bank Rp ${formatWithCommas(nominal.toString())}" readonly></td>
    `;

    table.appendChild(newRow);
    updateRowNumbers();
    calculateTotal();
}

function removeBiayaBankRow(nominal) {
    const table = document.getElementById("jurnal_table").getElementsByTagName('tbody')[0];
    const rows = table.getElementsByClassName("biaya-bank-row");

    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        if (parseFloat(row.getAttribute("data-nominal")) === nominal) {
            row.remove();
            break;
        }
    }

    updateRowNumbers();
    calculateTotal();
}

  
    </script>
</body>
</html>
