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

// Menentukan apakah user adalah sales
$is_sales = false;
if (isset($_SESSION['location']) && $_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    $is_sales = true;
}
require_once 'config1.php';

// Konfigurasi koneksi database
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS');
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
    $sql = "SELECT tanggal_transaksi, J, cust, diskon, harga, ppn, jumlah, bank, bayar, sisa, userinv 
            FROM penjualanho1 WHERE J = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $j_value);
    $stmt->execute();
    $stmt->bind_result($tanggal_transaksi, $j_value, $cust, $diskon, $harga, $ppn, $jumlah, $bank, $bayar, $sisa, $userinv);
    $stmt->fetch();
    $stmt->close();
    
    // Verifikasi kepemilikan untuk sales
    if ($is_sales && $userinv !== $_SESSION['username']) {
        echo "<script>alert('Akses Ditolak: Anda hanya dapat memproses pelunasan untuk penjualan Anda sendiri.'); window.location.href='ap_sales.php';</script>";
        exit();
    }
} else {
    echo "Kode transaksi tidak ditemukan.";
    exit();
}

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $bayar1      = (float)($_POST['bayar'] ?? 0);
    $bayar_input = $bayar1 + $bayar;

    $bank_input     = $_POST['bank'] ?? '';
    $userbayar_input = $_POST['userbayar'] ?? '';

    // 🔹 LOGIKA YANG DIMINTA
    if (strtoupper($bank_input) === 'CASH') {
        $bank_input = $bank_input . ' ' . $userbayar_input;
        // hasil: "CASH Andi" / "CASH kasir1" dll
    }

    $tanggal   = $_POST['tanggal']   ?? '';
    $j_value   = $_POST['j_value']   ?? '';
    $cust      = $_POST['cust']      ?? '';
    $uang      = $_POST['uang']      ?? '';
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
    $conn->query("SET @disable_trigger = 1");
    $update_sql = "UPDATE penjualanho1
                   SET bayar = ?, bank = ?, userbayar = ? , uang = ? , kembalian = ? 
                   WHERE J = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("dssdds", $bayar_input, $bank_input, $userbayar_input, $uang, $kembalian, $j_value);
    $update_stmt->execute();

    if ($update_stmt->affected_rows > 0 || $update_stmt->errno === 0) {
        $update_stmt->close();
        
        // ===================================
        // JURNAL B: PELUNASAN KAS SALES
        // ===================================
        $coa_kas_sales = $bank_input; // Gunakan COA dari input form secara langsung

        $ket_jurnal = "Pelunasan POS " . $j_value . " oleh " . $userbayar_input;
        $nomor_lunas = "LUNAS-" . $j_value . "-" . time(); // avoid duplicate if partial

        $stmt_jurnal = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit, kode_booking) VALUES (?, ?, ?, ?, ?, ?, ?)");

        // 1. Debet: Kas Sales
        $d = $bayar1; $k = 0;
        $coa = $coa_kas_sales;
        $stmt_jurnal->bind_param("ssssdds", $nomor_lunas, $tanggal, $ket_jurnal, $coa, $d, $k, $j_value);
        $stmt_jurnal->execute();

        // 2. Kredit: 11201 Piutang
        $d = 0; $k = $bayar1;
        $coa = '11201';
        $stmt_jurnal->bind_param("ssssdds", $nomor_lunas, $tanggal, $ket_jurnal, $coa, $d, $k, $j_value);
        $stmt_jurnal->execute();

        $stmt_jurnal->close();
        
        $conn->query("SET @disable_trigger = NULL");
        
        // ==========================================
        // SINKRONISASI BFBS (Update penjualanho1 & Jurnal)
        // ==========================================
        try {
            $db_host = getenv('DB_HOST') ?: 'localhost';
            $db_user = getenv('DB_USER') ?: 'root';
            $db_pass = getenv('DB_PASS') ?: '';
            $db_bfbs = getenv('DB_BFBS_NAME') ?: 'symotec2_bfbs';
            
            $conn_bfbs = new mysqli($db_host, $db_user, $db_pass, $db_bfbs);
            $conn_bfbs->begin_transaction();
            
            // Cek apakah invoice ini ada di BFBS dan berapa sisanya
            $res_bfbs = $conn_bfbs->query("SELECT jumlah, sisa, bayar FROM penjualanho1 WHERE J = '$j_value'");
            if ($row_bfbs = $res_bfbs->fetch_assoc()) {
                $bfbs_sisa = (float)$row_bfbs['sisa'];
                $bfbs_bayar_lama = (float)$row_bfbs['bayar'];
                
                // Hitung persentase pembayaran di BFB, terapkan persentase yang sama di BFBS
                // Jika lunas 100% di BFB, lunas 100% juga di BFBS
                $persentase_bayar = 0;
                if ($sisa > 0) {
                    $persentase_bayar = $bayar1 / $sisa;
                }
                
                $bfbs_bayar_sekarang = $bfbs_sisa * $persentase_bayar;
                $bfbs_bayar_baru = $bfbs_bayar_lama + $bfbs_bayar_sekarang;
                
                // Update penjualanho1 BFBS (tanpa update sisa karena di DB mungkin dihitung trigger atau via view)
                $stmt_upd_bfbs = $conn_bfbs->prepare("UPDATE penjualanho1 SET bayar = ?, bank = ?, userbayar = ?, uang = ?, kembalian = ? WHERE J = ?");
                $stmt_upd_bfbs->bind_param("dssdds", $bfbs_bayar_baru, $bank_input, $userbayar_input, $uang, $kembalian, $j_value);
                $stmt_upd_bfbs->execute();
                $stmt_upd_bfbs->close();
                
                // JURNAL BFBS
                if ($bfbs_bayar_sekarang > 0) {
                    $stmt_jurnal_bfbs = $conn_bfbs->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit, kode_booking) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    // 1. Debet Kas Sales
                    $d = $bfbs_bayar_sekarang; $k = 0; $coa = $coa_kas_sales;
                    $stmt_jurnal_bfbs->bind_param("ssssdds", $nomor_lunas, $tanggal, $ket_jurnal, $coa, $d, $k, $j_value);
                    $stmt_jurnal_bfbs->execute();
                    
                    // 2. Kredit Piutang
                    $d = 0; $k = $bfbs_bayar_sekarang; $coa = '11201';
                    $stmt_jurnal_bfbs->bind_param("ssssdds", $nomor_lunas, $tanggal, $ket_jurnal, $coa, $d, $k, $j_value);
                    $stmt_jurnal_bfbs->execute();
                    
                    $stmt_jurnal_bfbs->close();
                }
            }
            $conn_bfbs->commit();
            $conn_bfbs->close();
        } catch (Exception $ex) {
            if (isset($conn_bfbs)) $conn_bfbs->close();
        }

        // ✅ Munculkan alert & redirect ke nota.php
        echo "<script>
            alert('Data pelunasan berhasil disimpan dengan kode transaksi: {$j_value}');
            window.location.href = 'nota.php?J={$j_value}';
        </script>";
        exit();
    } else {
        echo "Gagal memperbarui pelunasan: " . $update_stmt->error;
        $update_stmt->close();
    }
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
            color: RED;
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
    <form method="POST" action="">
        <label for="tanggal">Tanggal Pembayaran:</label>
        <input type="date" id="tanggal" name="tanggal" value="<?php echo date('Y-m-d'); ?>" required>

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
                <option value="" disabled selected hidden>Pilih Bank/Kas COA</option>
                <?php
                $conn_coa = new mysqli($servername, $db_username, $db_password, $database);
                $sql = "SELECT account_code as coa, account_name FROM coa WHERE parent_account = '111'";
                $result = $conn_coa->query($sql);

                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<option value='" . $row['coa'] . "'>" . htmlspecialchars($row['coa'] . " - " . $row['account_name']) . "</option>";
                    }
                } else {
                    echo "<option value='' disabled>Data Kas/Bank (111) kosong di tabel COA</option>";
                }
                $conn_coa->close();
                ?>
            </select>
   <input type="hidden" name="userbayar" value="<?php echo htmlspecialchars($userbayar_input); ?>">
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
