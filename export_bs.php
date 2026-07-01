<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['userid'])) {
    die("Akses ditolak!");
}

require_once "config.php";
require "vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Koneksi database



// Parameter BS
if (!isset($_GET['bs']) || $_GET['bs'] === "") {
    die("Parameter BS tidak ada.");
}
$bs = $_GET['bs'];

// Ambil data per BS
$sql = "
    SELECT tanggal_transaksi, kodesup, kodebarang, namabarang,
           quantity, satuan, grading, nomor_pembelian, sj
    FROM masuksementara
    WHERE bs = ?
    ORDER BY tanggal_transaksi ASC, id_transaksi ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $bs);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Tidak ada data untuk BS ini.");
}

$data = [];
$sj = "";

while ($r = $result->fetch_assoc()) {
    $data[] = $r;
    if ($sj == "" && isset($r['sj'])) {
        $sj = $r['sj'];
    }
}

// ---------------------------
//  SIAPKAN FILE EXCEL
// ---------------------------
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Judul
$sheet->setCellValue("A1", "LAPORAN BS");
$sheet->setCellValue("A2", "BS: " . $bs);
$sheet->setCellValue("A3", "Surat Jalan (SJ): " . $sj);

// Header tabel
$sheet->setCellValue("A5", "Tanggal");
$sheet->setCellValue("B5", "Kode Supplier");
$sheet->setCellValue("C5", "Kode Barang");
$sheet->setCellValue("D5", "Nama Barang");
$sheet->setCellValue("E5", "Qty");
$sheet->setCellValue("F5", "Satuan");
$sheet->setCellValue("G5", "Grade");
$sheet->setCellValue("H5", "No pembelianho1");

$row = 6;
$subtotal = 0;

// Isi data
foreach ($data as $d) {
    $sheet->setCellValue("A$row", $d['tanggal_transaksi']);
    $sheet->setCellValue("B$row", $d['kodesup']);
    $sheet->setCellValue("C$row", $d['kodebarang']);
    $sheet->setCellValue("D$row", $d['namabarang']);
    $sheet->setCellValue("E$row", $d['quantity']);
    $sheet->setCellValue("F$row", $d['satuan']);
    $sheet->setCellValue("G$row", $d['grading']);
    $sheet->setCellValue("H$row", $d['nomor_pembelian']);

    $subtotal += floatval($d['quantity']);
    $row++;
}

// Subtotal
$sheet->setCellValue("D$row", "Subtotal Qty:");
$sheet->setCellValue("E$row", $subtotal);

// Auto width kolom
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// File name
$filename = "BS_" . $bs . "_" . date("Ymd-His") . ".xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment;filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;

?>
