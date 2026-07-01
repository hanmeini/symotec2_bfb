<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config1.php';

/* ================= PARAMETER ================= */
$tanggal_awal  = $_GET['tanggal_awal'] ?? date('Y-m-d');
$tanggal_akhir = $_GET['tanggal_akhir'] ?? date('Y-m-d');
$lokasi        = $_GET['lokasi'] ?? '';

$start = $tanggal_awal . " 00:00:00";
$end   = $tanggal_akhir . " 23:59:59";

/* ================= KONEKSI ================= */

if ($conn->connect_error) die("Koneksi gagal");

/* ================= QUERY ================= */
$sql = "
SELECT j.*, l.nama_cabang, c.account_name
FROM jurnal j
LEFT JOIN location l ON j.location = l.idl
LEFT JOIN coa c ON j.coa = c.account_code
WHERE j.journal_number IS NOT NULL
AND j.journal_number <> ''
AND j.tanggal BETWEEN ? AND ?
";

$params = [$start, $end];
$types  = "ss";

if (!empty($lokasi)) {
    $sql .= " AND l.nama_cabang = ?";
    $params[] = $lokasi;
    $types .= "s";
}

$sql .= " ORDER BY j.journal_number ASC, j.id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

/* ================= EXCEL ================= */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

/* HEADER */
$sheet->fromArray([
    ['ID','Nomor Jurnal','Tanggal','Keterangan','Lokasi','COA','Nama Akun','Debet','Kredit']
], NULL, 'A1');

$rowNum = 2;

$total_debet = 0;
$total_kredit = 0;

while ($row = $result->fetch_assoc()) {

    $sheet->setCellValue("A$rowNum", $row['id']);
    $sheet->setCellValue("B$rowNum", $row['journal_number']);
    $sheet->setCellValue("C$rowNum", $row['tanggal']);
    $sheet->setCellValue("D$rowNum", $row['keterangan']);
    $sheet->setCellValue("E$rowNum", $row['nama_cabang']);
    $sheet->setCellValue("F$rowNum", $row['coa']);
    $sheet->setCellValue("G$rowNum", $row['account_name']);
    $sheet->setCellValue("H$rowNum", $row['debet']);
    $sheet->setCellValue("I$rowNum", $row['kredit']);

    $total_debet += $row['debet'];
    $total_kredit += $row['kredit'];

    $rowNum++;
}

/* TOTAL */
$sheet->setCellValue("G$rowNum", "TOTAL");
$sheet->setCellValue("H$rowNum", $total_debet);
$sheet->setCellValue("I$rowNum", $total_kredit);

/* FORMAT ANGKA */
$sheet->getStyle("H2:I$rowNum")
      ->getNumberFormat()
      ->setFormatCode('#,##0.00');

/* AUTO WIDTH */
foreach(range('A','I') as $col){
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

/* OUTPUT */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="jurnal_'.$tanggal_awal.'_sd_'.$tanggal_akhir.'.xlsx"');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;