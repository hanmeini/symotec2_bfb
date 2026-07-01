<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;




$tanggal = date('Y-m-d');
$gagal = [];
$valid_data = [];
$berhasil = 0;
$kode_transaksi = '';
$all_valid = false;

// =================================================
// LANGKAH 1: Upload dan Validasi
// =================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file_tmp = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($file_tmp);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // skip header
            $inv = trim($row[0]);
            $bukpot = trim($row[1]);

            if (empty($inv) || empty($bukpot)) {
                $gagal[] = ['baris' => $index + 1, 'inv' => $inv, 'alasan' => 'INV atau Bukpot kosong'];
                continue;
            }

            $inv_escaped = $conn->real_escape_string($inv);
            $cek = $conn->query("SELECT * FROM pph23 WHERE inv = '$inv_escaped'");

            if ($cek->num_rows === 0) {
                $gagal[] = ['baris' => $index + 1, 'inv' => $inv, 'alasan' => 'INV tidak ditemukan'];
                continue;
            }

            $data = $cek->fetch_assoc();
            $cek->free_result();

            if (!empty($data['bukpot'])) {
                $gagal[] = ['baris' => $index + 1, 'inv' => $inv, 'alasan' => 'Sudah memiliki bukpot'];
                continue;
            }

            $valid_data[] = [
                'inv' => $inv,
                'bukpot' => $bukpot,
                'pph23' => floatval($data['pph23']),
                'kodebooking' => $data['kodebooking'],
                'cust_id' => $data['cust_id'],
                'location' => $data['location'],
                'devisi' => $data['devisi']
            ];
        }

        if (count($gagal) === 0 && count($valid_data) > 0) {
            $all_valid = true;
        }

    } catch (Exception $e) {
        die("Gagal membaca file Excel: " . $e->getMessage());
    }
}

// =================================================
// LANGKAH 2: Proses Data Valid
// =================================================
if (isset($_POST['proses_valid']) && isset($_POST['valid_json'])) {
    $valid_data = json_decode($_POST['valid_json'], true);
    $tahun = date('Y');

    // --- Buat nomor jurnal baru
    $query = "SELECT max(id) AS max_nomor FROM JM";
    $result = $conn->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $max_nomor = $row['max_nomor'];
        $nomor = $max_nomor ? intval($max_nomor) + 1 : 1;
        $nomor_formatted = sprintf('%04d', $nomor);
    } else {
        $nomor_formatted = '0001';
    }
    $kode_transaksi = "JM" . $tahun . $nomor_formatted;

    // Simpan ke tabel JM
    $sql1 = "INSERT INTO JM (jurnal) VALUES (?)";
    $stmtJM = $conn->prepare($sql1);
    $stmtJM->bind_param("s", $kode_transaksi);
    $stmtJM->execute();
    $stmtJM->close();

    $total_pph23 = 0;
    $insert_sql = "INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_sql);

    foreach ($valid_data as $v) {
        $inv = $conn->real_escape_string($v['inv']);
        $bukpot = $conn->real_escape_string($v['bukpot']);
        $pph23 = floatval($v['pph23']);
        $kodebooking = $conn->real_escape_string($v['kodebooking']);
        $cust_id = $conn->real_escape_string($v['cust_id']);
        $location = $conn->real_escape_string($v['location']);
        $devisi = $conn->real_escape_string($v['devisi']);
        $total_pph23 += $pph23;

        // Update pph23
        $conn->query("UPDATE pph23 SET bukpot = '$bukpot' WHERE inv = '$inv'");

        // --- Baris 1: Dr. PPh Pasal 23 (per INV)
        $journal_number = $kode_transaksi;
        $keterangan1 = "Terima bukpot untuk INV $inv";
        $coa1 = "13106";
        $account1 = "PPh pasal 23";
        $debet1 = $pph23;
        $kredit1 = 0;
        $stmt->bind_param(
            "sssssssssss",
            $journal_number, $tanggal, $keterangan1,
            $coa1, $account1,
            $debet1, $kredit1,
            $kodebooking, $location, $devisi, $cust_id
        );
        $stmt->execute();

        $berhasil++;
    }

    // --- Baris 2: Cr. Piutang PPh 23 (sekali total)
    $keterangan2 = "Total PPh 23 diterima dari bukpot";
    $coa2 = "12105";
    $account2 = "Piutang PPh 23";
    $debet2 = 0;
    $kredit2 = $total_pph23;
    $dummy_kodebooking = $valid_data[0]['kodebooking'];
    $dummy_location =NULL;
    $dummy_devisi = NULL;
$dummy_custid = NULL;

    $stmt->bind_param(
        "sssssssssss",
        $kode_transaksi, $tanggal, $keterangan2,
        $coa2, $account2,
        $debet2, $kredit2,
        $dummy_kodebooking, $dummy_location, $dummy_devisi, $dummy_custid
    );
    $stmt->execute();
    $stmt->close();

    echo "<script>
        alert('Data jurnal berhasil disimpan!\\nKode Transaksi: $kode_transaksi');
        window.location.href = 'cetak_jurnal.php?kode_transaksi=$kode_transaksi';
    </script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Upload Bukpot PPh 23 Diterima</title>
<style>
body { font-family: 'Segoe UI', Arial; background: #f5f7fa; margin: 0; padding: 20px; }
header { display: flex; justify-content: space-between; align-items: center; background: #4CAF50; color: white; padding: 10px 20px; border-radius: 8px; }
h2 { margin: 0; }
a.home-btn { color: white; text-decoration: none; background: #388E3C; padding: 8px 16px; border-radius: 4px; }
form.upload-box { background: white; padding: 20px; border-radius: 8px; margin-top: 20px; box-shadow: 0 0 6px rgba(0,0,0,0.1); }
button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
button:hover { background: #45a049; }
table { border-collapse: collapse; width: 100%; background: white; margin-top: 20px; box-shadow: 0 0 6px rgba(0,0,0,0.05); }
th, td { border: 1px solid #ddd; padding: 8px; }
th { background: #f2f2f2; }
.success { color: green; margin-top: 10px; }
.fail { color: red; margin-top: 10px; }
</style>
</head>
<body>
<header>
  <h2>Upload Bukpot PPh 23 Diterima</h2>
  <a class="home-btn" href="home.php">🏠 Home</a>
</header>

<form class="upload-box" method="post" enctype="multipart/form-data">
  <label><strong>Upload file Excel (.xlsx)</strong></label><br>
  <small>Format kolom: A = INV, B = Bukpot23</small><br><br>
  <input type="file" name="excel_file" accept=".xlsx" required><br><br>
  <button type="submit">Cek Data</button>
</form>

<?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
  <div class="success">✅ Data valid: <?= count($valid_data); ?> baris</div>
  <div class="fail">❌ Data gagal: <?= count($gagal); ?> baris</div>

  <?php if (!empty($gagal)): ?>
  <table>
    <tr><th>Baris</th><th>INV</th><th>Alasan Gagal</th></tr>
    <?php foreach ($gagal as $g): ?>
    <tr><td><?= $g['baris']; ?></td><td><?= htmlspecialchars($g['inv']); ?></td><td><?= $g['alasan']; ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php if ($all_valid): ?>
  <form method="post">
    <input type="hidden" name="valid_json" value='<?= json_encode($valid_data); ?>'>
    <button type="submit" name="proses_valid">✅ Proses Data Valid</button>
  </form>
  <?php endif; ?>
<?php endif; ?>

</body>
</html>
