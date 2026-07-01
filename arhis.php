<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';
require 'vendor/autoload.php'; // pastikan sudah ada composer require phpoffice/phpspreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --- Koneksi database ---



if ($conn->connect_error) die("Koneksi ke database pertama gagal: " . $conn->connect_error);

$end_date = $_POST['end_date'] ?? '';
$action = $_POST['action'] ?? '';
$results = [];
$rekap_percust = [];

if (!empty($end_date)) {
    // Ambil semua customer
    $custRes = $conn->query("SELECT id, nama AS cust FROM cust ORDER BY id ASC");
    while ($cust = $custRes->fetch_assoc()) {
        $cust_id = $cust['id'];
        $cust_name = $cust['cust'];

        // Ambil semua invoice
        $stmt_inv = $conn->prepare("
            SELECT inv, tanggal, tagihan, pph23, bayar, sisa, bukpot, fp
            FROM pph23
            WHERE cust_id = ?
            ORDER BY tanggal ASC
        ");
        $stmt_inv->bind_param("i", $cust_id);
        $stmt_inv->execute();
        $stmt_inv->store_result();
        $stmt_inv->bind_result($inv, $tanggal, $tagihan, $pph23, $bayar, $sisa, $bukpot, $fp);

        $total_inv_cust = $total_pph23_cust = $total_ttp_cust = $total_bayar_cust = $total_saldo_cust = 0;

        while ($stmt_inv->fetch()) {
            // Hitung DPP & PPN
            $nilai_DPP = $nilai_PPN = 0;
            $sql_dpp = $conn->prepare("SELECT SUM(kredit) FROM jurnal WHERE journal_number = ? AND coa LIKE '41%'");
            $sql_dpp->bind_param("s", $inv);
            $sql_dpp->execute();
            $sql_dpp->bind_result($nilai_DPP);
            $sql_dpp->fetch();
            $sql_dpp->close();

            $sql_ppn = $conn->prepare("SELECT SUM(kredit) FROM jurnal WHERE journal_number = ? AND coa = '21206'");
            $sql_ppn->bind_param("s", $inv);
            $sql_ppn->execute();
            $sql_ppn->bind_result($nilai_PPN);
            $sql_ppn->fetch();
            $sql_ppn->close();

            $nilai_inv = floatval($nilai_DPP) + floatval($nilai_PPN);
            $ttp = max(0, $nilai_inv - $pph23 - $tagihan);

            // Pembayaran sampai tanggal akhir
            $stmt_pay = $conn->prepare("SELECT SUM(bayar1) FROM arby WHERE inv = ? AND tanggal <= ?");
            $stmt_pay->bind_param("ss", $inv, $end_date);
            $stmt_pay->execute();
            $stmt_pay->bind_result($total_bayar);
            $stmt_pay->fetch();
            $stmt_pay->close();

            $total_bayar = $total_bayar ?: 0;
            $saldo_akhir = $nilai_inv - $pph23 - $ttp - $total_bayar;

            if (abs($saldo_akhir) > 1) {
                $results[] = [
                    'cust_name' => $cust_name,
                    'inv' => $inv,
                    'tanggal' => $tanggal,
                    'nilai_inv' => $nilai_inv,
                    'pph23' => $pph23,
                    'ttp' => $ttp,
                    'bayar' => $total_bayar,
                    'saldo' => $saldo_akhir,
                    'bukpot' => $bukpot,
                    'fp' => $fp
                ];

                $total_inv_cust   += $nilai_inv;
                $total_pph23_cust += $pph23;
                $total_ttp_cust   += $ttp;
                $total_bayar_cust += $total_bayar;
                $total_saldo_cust += $saldo_akhir;
            }
        }

        $stmt_inv->close();

        if ($total_saldo_cust != 0) {
            $rekap_percust[] = [
                'customer' => $cust_name,
                'total_inv' => $total_inv_cust,
                'total_pph23' => $total_pph23_cust,
                'total_ttp' => $total_ttp_cust,
                'total_bayar' => $total_bayar_cust,
                'total_saldo' => $total_saldo_cust
            ];
        }
    }
}

// === EXPORT EXCEL ===
if ($action === 'export' && !empty($results)) {
    $spreadsheet = new Spreadsheet();

    // Sheet 1: Detail Invoice
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Detail Invoice');

    $headers1 = ['No', 'Customer', 'Tanggal', 'Invoice', 'Nilai Inv', 'PPh 23', 'Titipan', 'Dibayar', 'Saldo Akhir', 'Bukpot', 'Faktur'];
    $sheet1->fromArray($headers1, NULL, 'A1');
    $row = 2;
    $no = 1;

    foreach ($results as $r) {
        $sheet1->fromArray([
            $no++,
            $r['cust_name'],
            $r['tanggal'],
            $r['inv'],
            $r['nilai_inv'],
            $r['pph23'],
            $r['ttp'],
            $r['bayar'],
            $r['saldo'],
            $r['bukpot'],
            $r['fp']
        ], NULL, "A$row");
        $row++;
    }

    // Sheet 2: Rekap per Customer
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Rekap per Customer');
    $headers2 = ['No', 'Customer', 'Total Nilai Inv', 'Total PPh 23', 'Total Titipan', 'Total Dibayar', 'Total Saldo Akhir'];
    $sheet2->fromArray($headers2, NULL, 'A1');
    $row = 2;
    $no = 1;
    foreach ($rekap_percust as $c) {
        $sheet2->fromArray([
            $no++,
            $c['customer'],
            $c['total_inv'],
            $c['total_pph23'],
            $c['total_ttp'],
            $c['total_bayar'],
            $c['total_saldo']
        ], NULL, "A$row");
        $row++;
    }

    $filename = "AR_History_" . date('Ymd_His') . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    $writer = new Xlsx($spreadsheet);
    $writer->save("php://output");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>AR History - Saldo Akhir per Customer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; background: #f8f9fa; padding: 20px; }
.container { background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
th, td { border: 1px solid #ccc; padding: 8px; }
th { background: #007bff; color: white; text-align: center; }
td.text-right { text-align: right; }
tr.total-row { background: #f1f8e9; font-weight: bold; }
.btn { background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 5px; cursor: pointer; }
h3 { margin-top: 30px; }
</style>
</head>
<body>
<div class="container">
<a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>

<form method="POST">
    <label>Tanggal Akhir:</label>
    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
    <button type="submit" class="btn">Tampilkan</button>
    <?php if (!empty($results)): ?>
        <button type="submit" name="action" value="export" class="btn" style="background:green;">Export Excel</button>
    <?php endif; ?>
</form>
<hr>

<?php if (!empty($results)): ?>
<h3>Saldo Akhir per Invoice - per <?= htmlspecialchars($end_date) ?></h3>
<table>
    <tr>
        <th>No</th><th>Customer</th><th>Tanggal</th><th>Invoice</th>
        <th>Nilai Inv</th><th>PPh 23</th><th>Titipan</th><th>Dibayar</th><th>Saldo Akhir</th><th>Bukpot</th><th>Faktur</th>
    </tr>
    <?php
    $no=1;$tinv=$tpph=$tttp=$tbyr=$tsaldo=0;
    foreach ($results as $r):
        $tinv+=$r['nilai_inv'];$tpph+=$r['pph23'];$tttp+=$r['ttp'];$tbyr+=$r['bayar'];$tsaldo+=$r['saldo'];
    ?>
    <tr>
        <td><?= $no++ ?></td><td><?= htmlspecialchars($r['cust_name']) ?></td><td><?= htmlspecialchars($r['tanggal']) ?></td>
        <td><?= htmlspecialchars($r['inv']) ?></td>
        <td class="text-right"><?= number_format($r['nilai_inv'],2,',','.') ?></td>
        <td class="text-right"><?= number_format($r['pph23'],2,',','.') ?></td>
        <td class="text-right"><?= number_format($r['ttp'],2,',','.') ?></td>
        <td class="text-right"><?= number_format($r['bayar'],2,',','.') ?></td>
        <td class="text-right" style="font-weight:bold;"><?= number_format($r['saldo'],2,',','.') ?></td>
        <td><?= htmlspecialchars($r['bukpot'] ?: '-') ?></td>
        <td><?= htmlspecialchars($r['fp'] ?: '-') ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td colspan="4" align="right">TOTAL</td>
        <td class="text-right"><?= number_format($tinv,2,',','.') ?></td>
        <td class="text-right"><?= number_format($tpph,2,',','.') ?></td>
        <td class="text-right"><?= number_format($tttp,2,',','.') ?></td>
        <td class="text-right"><?= number_format($tbyr,2,',','.') ?></td>
        <td class="text-right"><?= number_format($tsaldo,2,',','.') ?></td>
        <td colspan="2"></td>
    </tr>
</table>

<h3>Rekap per Customer (Tanpa Bukpot & Faktur)</h3>
<table>
    <tr>
        <th>No</th><th>Customer</th><th>Total Nilai Inv</th><th>Total PPh 23</th><th>Total Titipan</th><th>Total Dibayar</th><th>Total Saldo Akhir</th>
    </tr>
    <?php
    $no=1;$ginv=$gpph=$gttp=$gbyr=$gsaldo=0;
    foreach ($rekap_percust as $c):
        $ginv+=$c['total_inv'];$gpph+=$c['total_pph23'];$gttp+=$c['total_ttp'];$gbyr+=$c['total_bayar'];$gsaldo+=$c['total_saldo'];
    ?>
    <tr>
        <td><?= $no++ ?></td><td><?= htmlspecialchars($c['customer']) ?></td>
        <td class="text-right"><?= number_format($c['total_inv'],2,',','.') ?></td>
        <td class="text-right"><?= number_format($c['total_pph23'],2,',','.') ?></td>
        <td class="text-right"><?= number_format($c['total_ttp'],2,',','.') ?></td>
        <td class="text-right"><?= number_format($c['total_bayar'],2,',','.') ?></td>
        <td class="text-right" style="font-weight:bold;"><?= number_format($c['total_saldo'],2,',','.') ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td colspan="2" align="right">GRAND TOTAL</td>
        <td class="text-right"><?= number_format($ginv,2,',','.') ?></td>
        <td class="text-right"><?= number_format($gpph,2,',','.') ?></td>
        <td class="text-right"><?= number_format($gttp,2,',','.') ?></td>
        <td class="text-right"><?= number_format($gbyr,2,',','.') ?></td>
        <td class="text-right"><?= number_format($gsaldo,2,',','.') ?></td>
    </tr>
</table>
<?php endif; ?>
</div>
</body>
</html>
