<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

require_once 'config1.php';

// ================= PARAMETER =================
$bulan_awal = $_GET['bulan_awal'] ?? date('m');
$bulan_akhir = $_GET['bulan_akhir'] ?? $bulan_awal;
$tahun = $_GET['tahun'] ?? date('Y');

$selected_location = $_GET['location'] ?? '';
$selected_devisi = $_GET['devisi'] ?? '';

if ($selected_location === 'ALL') $selected_location = '';
if ($selected_devisi === 'ALL') $selected_devisi = '';

$tanggal_cutoff_awal = "$tahun-" . str_pad($bulan_awal, 2, '0', STR_PAD_LEFT) . "-01";
$tanggal_cutoff_akhir = date("Y-m-t", strtotime("$tahun-" . str_pad($bulan_akhir, 2, '0', STR_PAD_LEFT) . "-01"));

// ================= DB =================


if ($conn->connect_error) die("DB Error");

// ================= QUERY =================
$query = "
SELECT c.account_code, c.account_name, c.layer, c.parent_account,
    COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_awal,
    COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS total_debet,
    COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS total_kredit,
    COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_akhir,
    COALESCE(SUM(CASE WHEN c.posisi = 'P&L' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS lr_debet,
    COALESCE(SUM(CASE WHEN c.posisi = 'P&L' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS lr_kredit
FROM coa c
LEFT JOIN jurnal j 
    ON c.account_code = j.coa 
    AND j.journal_number IS NOT NULL
    " . (!empty($selected_location) ? "AND j.location = ?" : "") . "
    " . (!empty($selected_devisi) ? "AND j.devisi = ?" : "") . "
WHERE c.account_code NOT LIKE '1%' 
AND c.account_code NOT LIKE '2%' 
AND c.account_code NOT LIKE '3%'
GROUP BY c.account_code, c.account_name, c.layer, c.parent_account
HAVING NOT (c.layer = 4 AND lr_debet = 0 AND lr_kredit = 0)
ORDER BY c.account_code ASC
";

$stmt = $conn->prepare($query);

// ================= BIND =================
$params = [
    $tanggal_cutoff_awal,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir
];

$types = str_repeat('s', count($params));

if (!empty($selected_location)) {
    $params[] = $selected_location;
    $types .= 's';
}
if (!empty($selected_devisi)) {
    $params[] = $selected_devisi;
    $types .= 's';
}

$stmt->bind_param($types, ...$params);
$stmt->execute();

$stmt->bind_result(
    $account_code, $account_name, $layer, $parent_account, $saldo_awal,
    $total_debet, $total_kredit, $saldo_akhir,
    $lr_debet, $lr_kredit
);

// ================= PROSES DATA =================
$data = [];
while ($stmt->fetch()) {

    $first = substr($account_code,0,1);

    if (in_array($first,['1','5','7','9'])) {
        $saldo_akhir = $total_debet - $total_kredit;
    } else {
        $saldo_akhir = $total_kredit - $total_debet;
    }

    $lr_debet  = in_array($first,['5','6','7','9']) ? $saldo_akhir : 0;
    $lr_kredit = in_array($first,['4','6','8']) ? $saldo_akhir : 0;

    $data[$account_code] = [
        'account_code'=>$account_code,
        'account_name'=>$account_name,
        'layer'=>$layer,
        'lr_debet'=>$lr_debet,
        'lr_kredit'=>$lr_kredit
    ];
}
ksort($data);

// ================= AKUMULASI =================
foreach ($data as &$parent) {
    if ($parent['layer']==4) continue;

    foreach ($data as $child) {
        $match=false;

        if ($parent['layer']==1 && substr($child['account_code'],0,3)==substr($parent['account_code'],0,3)) $match=true;
        if ($parent['layer']==2 && substr($child['account_code'],0,6)==substr($parent['account_code'],0,6)) $match=true;
        if ($parent['layer']==3 && substr($child['account_code'],0,9)==substr($parent['account_code'],0,9)) $match=true;

        if ($match) {
            $parent['lr_debet']  += $child['lr_debet'];
            $parent['lr_kredit'] += $child['lr_kredit'];
        }
    }
}
unset($parent);

// ================= TOTAL =================
$total_debet=0;
$total_kredit=0;

foreach ($data as $r) {
    if ($r['layer']==4) {
        $total_debet+=$r['lr_debet'];
        $total_kredit+=$r['lr_kredit'];
    }
}

$laba_rugi = $total_kredit - $total_debet;

// ================= EXCEL =================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// HEADER
$headers = ['Layer 1','Layer 2','Layer 3','Layer 4','Nama Akun','LR Debet','LR Kredit'];
$col='A';
foreach($headers as $h){
    $sheet->setCellValue($col.'1',$h);
    $sheet->getStyle($col.'1')->getFont()->setBold(true);
    $sheet->getStyle($col.'1')->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('007BFF');
    $sheet->getStyle($col.'1')->getFont()->getColor()->setARGB('FFFFFF');
    $col++;
}

// DATA
$row=2;
foreach($data as $r){

    $sheet->setCellValue('A'.$row,$r['layer']==1?$r['account_code']:'');
    $sheet->setCellValue('B'.$row,$r['layer']==2?$r['account_code']:'');
    $sheet->setCellValue('C'.$row,$r['layer']==3?$r['account_code']:'');
    $sheet->setCellValue('D'.$row,$r['layer']==4?$r['account_code']:'');
    $sheet->setCellValue('E'.$row,$r['account_name']);
    $sheet->setCellValue('F'.$row,$r['lr_debet']);
    $sheet->setCellValue('G'.$row,$r['lr_kredit']);

    // WARNA LAYER
    if($r['layer']==1){
        $color='D4EDDA';
    }elseif($r['layer']==2){
        $color='F8D7DA';
    }elseif($r['layer']==3){
        $color='D1ECF1';
    }else{
        $color=null;
    }

    if($color){
        $sheet->getStyle("A$row:G$row")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB($color);

        $sheet->getStyle("A$row:G$row")->getFont()->setBold(true);
    }

    $row++;
}

// TOTAL
$sheet->setCellValue('E'.$row,'TOTAL');
$sheet->setCellValue('F'.$row,$total_debet);
$sheet->setCellValue('G'.$row,$total_kredit);

$sheet->getStyle("A$row:G$row")->getFill()
->setFillType(Fill::FILL_SOLID)
->getStartColor()->setARGB('E0E0E0');

$sheet->getStyle("A$row:G$row")->getFont()->setBold(true);

$row++;

// LABA RUGI
$sheet->setCellValue('E'.$row,'LABA RUGI');
$sheet->setCellValue('G'.$row,$laba_rugi);

$sheet->getStyle("A$row:G$row")->getFill()
->setFillType(Fill::FILL_SOLID)
->getStartColor()->setARGB('D0FFD0');

$sheet->getStyle("A$row:G$row")->getFont()->setBold(true);

// FORMAT ANGKA
$sheet->getStyle("F2:G$row")->getNumberFormat()->setFormatCode('#,##0');

// AUTO WIDTH
foreach(range('A','G') as $c){
    $sheet->getColumnDimension($c)->setAutoSize(true);
}

// ================= DOWNLOAD =================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="LR_'.$bulan_awal.'_'.$bulan_akhir.'_'.$tahun.'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;