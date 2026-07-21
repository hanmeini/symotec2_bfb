<?php
// sjbeli_detail.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'use_only_cookies'=> true,
    'use_strict_mode' => true,
]);

// Pastikan login
if (!isset($_SESSION['userid'])) {
    header("Location: index.html");
    exit();
}

// Hanya HO & HO1
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    header("Location: index.html");
    exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

require_once 'config1.php';
require_once 'functions_stock.php';

// Koneksi DB
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Ambil parameter J
if (!isset($_GET['sj'])) {
    die("sj tidak ditemukan.");
}
$j_value = $_GET['sj'];

// ===== PROSES SIMPAN =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        die("Token CSRF tidak valid.");
    }

    $total_harga  = 0;
    $total_ppn    = 0;
    $total_hargat = 0;


    $tanggal_transaksi_pertama = date('Y-m-d');
    
    if (!empty($_POST['id_transaksi']) && is_array($_POST['id_transaksi'])) {
        foreach ($_POST['id_transaksi'] as $idx => $id_transaksi) {
            $harga_m = floatval(str_replace(',', '.', $_POST['harga_m'][$idx]));
            $ppn_m   = floatval(str_replace(',', '.', $_POST['ppn_m'][$idx]));
            $jumlah_m   = floatval(str_replace(',', '.', $_POST['jumlah_m'][$idx]));
            $hargat  = ($harga_m * $jumlah_m) + $ppn_m;
            if ($jumlah_m > 0) {
                $beliterakhir = $hargat / $jumlah_m;
            }



            // Akumulasi total
            $total_harga  += ($harga_m * $jumlah_m);
            $total_ppn    += $ppn_m;
            $total_hargat += $hargat;

            // update harga_m, ppn_m, hargat_m di transaksiho1
            $sqlUpd = "UPDATE transaksiho1 SET jumlah_m=?, harga_m=?, ppn_m=?, hargat_m=?  WHERE id_transaksi=?";
            $stmtUpd = $conn->prepare($sqlUpd);
            $stmtUpd->bind_param("ddddi", $jumlah_m, $harga_m, $ppn_m, $hargat, $id_transaksi);
            $stmtUpd->execute();
            $stmtUpd->close();

            // ================== Perhitungan DPP ==================
            $sqlInfo = "SELECT kode_b, tanggal_transaksi, jumlah_m FROM transaksiho1 WHERE id_transaksi=?";
            $stmtInfo = $conn->prepare($sqlInfo);
            $stmtInfo->bind_param("i", $id_transaksi);
            $stmtInfo->execute();
            $stmtInfo->bind_result($kode_b, $tgl_transaksi, $jumlah_m_db);
            if (!$stmtInfo->fetch()) {
                $stmtInfo->close();
                continue;
            }
            $stmtInfo->close();
            
            if ($idx === 0) {
                $tanggal_transaksi_pertama = $tgl_transaksi;
            }

            // Update stock table
            $sqlUpdStk = "UPDATE stock SET harga_m=?, ppn_m=?, hargat_m=? WHERE sj=? AND kodeb=?";
            $stmtUpdStk = $conn->prepare($sqlUpdStk);
            $stmtUpdStk->bind_param("dddss", $harga_m, $ppn_m, $hargat, $j_value, $kode_b);
            $stmtUpdStk->execute();
            $stmtUpdStk->close();

            // Update harga terakhir di master barang
            $sqlUpdB = "UPDATE b SET harga_m=?, ppn_m=?, hargat_m=? WHERE kode_b=?";
            $stmtB = $conn->prepare($sqlUpdB);
            $stmtB->bind_param("ddds", $harga_m, $ppn_m, $hargat, $kode_b);
            $stmtB->execute();
            $stmtB->close();
            
            // Recalculate stock history for this item
            recalculate_stock_history($conn, $kode_b);
        }

        $inv_post = $_POST['inv'] ?? '';
        $bayar_post = floatval(str_replace(',', '.', $_POST['bayar'] ?? '0'));
        $sisa = $total_hargat - $bayar_post;

        // ============= UPDATE KE PEMBELIANHO1 BERDASARKAN J =============
        $sqlUpdPemb = "UPDATE pembelianho1 SET harga_m=?, ppn_m=?, hargat_m=?, sisa=?, inv=?, bayar=?, coa='21101' WHERE sj=?";
        $stmtPemb = $conn->prepare($sqlUpdPemb);
        $stmtPemb->bind_param("ddddsss", $total_harga, $total_ppn, $total_hargat, $sisa, $inv_post, $bayar_post, $j_value);
        $stmtPemb->execute();
        $stmtPemb->close();
        
        // UPDATE inv in stock for the same SJ
        $sqlUpdStock = "UPDATE stock SET inv=? WHERE sj=?";
        $stmtStockInv = $conn->prepare($sqlUpdStock);
        $stmtStockInv->bind_param("ss", $inv_post, $j_value);
        $stmtStockInv->execute();
        $stmtStockInv->close();
        
        // ============= INSERT 3 COA JURNAL =============
        // Hapus jurnal lama dengan sj ini jika ada (agar tidak dobel jika diedit)
        $delJ = $conn->prepare("DELETE FROM jurnal WHERE journal_number=?");
        $delJ->bind_param("s", $j_value);
        $delJ->execute();
        $delJ->close();

        $coa1 = '11301'; // Debet Total Harga
        $coa2 = '12102'; // Debet Total PPN
        $coa3 = '21101'; // Kredit Total Hargat
        
        $nol = 0.0;
        
        $insJ = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, 'Pembelian', ?, ?, ?)");
        
        // 1. Debet Total Harga
        $insJ->bind_param("sssdd", $j_value, $tanggal_transaksi_pertama, $coa1, $total_harga, $nol);
        $insJ->execute();
        
        // 2. Debet Total PPN
        $insJ->bind_param("sssdd", $j_value, $tanggal_transaksi_pertama, $coa2, $total_ppn, $nol);
        $insJ->execute();
        
        // 3. Kredit Total Hargat
        $insJ->bind_param("sssdd", $j_value, $tanggal_transaksi_pertama, $coa3, $nol, $total_hargat);
        $insJ->execute();
        
        $insJ->close();
    }

    echo "<script>alert('Data berhasil disimpan dan DPP diperbarui.'); window.location.href='sjbeli_detail.php?sj=" . urlencode($j_value) . "';</script>";
    exit();
}

// ===== AMBIL DATA UNTUK FORM (tanpa get_result) =====
$sql = "SELECT id_transaksi, tanggal_transaksi, kode_b, nama_b, jumlah_m, harga_m, ppn_m, hargat_m, format_qty
        FROM transaksiho1 WHERE sj=? AND jumlah_m>0 ";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $j_value);
$stmt->execute();
$stmt->bind_result($id_transaksi, $tanggal_transaksi, $kode_b, $nama_b, $jumlah_m, $harga_m, $ppn_m, $hargat_m, $format_qty);

$rows = [];
while ($stmt->fetch()) {
    $rows[] = [
        'id_transaksi'      => $id_transaksi,
        'tanggal_transaksi' => $tanggal_transaksi,
        'kode_b'            => $kode_b,
        'nama_b'            => $nama_b,
        'jumlah_m'          => $jumlah_m,
        'harga_m'           => $harga_m,
        'ppn_m'             => $ppn_m,
        'hargat_m'          => $hargat_m,
        'format_qty'        => $format_qty,
    ];
}
$stmt->close();

// ===== AMBIL DATA INVOICE DAN BAYAR DARI PEMBELIANHO1 =====
$sqlPembGet = "SELECT inv, bayar FROM pembelianho1 WHERE sj=?";
$stmtPembGet = $conn->prepare($sqlPembGet);
$stmtPembGet->bind_param("s", $j_value);
$stmtPembGet->execute();
$stmtPembGet->bind_result($inv_val, $bayar_val);
if (!$stmtPembGet->fetch()) {
    $inv_val = '';
    $bayar_val = 0;
}
$stmtPembGet->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Detail SJ Beli <?php echo htmlspecialchars($j_value); ?></title>
<style>
body { font-family: Arial, sans-serif; margin:20px; background:#f9f9f9; }
h1 { color:#2c6; }
table { border-collapse: collapse; width:100%; background:#fff; }
th, td { border:1px solid #ccc; padding:8px; text-align:left; }
th { background:#2c6; color:#fff; }
input[type=number] { width:100px; }
.btn { padding:6px 12px; border:0; border-radius:4px; background:#28a745; color:#fff; cursor:pointer; }
</style>
</head>
<body>
<div style="margin-bottom: 15px;">
    <a href="sjbeli.php" class="btn" style="text-decoration:none; background:#6c757d;">← Kembali (SJ Beli)</a>
    <a href="home.php" class="btn" style="text-decoration:none; background:#007bff; margin-left:5px;">🏠 Home</a>
</div>
<div style="display: flex; justify-content: space-between; align-items: center;">
    <h1>Detail SJ Beli - J: <?php echo htmlspecialchars($j_value); ?></h1>
    <div style="font-weight: bold; font-size: 18px;">
        <label>Nomor Invoice: </label>
        <input type="text" form="mainForm" name="inv" value="<?php echo htmlspecialchars($inv_val ?? ''); ?>" style="padding:5px; font-size:16px;">
    </div>
</div>
<form method="post" id="mainForm">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
<table>
<tr>
  <th>No</th>
  <th>Tanggal</th>
  <th>Kode Barang</th>
  <th>Nama Barang</th>
  <th>Jumlah Masuk</th>
  <th>Harga</th>
  <th>PPN</th>
  <th>Total</th>
</tr>
<?php 
$no=1;
foreach ($rows as $row): ?>
<tr>
  <td><?php echo $no++; ?>
      <input type="hidden" name="id_transaksi[]" value="<?php echo $row['id_transaksi']; ?>">
  </td>
  <td><?php echo htmlspecialchars($row['tanggal_transaksi']); ?></td>
  <td><?php echo htmlspecialchars($row['kode_b']); ?></td>
  <td><?php echo htmlspecialchars($row['nama_b']); ?></td>
  <td>
      <input type="text" class="qty" name="jumlah_m[]" value="<?php echo htmlspecialchars(str_replace(',', '.', $row['jumlah_m'] ?? '0')); ?>" oninput="calc()" style="width:70px;">
      <br><small style="color:gray;"><?php echo htmlspecialchars($row['format_qty'] ?? ''); ?></small>
  </td>
  <td><input type="text" class="harga" name="harga_m[]" value="<?php echo htmlspecialchars(str_replace(',', '.', $row['harga_m'] ?? '0')); ?>" oninput="calc()" style="width:120px;"></td>
  <td><input type="text" class="ppn" name="ppn_m[]" value="<?php echo htmlspecialchars(str_replace(',', '.', $row['ppn_m'] ?? '0')); ?>" oninput="calc()" style="width:100px;"></td>
  <td class="row-total"><?php echo htmlspecialchars($row['hargat_m']); ?></td>
</tr>
<?php endforeach; ?>
<tr>
  <td colspan="7" style="text-align:right; font-weight:bold;">Grand Total:</td>
  <td style="font-weight:bold;"><span id="grandTotalText">0.00</span></td>
</tr>
</table>
<br>
<button type="submit" class="btn">Simpan</button>
</form>

<script>
function parseLocalFloat(val) {
    if (!val) return 0;
    // Ganti koma dengan titik untuk parsing
    return parseFloat(val.toString().replace(/,/g, '.')) || 0;
}

function calc() {
    let grandTotal = 0;
    const trs = document.querySelectorAll('table tr');
    for (let i = 1; i < trs.length; i++) {
        const qtyInp = trs[i].querySelector('.qty');
        const hrgInp = trs[i].querySelector('.harga');
        const ppnInp = trs[i].querySelector('.ppn');
        const totCell = trs[i].querySelector('.row-total');
        
        if (qtyInp && hrgInp && ppnInp && totCell) {
            const qty = parseLocalFloat(qtyInp.value);
            const hrg = parseLocalFloat(hrgInp.value);
            const ppn = parseLocalFloat(ppnInp.value);
            const tot = (hrg * qty) + ppn;
            totCell.innerText = tot.toFixed(2);
            grandTotal += tot;
        }
    }
    document.getElementById('grandTotalText').innerText = grandTotal.toFixed(2);
    // document.getElementById('bayarInput').value = grandTotal.toFixed(2); // Opsional: Auto-fill pembayaran
}

// Initial calc
window.onload = function() {
    calc();
};
</script>
</body>
</html>
<?php
$conn->close();
?>
