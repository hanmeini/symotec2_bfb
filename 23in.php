<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://jascoacc.symotech.id/";

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










// Buat koneksi ke database pertama


// Periksa koneksi pertama


// Buat koneksi ke database kedua


// Periksa koneksi kedua


// Ambil nilai 'J' dari URL
$id = isset($_GET['J']) ? $_GET['J'] : null;


// Cek jika metode pengiriman adalah POST

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil input dari form
    $nomor_faktur_pajak = isset($_POST['nobukpot']) ? trim($_POST['nobukpot']) : null;
    $nomor = isset($_POST['nomor']) ? trim($_POST['nomor']) : null;

    // Ambil array input
    $coa = $_POST['coa'] ?? [];
    $account_name = $_POST['account_name'] ?? [];
    $debet = $_POST['debet'] ?? [];
    $kredit = $_POST['kredit'] ?? [];
    $keterangan = $_POST['keterangan'] ?? [];
    $kode_booking = $_POST['kode_booking'] ?? [];
    $location = $_POST['location'] ?? [];
    $devisi = $_POST['devisi'] ?? [];
    $cust_id = $_POST['supcust'] ?? [];

    $tanggal = date('Y-m-d'); // Tanggal hari ini

    // Validasi input utama
    if ($nomor_faktur_pajak && $nomor) {
        // Update bukpot di tabel pph23
        $update_sql = "UPDATE pph23 SET bukpot = ? WHERE inv = ?";
        $update_stmt = $conn->prepare($update_sql);

        if ($update_stmt) {
            $update_stmt->bind_param("ss", $nomor_faktur_pajak, $nomor);
            if (!$update_stmt->execute()) {
                $error_message = "Gagal memperbarui data: " . htmlspecialchars($update_stmt->error);
            }
            $update_stmt->close();
        } else {
            $error_message = "Gagal menyiapkan query: " . htmlspecialchars($conn->error);
        }

        // Insert ke tabel jurnal
        $insert_sql = "INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);

        if ($stmt) {
            for ($i = 0; $i < count($coa); $i++) {
                $coa_val = $coa[$i] ?? '';
                $account_name_val = $account_name[$i] ?? '';
                $debet_val = str_replace(',', '', $debet[$i] ?? 0);
                $kredit_val = str_replace(',', '', $kredit[$i] ?? 0);
                $keterangan_val = $keterangan[$i] ?? '';
                $kode_booking_val = is_array($kode_booking) ? ($kode_booking[$i] ?? '') : $kode_booking;
                $location_val = is_array($location) ? ($location[$i] ?? '') : $location;
                $devisi_val = is_array($devisi) ? ($devisi[$i] ?? '') : $devisi;
                $cust_id_val = is_array($cust_id) ? ($cust_id[$i] ?? '') : $cust_id;

                $stmt->bind_param(
                    "sssssssssss",
                    $nomor_faktur_pajak,
                    $tanggal,
                    $keterangan_val,
                    $coa_val,
                    $account_name_val,
                    $debet_val,
                    $kredit_val,
                    $kode_booking_val,
                    $location_val,
                    $devisi_val,
                    $cust_id_val
                );
                $stmt->execute();
            }
            $stmt->close();
            $success_message = "Data berhasil disimpan ke jurnal.";
        } else {
            $error_message = "Gagal menyiapkan query jurnal: " . htmlspecialchars($conn->error);
        }
    } else {
        $error_message = "Harap isi nomor Bukti Potong dengan benar.";
    }
}




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

// Query ke tabel `pph23`
$sql = "SELECT id, tanggal, inv, kodebooking, cust_id, bukpot, pph23, tagihan, fp , location, devisi
        FROM pph23 
        WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->bind_result($id, $tanggal, $inv, $kodebooking, $cust_id, $bukpot, $pph23, $tagihan, $fp, $location, $devisi);

$total_pph23 = 0;
$total_tagihan = 0;

// Proses hasil query
$pph23_data = [];
if ($stmt->store_result() && $stmt->num_rows > 0) {
    while ($stmt->fetch()) {
        $pph23_data[] = [
            'id' => $id,
            'tanggal' => $tanggal,
            'inv' => $inv,
            'kodebooking' => $kodebooking,
            'cust_id' => $cust_id,
            'bukpot' => $bukpot,
            'pph23' => $pph23,
            'tagihan' => $tagihan,
            'fp' => $fp,
            'location' => $location,
            'devisi' => $devisi,
        ];

        $total_pph23 += $pph23;
        $total_tagihan += $tagihan;
    }
}
$stmt->close();




$fp_sql = "SELECT fp FROM fp WHERE inv IS NULL ORDER BY id ASC LIMIT 1";
$fp_stmt = $conn->prepare($fp_sql);
if ($fp_stmt) {
    $fp_stmt->execute();
    $fp_stmt->bind_result($auto_fp);
    if (!$fp_stmt->fetch()) {
        $auto_fp = ''; // Jika tidak ada hasil, kosongkan nilai
    }
    $fp_stmt->close();
} else {
    $auto_fp = ''; // Jika query gagal, kosongkan nilai
}
?>



<?php
  // Ambil data faktur pajak
$stmt3 = $conn->prepare("SELECT fp FROM fp WHERE inv = ?");
$empty_value = '';
$stmt3->bind_param('s', $empty_value);
$stmt3->execute();

// Bind hasil ke variabel
$stmt3->bind_result($fp);

// Loop hasil menggunakan bind_result
$faktur_pajak_options = '';
while ($stmt3->fetch()) {
    $faktur_pajak_options .= '<option value="' . htmlspecialchars($fp ?? '') . '">' . htmlspecialchars($fp ?? '') . '</option>';
}

// Jika tidak ada hasil
if (empty($faktur_pajak_options)) {
    $faktur_pajak_options = '<option value="">Tidak ada data tersedia</option>';
}

// Tutup statement dan koneksi
$stmt3->close();
$conn->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Bukpot PPh 23 www.symotech.id </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }

        .table-container {
            margin: 50px auto;
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
            margin: 20px 0;
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
            margin-top: 20px;
        }

        label {
            display: block;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .button {
            padding: 10px 20px;
            background-color: #28a745;
            color: #fff;
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
      
 .form-container select,
    width: 500px;
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
    <h1>BUKPOT PPH 23 BELUM DITERIMA</h1>
        <p>Nomor INV: <?php echo htmlspecialchars($inv ?? ''); ?></p>
    <p>Nomor Kode Booking: <?php echo htmlspecialchars($kodebooking ?? ''); ?></p>

    <p>Tanggal: <?php echo htmlspecialchars($tanggal ?? ''); ?></p>

    <table>
        <tr>
            <th>ID</th>
            <th>Tanggal</th>
            <th>Invoice</th>
            <th>Kode Booking</th>
            <th>Customer ID</th>
            <th>Bukti Potong</th>
            <th>PPH 23</th>
            <th>Tagihan</th>
            <th>Faktur Pajak</th>
        </tr>
        <?php foreach ($pph23_data as $data): ?>
            <tr>
                <td><?php echo htmlspecialchars($data['id']); ?></td>
                <td><?php echo htmlspecialchars($data['tanggal']); ?></td>
                <td><?php echo htmlspecialchars($data['inv']); ?></td>
                <td><?php echo htmlspecialchars($data['kodebooking']); ?></td>
                <td><?php echo htmlspecialchars($data['cust_id']); ?></td>
                <td><?php echo htmlspecialchars($data['bukpot']); ?></td>
                <td><?php echo number_format($data['pph23'], 2); ?></td>
                <td><?php echo number_format($data['tagihan'], 2); ?></td>
                <td><?php echo htmlspecialchars($data['fp']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="total">
        <h3>Total PPH 23: <?php echo number_format($total_pph23, 2); ?></h3>
        <h3>Total Tagihan: <?php echo number_format($total_tagihan, 2); ?></h3>
    </div>

        <!-- Form input faktur pajak -->
        <form method="post" action="">
    <label for="nobukpot">Masukkan nomor Bukti Potong:</label>
    <input type="hidden" name="nomor" value="<?php echo htmlspecialchars($inv ?? ''); ?>">
    <input id="nobukpot" name="nobukpot" required
        style="width: 100%; padding: 10px; font-size: 14px; border: 1px solid #ccc; border-radius: 5px; background-color: #fff;">
    <br>
    <table id="jurnal_table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Account Code</th>
                        <th>Account Name</th>
                       
                        <th>Debet</th>
                  <th>Kredit</th>
                   <th>Keterangan</th>
                      
               
                    </tr>
                </thead>
                <tbody>
                   <td>1</td>
                        <td>
                        <input type="text" name="coa[]" value="13106" required  onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>
                        </td>
                        <td>
                            <input type="text" name="account_name[]" value="PPh pasal 23" readonly>
                        </td>
             <td><input type="number" name="debet[]" min="0" value="<?= isset($data['pph23']) ? max(0, $data['pph23']) : 0 ?>" readonly></td>
         


  <td>
 <input 
    type="text" 
    name="kredit[]" 
   min="0" value="0" readonly></td>
</td>
                  
                <td><input type="text" name="keterangan[]" value="terima bukpot untuk  <?= htmlspecialchars($data['inv'] ?? '') ?>" readonly></td>
             
                  <td>
        <input type="hidden" name="location[]" value="<?= htmlspecialchars($location ?? '') ?>" readonly>
    </td>
    <td>
        <input type="hidden" name="devisi[]" value="<?= htmlspecialchars($devisi ?? '') ?>" readonly>
    </td>
    <td>
        <input type="hidden" name="supcust[]" value="<?= htmlspecialchars($cust_id ?? '') ?>" readonly>
    </td>

                          </tr>
                    <td>2</td>
                        <td>
                    <input type="text" name="coa[]" value="12105" required onchange="getAccountName(this)" placeholder="Masukkan COA" readonly>

                    </select>
                </td>
                <td>
               <input type="text" name="account_name[]" value="Piutang PPH 23" readonly>

<td><input type="text" name="debet[]" min="0" value="0" readonly></td>
           <td><input type="number" name="kredit[]" min="0" value="<?= isset($data['pph23']) ? max(0, $data['pph23']) : 0 ?>" readonly></td>

                   <td><input type="text" name="keterangan[]" value="terima bukpot untuk <?= htmlspecialchars($data['inv'] ?? '') ?>" readonly></td>
                  <td>
        <input type="hidden" name="location[]" value="<?= htmlspecialchars($location ?? '') ?>" readonly>
    </td>
    <td>
        <input type="hidden" name="devisi[]" value="<?= htmlspecialchars($devisi ?? '') ?>" readonly>
    </td>
     <td>
        <input type="hidden" name="supcust[]" value="<?= htmlspecialchars($cust_id ?? '') ?>" readonly>
    </td>

                    </tr>
                   
            
                    </tr>
                </tfoot>
            </table>
           
            
    <button type="submit" class="button">Simpan Bukpot PPh 23</button>
</form>

        <?php if (isset($success_message)): ?>
            <p style="color: green;"><?php echo $success_message; ?></p>
        <?php elseif (isset($error_message)): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        
    <?php else: ?>
        <div class="no-data">
            <p>Tidak ada transaksi untuk kolom J: <?php echo htmlspecialchars($id ?? ''); ?></p>
        </div>
    <?php endif; ?>
</div>
</body>
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
</html>
