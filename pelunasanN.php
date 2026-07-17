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
if (!isset($_SESSION['userid'])) {
    // Jika belum login, redirect ke halaman login
    header("Location: index.html");
    exit();
}

// Periksa apakah session location adalah 'HO' atau 'HO1'
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    // Jika lokasi bukan 'HO' atau 'HO1', redirect ke halaman login
    header("Location: index.html");
    exit();
}
require_once 'config1.php';

// Konfigurasi koneksi database
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("Kesalahan: DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
  die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil username dari session
$userbayar_input = $_SESSION['username'];



// Ambil parameter 'J' dari URL
if (isset($_GET['J'])) {
    $j_value = $_GET['J'];

    // Query untuk mengambil data transaksi berdasarkan 'J'
    $sql = "SELECT tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, bank, bayar, sisa 
            FROM penjualanHO1 WHERE J = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $j_value);
    $stmt->execute();
    $stmt->bind_result($tanggal_transaksi, $j_value, $cust, $diskon, $harga, $ppn, $jumlah, $bank, $bayar, $sisa);
    $stmt->fetch();
    $stmt->close();
} else {
    echo "Kode transaksi tidak ditemukan.";
    exit();
}

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $bayar_input = $_POST['bayar'] + $bayar;
     $bayar1 = $_POST['bayar'];
    $bank_input = $_POST['bank'];
    $userbayar_input = $_POST['userbayar']; // Ambil userbayar dari input hidden
     $tanggal= $_POST['tanggal'] ?? '';
      $j_value = $_POST['j_value'] ?? '';
      $cust = $_POST['cust'] ?? '';
         $bank = $_POST['bank'] ?? '';
            $uang = $_POST['uang'] ?? '';
 $kembalian = $_POST['kembalian'] ?? '';
 
    // Validasi input
    if ($bayar1 <= 0 || $bayar1 > $sisa) {
        echo "Nilai bayar tidak valid.";
        exit();
    }

    if (empty($bank_input)) {
        echo "Pilih Bank/Cash.";
        exit();
    }

    
// Update data transaksi di database (hanya bayar dan bank yang diupdate, sisa otomatis dihitung)
    $update_sql = "UPDATE penjualanHO1
                   SET bayar = ?, bank = ?, userbayar = ? , uang = ? , kembalian = ? 
                   WHERE J = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("dssdds", $bayar_input, $bank_input, $userbayar_input, $uang, $kembalian, $j_value);
    $conn->query("SET @disable_trigger = 1");
    $update_stmt->execute();

    if ($update_stmt->affected_rows > 0) {
        // Insert ke tabel bayar
        $jenis = 'pelunasan_piutang';
    $insert_sql = "INSERT INTO kas (tanggal, no, keterangan, debet, bank, username, jenis) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param(
        "sssssss",
        $tanggal,
        $j_value,
        $cust,
        $bayar1,
        $bank_input,
        $userbayar_input,
       $jenis
        );
        if ($insert_stmt->execute()) {
            $conn->query("SET @disable_trigger = NULL");
            
            // ==========================================
            // SINKRONISASI BFBS (Update penjualanHO1 & Kas)
            // ==========================================
            try {
                $db_host = getenv('DB_HOST') ?: 'localhost';
                $db_user = getenv('DB_USER') ?: 'root';
                $db_pass = getenv('DB_PASS') ?: '';
                $db_bfbs = getenv('DB_BFBS_NAME') ?: 'symotec2_bfbs';
                
                $conn_bfbs = new mysqli($db_host, $db_user, $db_pass, $db_bfbs);
                $conn_bfbs->begin_transaction();
                
                // Cek apakah invoice ini ada di BFBS dan berapa sisanya
                $res_bfbs = $conn_bfbs->query("SELECT jumlah, sisa, bayar FROM penjualanHO1 WHERE J = '$j_value'");
                if ($row_bfbs = $res_bfbs->fetch_assoc()) {
                    $bfbs_sisa = (float)$row_bfbs['sisa'];
                    $bfbs_bayar_lama = (float)$row_bfbs['bayar'];
                    
                    $persentase_bayar = 0;
                    if ($sisa > 0) {
                        $persentase_bayar = $bayar1 / $sisa;
                    }
                    
                    $bfbs_bayar_sekarang = $bfbs_sisa * $persentase_bayar;
                    $bfbs_bayar_baru = $bfbs_bayar_lama + $bfbs_bayar_sekarang;
                    
                    // Update penjualanHO1 BFBS
                    $stmt_upd_bfbs = $conn_bfbs->prepare("UPDATE penjualanHO1 SET bayar = ?, bank = ?, userbayar = ?, uang = ?, kembalian = ? WHERE J = ?");
                    $stmt_upd_bfbs->bind_param("dssdds", $bfbs_bayar_baru, $bank_input, $userbayar_input, $uang, $kembalian, $j_value);
                    $stmt_upd_bfbs->execute();
                    $stmt_upd_bfbs->close();
                    
                    // KAS BFBS
                    if ($bfbs_bayar_sekarang > 0) {
                        $insert_sql_bfbs = "INSERT INTO kas (tanggal, no, keterangan, debet, bank, username, jenis) 
                                            VALUES ('$tanggal', '$j_value', '$cust', $bfbs_bayar_sekarang, '$bank_input', '$userbayar_input', 'pelunasan_piutang')";
                        $conn_bfbs->query($insert_sql_bfbs);
                    }
                }
                $conn_bfbs->commit();
                $conn_bfbs->close();
            } catch (Exception $ex) {
                if (isset($conn_bfbs)) $conn_bfbs->close();
            }

            echo "Pelunasan berhasil disimpan!";
            header("Location: reports.php");
            exit();
        } else {
            echo "Gagal menyimpan pelunasan: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    } else {
        echo "Gagal memperbarui pelunasan.";
    }
    $update_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelunasan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #4CAF50;
        }
        form {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        label {
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
        }
        input[type="text"], input[type="number"], input[type="date"], select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h1>Pelunasan Transaksi HO1</h1>
    <form method="POST" action="">
           <input type="text" name="userbayar" value="<?php echo htmlspecialchars($userbayar_input); ?>">
        <label for="tanggal">Tanggal Pembayaran:</label>
        <input type="date" id="tanggal" name="tanggal" required>

        <label for="j_value">No. Transaksi:</label>
        <input type="text" id="j_value" name="j_value" value="<?php echo htmlspecialchars($j_value); ?>" readonly>

        <label for="cust">Kode Customer:</label>
        <input type="text" id="cust" name="cust" value="<?php echo htmlspecialchars($cust); ?>" readonly>

        <label for="jumlah">Sisa:</label>
        <input type="text" id="jumlah" value="<?php echo number_format($sisa, 2); ?>" readonly>

  <label for="bayar">nota:</label>
<input type="number" id="bayar" name="bayar" step="0.01" 
       value="<?php echo $sisa; ?>" required>

        <label for="bank">Bank/Cash:</label>
        <select id="bank" name="bank" required>
            <option value="" disabled selected hidden>Pilih Bank/Cash</option>
            <?php
        $conn = new mysqli($servername, $db_username, $db_password, $database);

$sql = "SELECT id, bank FROM bank WHERE cabang = 'HO1'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<option value='" . $row['bank'] . "'>" . htmlspecialchars($row['bank']) . "</option>";
    }
} else {
    echo "<option value=''>Tidak ada data bank tersedia</option>";
}

$conn->close();
            ?>
        </select>
          <label for="uang">Uang Pembayaran:</label>
<input type="number" id="uang" name="uang" step="0.01" 
          <label for="kembalian">Kembalian:</label>
  <input type="number" id="kembalian" name="kembalian" step="0.01" readonly>

        

      <button type="submit">Update Pelunasan</button>

    </form>
    <script>
        const inputTanggal = document.getElementById('tanggal');
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        inputTanggal.value = `${yyyy}-${mm}-${dd}`;
    </script>
    <script>
document.getElementById('uang').addEventListener('input', function() {
    let bayar = parseFloat(document.getElementById('bayar').value) || 0;
    let uang  = parseFloat(this.value) || 0;
    let kembalian = uang - bayar;

    document.getElementById('kembalian').value = kembalian.toFixed(2);
});
</script>

</body>
</html>
