<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';
require 'vendor/autoload.php'; // pastikan PHPSpreadsheet sudah terpasang

use PhpOffice\PhpSpreadsheet\IOFactory;

// === Koneksi database ===








$tanggal = date('Y-m-d');
$tahun   = date('Y');

// === Generate kode transaksi ===
$query = "SELECT MAX(id) AS max_nomor FROM bo";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    $max_nomor = $row['max_nomor'];
    $nomor = $max_nomor ? intval($max_nomor) + 1 : 1;
    $nomor_formatted = sprintf('%04d', $nomor);
} else {
    $nomor_formatted = '0001';
}
$kode_transaksi = "BO" . $tahun . $nomor_formatted;

$gagal = [];
$valid = [];
$total_pph23 = 0;
$berhasil = 0;

// === Tahap 1: Cek file Excel ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file']) && !isset($_POST['proses'])) {

    $coa2 = $_POST['coa2'] ?? '';
    if (empty($coa2)) {
        die("<div style='color:red'>COA Piutang PPh 23 wajib dipilih!</div>");
    }

    $coa_res = $conn->query("SELECT account_name FROM coa WHERE account_code='$coa2' LIMIT 1");
    if (!$coa_res || $coa_res->num_rows == 0) {
        die("<div style='color:red'>Kode COA tidak valid!</div>");
    }
    $coa_row = $coa_res->fetch_assoc();
    $account2 = $coa_row['account_name'];

    $file_tmp = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file_tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    foreach ($rows as $index => $row) {
        if ($index === 0) continue;

        $inv = trim($row[0]);
        $bukpot = trim($row[1]);

        if (empty($inv) || empty($bukpot)) {
            $gagal[] = ['baris' => $index + 1, 'inv' => $inv, 'alasan' => 'INV atau Bukpot kosong'];
            continue;
        }

        $inv_escaped = $conn->real_escape_string($inv);
        $cek = $conn->query("SELECT * FROM BELI WHERE inv = '$inv_escaped'");

        if ($cek->num_rows == 0) {
            $gagal[] = ['baris' => $index + 1, 'inv' => $inv, 'alasan' => 'INV tidak ditemukan'];
            continue;
        }

        $data = $cek->fetch_assoc();
        if (!is_null($data['23dibayar']) && $data['23dibayar'] !== '' && $data['23dibayar'] !== '0') {
            $gagal[] = ['baris' => $index + 1, 'inv' => $inv, 'alasan' => 'Sudah dibayar'];
            continue;
        }

        $pph23 = floatval($data['pph23']);
        $total_pph23 += $pph23;
        $valid[] = [
            'baris' => $index + 1,
            'inv' => $inv,
            'bukpot' => $bukpot,
            'pph23' => $pph23,
            'kodebooking' => $data['kodebooking'],
            'cust_id' => $data['cust_id'],
            'location' => $data['location'],
            'devisi' => $data['devisi']
        ];
    }
}

// === Tahap 2: Proses update & jurnal ===
if (isset($_POST['proses'])) {
    $coa2 = $_POST['coa2'];
    $account2 = $_POST['account2'];
    $data_valid = json_decode($_POST['data_valid'], true);
    $total_pph23 = floatval($_POST['total_pph23']);

    $conn->begin_transaction();
    try {
        foreach ($data_valid as $d) {
            $inv = $conn->real_escape_string($d['inv']);
            $bukpot = $conn->real_escape_string($d['bukpot']);

            $update = $conn->query("UPDATE BELI SET `23dibayar`='1' WHERE inv='$inv'");
            if (!$update) {
                throw new Exception("Gagal update INV: $inv");
            }

            $pph23 = floatval($d['pph23']);
            $kodebooking = $conn->real_escape_string($d['kodebooking']);
            $cust_id = $conn->real_escape_string($d['cust_id']);
            $location = $conn->real_escape_string($d['location']);
            $devisi = $conn->real_escape_string($d['devisi']);

            $insert1 = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)");
            $coa1 = "21203";
            $account1 = "PPh pasal 23";
            $keterangan1 = "Setor bukpot untuk $inv";
            $debet1 = $pph23;
            $kredit1 = 0;
            $insert1->bind_param(
                "sssssssssss",
                $kode_transaksi, $tanggal, $keterangan1,
                $coa1, $account1,
                $debet1, $kredit1,
                $kodebooking, $location, $devisi, $cust_id
            );
            $insert1->execute();
            $insert1->close();
        }

        // baris kedua jurnal (sekali)
        $insert2 = $conn->prepare("INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)");
        $keterangan2 = "Setor bukpot PPh 23 total ($total_pph23)";
        $debet2 = 0;
        $kredit2 = $total_pph23;
        $insert2->bind_param(
            "sssssss",
            $kode_transaksi, $tanggal, $keterangan2,
            $coa2, $account2, $debet2, $kredit2
        );
        $insert2->execute();
        $insert2->close();

        $sql1 = "INSERT INTO bo (bo) VALUES (?)";
        $stmt = $conn->prepare($sql1);
        $stmt->bind_param("s", $kode_transaksi);
        $stmt->execute();

        $conn->commit();

        echo "<script>
            alert('Data jurnal berhasil disimpan! Kode Transaksi: $kode_transaksi');
            window.location.href = 'cetak_jurnal.php?kode_transaksi=$kode_transaksi';
        </script>";
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        die("<div style='color:red'>Transaksi gagal: {$e->getMessage()}</div>");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Upload Bukpot PPh23</title>
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 0; padding: 0;
        background: #f4f6f8;
    }
    header {
        background: #2E7D32;
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    header h2 { margin: 0; }
    .btn-home {
        background: #fff;
        color: #2E7D32;
        padding: 8px 16px;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        transition: 0.2s;
    }
    .btn-home:hover {
        background: #e8f5e9;
    }
    main {
        max-width: 900px;
        margin: 30px auto;
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    select, input[type="file"] {
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #ccc;
    }
    button {
        background: #2E7D32;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 15px;
        transition: 0.2s;
    }
    button:hover { background: #1b5e20; }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    th, td {
        padding: 8px;
        border: 1px solid #ddd;
        text-align: left;
    }
    th { background: #f1f1f1; }
    .success { color: green; margin: 10px 0; }
    .fail { color: red; margin: 10px 0; }
</style>
</head>
<body>
<header>
    <h2>Upload Bukpot PPh 23</h2>
    <a href="home.php" class="btn-home">🏠 Home</a>
</header>

<main>
<?php if (!isset($_POST['proses'])): ?>
<form method="post" enctype="multipart/form-data">
    <label><strong>Pilih COA BANK:</strong></label>
    <select name="coa2" required>
        <option value="">-- Pilih COA --</option>
        <?php
        $sql = "SELECT account_code, account_name FROM coa WHERE layer = 4 AND account_code LIKE '112%'";
        $result = $conn->query($sql);
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['account_code']}'>{$row['account_code']} - {$row['account_name']}</option>";
        }
        ?>
    </select>

    <label><strong>File Excel (.xlsx):</strong></label>
    <input type="file" name="excel_file" accept=".xlsx" required>

    <button type="submit">🔍 Cek Data</button>
</form>
<?php endif; ?>

<?php if (!empty($valid) || !empty($gagal)): ?>
    <h3>📋 Hasil Pemeriksaan:</h3>
    <?php if (!empty($gagal)): ?>
        <div class="fail">❌ Ada <?php echo count($gagal); ?> baris bermasalah:</div>
        <table>
            <tr><th>Baris</th><th>INV</th><th>Alasan</th></tr>
            <?php foreach ($gagal as $g): ?>
                <tr>
                    <td><?= $g['baris']; ?></td>
                    <td><?= htmlspecialchars($g['inv']); ?></td>
                    <td><?= htmlspecialchars($g['alasan']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if (!empty($valid)): ?>
        <div class="success">✅ Ditemukan <?= count($valid); ?> baris valid.</div>
        <div><strong>Total PPh 23:</strong> Rp <?= number_format($total_pph23, 2, ',', '.'); ?></div>

        <?php if (empty($gagal)): ?>
            <form method="post">
                <input type="hidden" name="coa2" value="<?= htmlspecialchars($coa2 ?? ''); ?>">
                <input type="hidden" name="account2" value="<?= htmlspecialchars($account2 ?? ''); ?>">
                <input type="hidden" name="data_valid" value='<?= json_encode($valid); ?>'>
                <input type="hidden" name="total_pph23" value="<?= $total_pph23; ?>">
                <button type="submit" name="proses">💾 Proses Update & Simpan Jurnal</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
