<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config1.php';





$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$headers = [
'ID',
'Tanggal',
'Invoice',
'No Jurnal',
'Supplier',
'Sisa',
'1-30 Hari',
'31-60 Hari',
'61-90 Hari',
'>90 Hari',
'Umur (Hari)',
'User'
];

$sheet->fromArray($headers, NULL, 'A1');

$filter = $_GET['filter'] ?? '';
$filter = trim($filter);

$filter_sql = '';

if ($filter != '') {

$escaped = $conn->real_escape_string(strtolower($filter));

$filter_sql = "
AND (
LOWER(p.inv) LIKE '%$escaped%' OR
LOWER(p.j) LIKE '%$escaped%' OR
LOWER(s.nama) LIKE '%$escaped%' OR
LOWER(p.sup) LIKE '%$escaped%'
)
";
}

$sql = "
SELECT
p.id_transaksi,
p.tanggal_transaksi,
p.inv,
p.j,
s.nama AS supplier,
p.sisa,
p.userid,
DATEDIFF(CURDATE(), p.tanggal_transaksi) AS umur
FROM pembelianho1 p
LEFT JOIN sup s ON p.sup = s.kode
WHERE p.sisa > 0
$filter_sql
ORDER BY umur DESC
";

$result = $conn->query($sql);

$rowNum = 2;

$total_sisa = 0;
$total_1_30 = 0;
$total_31_60 = 0;
$total_61_90 = 0;
$total_90_plus = 0;

while ($row = $result->fetch_assoc()) {

$hari = intval($row['umur']);
$sisa = floatval($row['sisa']);

$sisa_1_30 = ($hari >= 1 && $hari <= 30) ? $sisa : 0;
$sisa_31_60 = ($hari >= 31 && $hari <= 60) ? $sisa : 0;
$sisa_61_90 = ($hari >= 61 && $hari <= 90) ? $sisa : 0;
$sisa_90_plus = ($hari > 90) ? $sisa : 0;

$total_sisa += $sisa;
$total_1_30 += $sisa_1_30;
$total_31_60 += $sisa_31_60;
$total_61_90 += $sisa_61_90;
$total_90_plus += $sisa_90_plus;

$sheet->fromArray([
$row['id_transaksi'],
$row['tanggal_transaksi'],
$row['inv'],
$row['j'],
$row['supplier'],
$sisa,
$sisa_1_30,
$sisa_31_60,
$sisa_61_90,
$sisa_90_plus,
$hari,
$row['userid']
], NULL, 'A' . $rowNum);

$rowNum++;
}

$sheet->setCellValue("E$rowNum", "TOTAL");

$sheet->setCellValue("F$rowNum", $total_sisa);
$sheet->setCellValue("G$rowNum", $total_1_30);
$sheet->setCellValue("H$rowNum", $total_31_60);
$sheet->setCellValue("I$rowNum", $total_61_90);
$sheet->setCellValue("J$rowNum", $total_90_plus);

$sheet->getStyle("E$rowNum:L$rowNum")->getFont()->setBold(true);

$sheet->getStyle("F2:L$rowNum")
->getNumberFormat()
->setFormatCode('#,##0.00');

foreach(range('A','L') as $col){
$sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="AGING_HUTANG_SUPPLIER.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
?>