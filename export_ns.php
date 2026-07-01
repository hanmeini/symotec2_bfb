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
$bulan_awal  = $_GET['bulan_awal'] ?? date('m');
$bulan_akhir = $_GET['bulan_akhir'] ?? $bulan_awal;
$tahun       = $_GET['tahun'] ?? date('Y');
$location    = $_GET['location'] ?? '';
$devisi      = $_GET['devisi'] ?? '';

// ================= TANGGAL =================
$tanggal_awal  = "$tahun-$bulan_awal-01";
$tanggal_akhir = date("Y-m-t", strtotime("$tahun-$bulan_akhir-01"));

// ================= DB =================


// ================= QUERY (SAMA HALAMAN) =================
$filter_location = !empty($location) ? "AND j.location = ?" : "";
$filter_devisi   = !empty($devisi) ? "AND j.devisi = ?" : "";

$query = "
SELECT c.account_code, c.account_name, c.layer, c.parent_account,
COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END),0),
COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END),0),
COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END),0),
COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END),0),
COALESCE(SUM(CASE WHEN c.posisi='P&L' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END),0),
COALESCE(SUM(CASE WHEN c.posisi='P&L' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END),0),
COALESCE(SUM(CASE WHEN c.posisi='neraca' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END),0),
COALESCE(SUM(CASE WHEN c.posisi='neraca' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END),0)
FROM coa c
LEFT JOIN jurnal j 
ON c.account_code=j.coa
AND j.journal_number IS NOT NULL
$filter_location
$filter_devisi
GROUP BY c.account_code,c.account_name,c.layer,c.parent_account
ORDER BY c.account_code
";

$stmt = $conn->prepare($query);

$types = "ssssssssssssss";
$params = [
$tanggal_awal,$tanggal_awal,$tanggal_akhir,
$tanggal_awal,$tanggal_akhir,$tanggal_akhir,
$tanggal_awal,$tanggal_akhir,$tanggal_awal,
$tanggal_akhir,$tanggal_awal,$tanggal_akhir,
$tanggal_awal,$tanggal_akhir
];

if($location){ $types.="s"; $params[]=$location; }
if($devisi){ $types.="s"; $params[]=$devisi; }

$stmt->bind_param($types,...$params);
$stmt->execute();

$stmt->bind_result(
$kode,$nama,$layer,$parent,
$saldo_awal,$debet,$kredit,$saldo_akhir,
$lr_debet,$lr_kredit,$dn,$kn
);

// ================= PROSES (SAMA HALAMAN) =================
$data=[];

while($stmt->fetch()){

$f=substr($kode,0,1);

$sd=0;$sk=0;

if(in_array($f,['1','2','3'])){
    if($f=='1') $sd=max(0,$saldo_awal);
    else $sk=max(0,$saldo_awal);
}

if(in_array($f,['1','5','7','9']))
    $saldo_akhir=$sd+$debet-$kredit;
else
    $saldo_akhir=$sk-$debet+$kredit;

$lrk = in_array($f,['4','6','8'])?$saldo_akhir:0;
$lrd = in_array($f,['5','7','9'])?$saldo_akhir:0;

$dn = $f=='1'?$saldo_akhir:0;
$kn = in_array($f,['2','3'])?$saldo_akhir:0;

$data[$kode]=[
'kode'=>$kode,'nama'=>$nama,'layer'=>$layer,
'sd'=>$sd,'sk'=>$sk,'d'=>$debet,'k'=>$kredit,
'sa'=>$saldo_akhir,'lrd'=>$lrd,'lrk'=>$lrk,
'dn'=>$dn,'kn'=>$kn
];
}

ksort($data);

// ================= AKUMULASI PARENT =================
foreach($data as $k=>&$v){
if($v['layer']==4) continue;

foreach($data as $ck=>$cv){

$match=false;

if($v['layer']==1 && substr($ck,0,3)==substr($k,0,3)) $match=true;
if($v['layer']==2 && substr($ck,0,6)==substr($k,0,6)) $match=true;
if($v['layer']==3 && substr($ck,0,9)==substr($k,0,9)) $match=true;

if($match){
foreach(['sd','sk','d','k','sa','lrd','lrk','dn','kn'] as $col)
$v[$col]+=$cv[$col];
}
}
}
unset($v);

// ================= TOTAL =================
$total=['sd'=>0,'sk'=>0,'d'=>0,'k'=>0,'sa'=>0,'lrd'=>0,'lrk'=>0,'dn'=>0,'kn'=>0];

foreach($data as $r){
if($r['layer']==4){
foreach($total as $k=>$_) $total[$k]+=$r[$k];
}
}

// ================= EXCEL =================
$excel=new Spreadsheet();
$sheet=$excel->getActiveSheet();

// HEADER
$header=[
'Layer1','Layer2','Layer3','Layer4','Nama',
'SA Debet','SA Kredit','Debet','Kredit',
'Saldo','LR Debet','LR Kredit','DN','KN'
];

$col='A';
foreach($header as $h){
$sheet->setCellValue($col.'1',$h);
$col++;
}

// DATA
$row=2;

foreach($data as $r){

$sheet->fromArray([
$r['layer']==1?$r['kode']:'',
$r['layer']==2?$r['kode']:'',
$r['layer']==3?$r['kode']:'',
$r['layer']==4?$r['kode']:'',
$r['nama'],$r['sd'],$r['sk'],$r['d'],$r['k'],
$r['sa'],$r['lrd'],$r['lrk'],$r['dn'],$r['kn']
],NULL,"A$row");

if($r['layer']==1) $color='D4EDDA';
elseif($r['layer']==2) $color='F8D7DA';
elseif($r['layer']==3) $color='D1ECF1';
else $color=null;

if($color){
$sheet->getStyle("A$row:N$row")->getFill()
->setFillType(Fill::FILL_SOLID)
->getStartColor()->setARGB($color);
}

$row++;
}

// TOTAL
$sheet->mergeCells("A$row:E$row");
$sheet->setCellValue("A$row","TOTAL");

$colIndex='F';
foreach($total as $v){
$sheet->setCellValue($colIndex.$row,$v);
$colIndex++;
}

$row++;

// LABA RUGI
$lr=$total['lrk']-$total['lrd'];
$sheet->mergeCells("A$row:K$row");
$sheet->setCellValue("A$row","LABA RUGI BULAN INI");
$sheet->setCellValue("L$row",$lr);

$row++;

// SELISIH NERACA
$sn=$total['dn']-$total['kn'];
$sheet->mergeCells("A$row:K$row");
$sheet->setCellValue("A$row","SELISIH NERACA");
$sheet->setCellValue("M$row",$sn);

$row++;

// FINAL
$final=$sn+$lr;
$sheet->mergeCells("A$row:K$row");
$sheet->setCellValue("A$row","SELISIH NERACA TERHADAP LR");
$sheet->setCellValue("N$row",$final);

// FORMAT ANGKA
$sheet->getStyle("F2:N$row")->getNumberFormat()
->setFormatCode('#,##0');

// AUTO WIDTH
foreach(range('A','N') as $c)
$sheet->getColumnDimension($c)->setAutoSize(true);

// DOWNLOAD
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="neraca_saldo.xlsx"');

$writer=new Xlsx($excel);
$writer->save('php://output');
exit;
?>