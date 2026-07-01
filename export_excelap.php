<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start([
    'cookie_lifetime'=>86400,
    'cookie_httponly'=>true,
    'cookie_secure'=>isset($_SERVER['HTTPS']),
    'use_only_cookies'=>true,
    'use_strict_mode'=>true,
]);

if(!isset($_SESSION['username'])){
    header("Location:index.html");
    exit();
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config1.php';





$filter=$_GET['filter'] ?? '';
$filter=trim($filter);

$filter_sql="";
if($filter!=""){
    $escaped=$conn->real_escape_string(strtolower($filter));
    $filter_sql=" AND (
        LOWER(p.inv) LIKE '%$escaped%' OR
        LOWER(p.j) LIKE '%$escaped%' OR
        LOWER(s.nama) LIKE '%$escaped%' OR
        LOWER(p.sup) LIKE '%$escaped%'
    )";
}

$sql="
SELECT
    p.id_transaksi,
    p.tanggal_transaksi,
    p.inv,
    p.j,
    s.nama AS supplier,
    p.hargat_m AS tagihan,
    p.pph15m,
    p.pph22m,
    p.pph23m,
    p.sisa,
    DATEDIFF(CURDATE(),p.tanggal_transaksi) AS umur
FROM pembelianho1 p
LEFT JOIN sup s ON p.sup=s.kode
WHERE p.sisa>0 AND j NOT LIKE 'co%'
$filter_sql
ORDER BY umur DESC
";

$result=$conn->query($sql);

$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();

$headers=[
    'ID','Tanggal','Invoice','No Jurnal','Supplier',
    'Tagihan','Bayar','PPH15','PPH22','PPH23','Sisa','Umur (Hari)'
];
$sheet->fromArray($headers,NULL,'A1');

$rowNum=2;
$total_tagihan=0;
$total_bayar=0;
$total_pph15=0;
$total_pph22=0;
$total_pph23=0;
$total_sisa=0;

while($row=$result->fetch_assoc()){
    // Hitung ulang Bayar sesuai formula
    $bayar_hitung = $row['tagihan']
                  - $row['pph15m']
                  - $row['pph22m']
                  - $row['pph23m']
                  - $row['sisa'];

    $sheet->fromArray([
        $row['id_transaksi'],
        $row['tanggal_transaksi'],
        $row['inv'],
        $row['j'],
        $row['supplier'],
        $row['tagihan'],
        $bayar_hitung,
        $row['pph15m'],
        $row['pph22m'],
        $row['pph23m'],
        $row['sisa'],
        $row['umur']." Hari"
    ],NULL,'A'.$rowNum);

    // Akumulasi total
    $total_tagihan += $row['tagihan'];
    $total_bayar   += $bayar_hitung;
    $total_pph15   += $row['pph15m'];
    $total_pph22   += $row['pph22m'];
    $total_pph23   += $row['pph23m'];
    $total_sisa    += $row['sisa'];

    $rowNum++;
}

// Baris total
$sheet->setCellValue("E$rowNum",'TOTAL');
$sheet->setCellValue("F$rowNum",$total_tagihan);
$sheet->setCellValue("G$rowNum",$total_bayar);
$sheet->setCellValue("H$rowNum",$total_pph15);
$sheet->setCellValue("I$rowNum",$total_pph22);
$sheet->setCellValue("J$rowNum",$total_pph23);
$sheet->setCellValue("K$rowNum",$total_sisa);
$sheet->getStyle("E$rowNum:K$rowNum")->getFont()->setBold(true);

// Auto size kolom
foreach(range('A','L') as $col){
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output file Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="AP_SUPPLIER.xlsx"');
header('Cache-Control: max-age=0');

$writer=new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
