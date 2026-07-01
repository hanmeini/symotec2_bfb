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




if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';


if ($conn->connect_error) die("Koneksi gagal");

// Ambil nilai 'J' dari URL
$id = isset($_GET['J']) ? $_GET['J'] : null;






?>

<!-- Tambahkan Skrip untuk Notifikasi -->
<?php if (isset($success_message)): ?>
    <script>
        alert("<?php echo $success_message; ?>");
        window.close();
    </script>
<?php elseif (isset($error_message)): ?>
    <p style="color: red;"><?php echo $error_message; ?></p>
<?php endif; ?>
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


?>
<?php
// Query ke tabel `BELI`
$sql = "SELECT  id, 
                tanggal, 
                inv, 
                kodebooking, 
                cust_id, 
                tagihan, 
                bayar, 
                sisa, 
                location,
                devisi,
                pph23
        FROM BELI 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Query preparation failed: " . $conn->error);
}

$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->bind_result($id, $tanggal, $inv, $kodebooking, $cust_id, $tagihan,  $bayar, $sisa, $location, $devisi, $pph23);


$total_tagihan = 0;

// Proses hasil query
$pph23_data = [];
while ($stmt->fetch()) {
    $pph23_data[] = [
        'id' => $id,
        'tanggal' => $tanggal,
        'inv' => $inv,
        'kodebooking' => $kodebooking,
        'cust_id' => $cust_id,

        'tagihan' => $tagihan,

        'bayar' => $bayar,
        'sisa' => $sisa,
        'location' => $location,
        'devisi' => $devisi,
        'pph23' => $pph23,
    ];
 
    $total_tagihan += $tagihan;
}
$stmt->close();

// Query ke tabel `customer`
foreach ($pph23_data as &$row) {
    $sql_customer = "SELECT customer FROM customer WHERE id = ?";
    $stmt_customer = $conn->prepare($sql_customer);
    if ($stmt_customer === false) {
        die("Query preparation failed: " . $conn->error);
    }

    $stmt_customer->bind_param("i", $row['cust_id']);
    $stmt_customer->execute();
    $stmt_customer->bind_result($customer_name);
    if ($stmt_customer->fetch()) {
        $row['customer_name'] = $customer_name;
    } else {
        $row['customer_name'] = "Unknown";
    }
    $stmt_customer->close();
}

// Output data
foreach ($pph23_data as $data) {
    echo "<p style='display: none;'>ID: {$data['id']}, Tanggal: {$data['tanggal']}, Customer: {$data['customer_name']}</p>";
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
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
       body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }

        .table-container {
            margin: 5px auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            max-width: 800px;
        }

        h1 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0px 0;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table th {
            background-color: #f9f9f9;
            color: #555;
        }

        .total {
            margin: 20px 0;
            font-size: 0.8em;
        }

        .total h3 {
            color: #555;
        }

        form {
            margin-top: 0px;
        }

        label {
            display: block;
            font-size: 0.9em;
            margin-bottom: 0px;
        }

        input[type="text"],select {
            width: 100%;
            padding: 2px;
            margin-bottom: 0px;
            border: 1px solid #ddd;
            border-radius: 0px;
        }

        .button {
            padding: 10px 20px;
            background-color: #28a745;
            color: blue;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .button:hover {
            background-color: #218838;
        }

        .home-icon1, .left-icon {
            text-decoration: none;
            font-size: 1.5em;
            color: #555;
        }

        .home-icon1 {
            margin-right: 10px;
        }

        .no-data {
            color: red;
            text-align: center;
        }
         input[readonly] {
            background-color: green;
            color: white; /* Warna teks putih untuk kontras dengan marun */
            border: 0px solid #800000; /* Tambahkan border agar lebih jelas */
        }
      .form-group {
            display: flex;
            flex-direction: column;
            flex: 1 1 calc(50% - 20px); /* Mengatur 2 kolom */
        }
  .form-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px; /* Jarak antar elemen */
        }
  th:nth-child(1),
        td:nth-child(2) {
            width: 5%; /* Kolom No. */
        }

        th:nth-child(2),
        td:nth-child(2) {
            width: 20%; /* Kolom Account Code */
        }

        th:nth-child(3),
        td:nth-child(3) {
            width: 30%; /* Kolom Account Name */
        }

        th:nth-child(4),
        td:nth-child(4) {
            width: 20%; /* Kolom Total Bank */
        }

        th:nth-child(5),
        td:nth-child(5) {
            width: 25%; /* Kolom Nominal */
        }
   #jurnal_table {
            width: 100%;
            border-collapse: collapse;
        }

        /* Atur lebar kolom */
        #jurnal_table th:nth-child(1), 
        #jurnal_table td:nth-child(1) {
            width: 5%; /* Kolom No. */
        }

        #jurnal_table th:nth-child(2), 
        #jurnal_table td:nth-child(2) {
            width: 1%; /* Kolom Account Code */
        }

        #jurnal_table th:nth-child(3), 
        #jurnal_table td:nth-child(3) {
            width: 30%; /* Kolom Account Name */
        }

        #jurnal_table th:nth-child(4), 
        #jurnal_table td:nth-child(4) {
            width: 20%; /* Kolom Total Bank */
        }

        #jurnal_table th:nth-child(5), 
        #jurnal_table td:nth-child(5) {
            width: 20%; /* Kolom Nominal */
        }

        /* Styling untuk header */
        #jurnal_table th {
            background-color: #f4f4f4;
            font-weight: bold;
            text-align: left;
            padding: 8px;
        }

        /* Styling untuk isi tabel */
        #jurnal_table td {
            padding: 8px;
            border: 1px solid #ddd;
        }

        /* Styling untuk footer */
        #jurnal_table tfoot td {
            font-weight: bold;
            text-align: right;
            background-color: #e9e9e9;
        }
</style>

    </style>
</head>
<body>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>faktur pajak belum dibuat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Style yang sama seperti sebelumnya */
    </style>
</head>
<body>
<div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="pos.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
   <?php if (!empty($pph23_data)): ?>

  

    <p>Tanggal INV : <?php echo htmlspecialchars($tanggal); ?></p>
    

    <table>
        <tr>
      
            
            <th>Customer</th>
      
           
            <th>Tagihan</th>
            <th>Bayar</th>
            <th>PPH23</th>
             <th>Sisa</th>
        </tr>
        <?php foreach ($pph23_data as $data): 
        ?>
         <?php
         
                   ?>
            <tr>
                
        
                <td><?php echo htmlspecialchars($data['customer_name']); ?></td>
        
                <td><?php echo number_format($data['tagihan'], 2); ?></td>
                       <td><?php echo number_format((float)($data['bayar'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float) $data['pph23'], 2); ?></td>
                      <td><?php echo number_format($data['sisa'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <br>    <br>    <br>
<?php
 // Query untuk ambil data
$sqlt = "SELECT idn, tanggal, no_cn_dn, dn FROM cndn
WHERE (inv IS NULL OR inv = '') 
AND dn > 0 
AND id_cust = '$cust_id'";

$result = $conn->query($sqlt);

// Tampilkan hasil
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID DN</th><th>Tanggal</th><th>Nominal DN</th><th>No DN</th></tr>";
    while ($row = $result->fetch_assoc()) {
       echo "<tr data-id='" . $row['idn'] . "' data-nominal='" . $row['dn'] . "'data-no='" . $row['no_cn_dn'] . "'>";
echo "<td>" . $row['idn'] . "</td>";
echo "<td>" . $row['tanggal'] . "</td>";
echo "<td>" . number_format($row['dn'], 2) . "</td>";
echo "<td>" . $row['no_cn_dn'] . "</td>";
echo "</tr>";

    }
    echo "</table>";
} else {
echo "<p style='color: red;'>Tidak ada data DN yang ditemukan.</p>";
}


?>
    <div class="total">
        
        
    </div>


        <?php if (isset($success_message)): ?>
            <p style="color: green;"><?php echo $success_message; ?></p>
        <?php elseif (isset($error_message)): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        
    <?php else: ?>
        <div class="no-data">
            <p>Tidak ada transaksi untuk kolom J: <?php echo htmlspecialchars($id); ?></p>
        </div>
    <?php endif; ?>
    <div class="container">

        <form id="formJurnal" action="savein_apdn.php" method="post" enctype="multipart/form-data">
            
                <input type="number" id="totalSum" name="totalSum" readonly style="display: none;">
                <input type="number" id="totalpph23" name="totalpph23" readonly style="display: none;">
<input type="hidden" name="idbeli" value="<?php echo htmlspecialchars($data['id']); ?>">


                
                <input 
        type="number" 
         id="bayar" 
        name="bayar" 
        value="<?php echo number_format($data['bayar'], 2, '.', ''); ?>" 
        step="0.01" 
        required
    style="display: none;">
     <input 
        type="number" 
         id="pph23" 
        name="bayar" 
        value="<?php echo number_format($data['pph23'], 2, '.', ''); ?>" 
        step="0.01" 
        required
    style="display: none;">
     <input 
        type="number" 
         id="tagihan" 
        name="tagihan" 
        value="<?php echo number_format($data['tagihan'], 2, '.', ''); ?>" 
        step="0.01" 
        required
  style="display: none;">
    <input type="number" id="totalSisa" name="totalSisa" readonly style="display: none;">
     <input type="number" id="titipansisa" name="titipansisa" readonly style="display: none;">
    
       <div class="form-container">
   
             <div class="form-group">
                <label for="tanggal">Tanggal pembayaran:</label>
                <input type="date" id="tanggal" name="tanggal" required>
            </div>
            <div class="form-group">
                <label for="idt">ID DN:</label>
               <input type="text" name="idt" required>
            </div>
            <div class="form-group">
                <label for="location">Location:</label>
                <input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="devisi">Devisi:</label>
                <input type="text" name="devisi" value="<?php echo htmlspecialchars($devisi); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="kode_booking">Kode Booking:</label>
                <input type="text" name="kode_booking" value="<?php echo htmlspecialchars($kodebooking); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="inv">Invoice:</label>
                <input type="text" name="inv" value="<?php echo htmlspecialchars($inv); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="cust_id">Customer ID:</label>
                <input type="text" name="cust_id" value="<?php echo htmlspecialchars($cust_id); ?>" readonly>
            </div>
            <div class="form-group">
                <label for="keterangan">Keterangan:</label>
                <input type="text" name="keterangan[]" value="<?php echo 'Pembayaran DN ' . htmlspecialchars($inv); ?>" placeholder="Keterangan Jurnal" required>
            </div>
 
</div>
<br><br>

            <table id="jurnal_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Account Code</th>
                        <th>Account Name</th>
                       
                        <th>Debet</th>
                  <th>Kredit</th>
                      
               
                    </tr>
                </thead>
                <tbody>
                   <td>1</td>
                        <td>
                        <input type="text" name="coa[]" value="81104" required  onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>
                        </td>
                        <td>
                            <input type="text" name="account_name[]" value="Pendapatan Lain dari CNDN" readonly>
                        </td>
                 <td><input type="hidden" name="debet[]" min="0" value="0" readonly></td>

  <td>
 <input 
    type="text" 
    name="kredit[]" 
    min="" 
    value="" 
    oninput="formatNumber(this)" 
    onfocus="removeFormatting(this)" 
    onblur="applyFormatting(this)" 
    style="text-align: right;">
</td>
                  
                  
                    </tr>
                    
                    <td>2</td>
                        <td>
                    <input type="text" name="coa[]" value="21101" required onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>

                    </select>
                </td>
                <td>
               <input type="text" name="account_name[]" value="Hutang Usaha Forwarding - EMKL" readonly>

<td id="total_kredit_container" style="background-color: green; color: white;">
    <span id="total_kredit"></span>
    <input type="hidden" id="hidden_total_kredit" name="debet[]" value="0" readonly>
</td>





                   <td><input type="hidden" name="kredit[]" min="0" value="0" readonly></td>

                  
                  
                    </tr>
                    <td>3</td>
                        <td>
                    <input type="text" name="coa[]" value="21101" required onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>

                    </select>
                </td>
                <td>
               <input type="text" name="account_name[]" value="Hutang Usaha Forwarding - EMKL" readonly>

<td id="total_kredit_container2" style="background-color: green; color: white;">
    <span id="total_kredit2"></span>
    <input type="hidden" id="hidden_total_kredit2" name="debet[]" value="0" readonly>
</td>





                   <td><input type="hidden" name="kredit[]" min="0" value="0" readonly></td>
                   </tr>
 

                </tbody>
                <tfoot>
                    <tr>
                        
                        
                    </tr>
            
                    </tr>
                </tfoot>
            </table>
           
           
            <br>
            <br>
           <input type="submit" id="submit_button" value="Submit">

        </form>
    </div>
</div>
</body>

    <script>
        function calculateTotal() {
    var table = document.getElementById("jurnal_table");
    var tbody = table.getElementsByTagName('tbody')[0];
    var rows = tbody.getElementsByTagName('tr');
    
    var totalKredit = 0;
    var totalKredit2 = 0;

    for (var i = 0; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var kreditInput = cells[4]?.querySelector('input[name="kredit[]"]');

        if (kreditInput) {
            var kreditValue = kreditInput.value.replace(/,/g, '');
            var kredit = parseFloat(kreditValue);

            if (!isNaN(kredit)) {
                if (i === 0) {
                    totalKredit = kredit;
                }
                if (i === 3) {
                    totalKredit2 = kredit;
                }
            }
        }
    }
    
     var idtInput = document.querySelector('input[name="idt"]');
    var idt = idtInput.value.trim();

    // Cari di tabel titipan untuk ambil nominal
    var nominal = 0;
    var titipanRows = document.querySelectorAll('table tr[data-id]');
    titipanRows.forEach(function(row) {
        if (row.getAttribute('data-id') === idt) {
            nominal = parseFloat(row.getAttribute('data-nominal')) || 0;
        }
    })

    // Ambil nilai bayar
    var bayarInput = document.getElementById("bayar");
    var bayar = parseFloat(bayarInput?.value.replace(/,/g, '')) || 0;
    var pph23Input = document.getElementById("pph23");
    var pph23 = parseFloat(pph23Input?.value.replace(/,/g, '')) || 0;


    // Hitung total sum
    var totalSum = totalKredit + bayar;
    
    var totalpph23 = pph23 + totalKredit2;

    // Ambil nilai sisa
    var sisaInput = document.getElementById("tagihan");
    var sisa = parseFloat(sisaInput?.value.replace(/,/g, '')) || 0;
   var totalSisa = sisa - totalSum - totalpph23;
   var titipansisa = nominal - totalKredit


    // Fungsi format angka dengan koma ribuan
    function formatRibuan(angka) {
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Update tampilan total kredit 1 (baris pertama)
    document.getElementById("total_kredit").innerText = formatRibuan(totalKredit.toFixed(2));
    document.getElementById("hidden_total_kredit").value = totalKredit.toFixed(2);

    // Update total kredit 2 (baris keempat), opsional jika mau tampil
    if (document.getElementById("total_kredit2")) {
        document.getElementById("total_kredit2").innerText = formatRibuan(totalKredit2.toFixed(2));
    }
    if (document.getElementById("hidden_total_kredit2")) {
        document.getElementById("hidden_total_kredit2").value = totalKredit2.toFixed(2);
    }

    // Update total sum dan total sisa
    document.getElementById("totalSum").value = totalSum.toFixed(2);
    document.getElementById("totalSisa").value = totalSisa.toFixed(2);
     document.getElementById("totalpph23").value = totalpph23.toFixed(2);
      document.getElementById("titipansisa").value = titipansisa.toFixed(2);
}




        

     function updateAccountDetails(select) {
    let selectedOption = select.options[select.selectedIndex];
    let accountCode = selectedOption.value;
    let accountName = selectedOption.getAttribute("data-name");

    let td = select.closest("td");
    if (td) {
        let nextTd = td.nextElementSibling;
        if (nextTd) {
            let inputName = nextTd.querySelector("input[name='account_name[]']");
            if (inputName) {
                inputName.value = accountName;
            }
        }
    }
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
                <td>4</td>
                <td>
                    <input type="text" name="coa[]" value="21203" required onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>

                    </select>
                </td>
                <td>
               <input type="text" name="account_name[]" value="Hutang PPH Pasal 23" readonly>

                </td>

  <td><input type="hidden" name="debet[]" min="0" value="0" readonly></td>

  <td>
 <input 
    type="text" 
    name="kredit[]" 
    min="" 
    value="" 
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
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Ambil elemen input
        const bayarElement = <?php echo json_encode($data['bayar']); ?>;
        const kreditInput = document.getElementById('hidden_total_kredit');
        const totalSumInput = document.getElementById('total_sum');

        // Konversi nilai ke angka dan jumlahkan
        const bayarValue = parseFloat(bayarElement || 0);
        const kreditValue = parseFloat(kreditInput.value || 0);
        const totalSum = bayarValue + kreditValue;

        // Masukkan hasil ke kolom total_sum
        totalSumInput.value = totalSum.toFixed(2);
    });
</script>
</body>
</html>
