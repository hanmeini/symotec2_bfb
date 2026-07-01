<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();

require_once 'config1.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/* ===========================
   CEK LOGIN
=========================== */

if(!isset($_SESSION['username'])){
header("Location:index.html");
exit();
}

/* ===========================
   KONEKSI DATABASE
=========================== */





/* ===========================
   FILTER
=========================== */

$filter=$_GET['filter'] ?? '';
$status=$_GET['status'] ?? '';

$filter_sql='';

if($filter!=''){

$escaped=$conn->real_escape_string(strtolower($filter));

$filter_sql="
AND (
LOWER(p.inv) LIKE '%$escaped%'
OR LOWER(p.j) LIKE '%$escaped%'
OR LOWER(s.nama) LIKE '%$escaped%'
OR LOWER(p.sup) LIKE '%$escaped%'
)
";

}

/* ===========================
   STATUS
=========================== */

$status_sql='';

if($status=='lunas'){
$status_sql="AND p.sisa = 0";
}
elseif($status=='dibayar'){
$status_sql="AND p.bayar > 0";
}

/* ===========================
   QUERY
=========================== */

$sql="

SELECT
p.id_transaksi,
p.tanggal_transaksi,
p.inv,
p.j,
s.nama AS supplier,
p.hargat_m AS tagihan,
p.bayar,
p.sisa,
p.pph15m,
p.pph22m,
p.pph23m,
p.userid

FROM pembelianho1 p

LEFT JOIN sup s
ON p.sup = s.kode

WHERE 1=1
$status_sql
$filter_sql

ORDER BY p.tanggal_transaksi DESC

";

$result=$conn->query($sql);

/* ===========================
   BUAT EXCEL
=========================== */

$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();

$sheet->setTitle("Laporan AP Supplier");

/* ===========================
   HEADER
=========================== */

$headers=[

'No',
'Tanggal',
'Invoice',
'No Jurnal',
'Supplier',
'Tagihan',
'Bayar',
'Sisa',
'PPH15',
'PPH22',
'PPH23',
'User'

];

$sheet->fromArray($headers,NULL,'A1');

/* ===========================
   DATA
=========================== */

$rowIndex=2;
$no=1;

$total_tagihan=0;
$total_bayar=0;
$total_sisa=0;
$total_pph15=0;
$total_pph22=0;
$total_pph23=0;

while($row=$result->fetch_assoc()){

$sheet->fromArray([

$no++,
$row['tanggal_transaksi'],
$row['inv'],
$row['j'],
$row['supplier'],
$row['tagihan'],
$row['bayar'],
$row['sisa'],
$row['pph15m'],
$row['pph22m'],
$row['pph23m'],
$row['userid']

],NULL,"A{$rowIndex}");

$total_tagihan += $row['tagihan'];
$total_bayar   += $row['bayar'];
$total_sisa    += $row['sisa'];
$total_pph15   += $row['pph15m'];
$total_pph22   += $row['pph22m'];
$total_pph23   += $row['pph23m'];

$rowIndex++;

}

/* ===========================
   TOTAL
=========================== */

$sheet->fromArray([

'TOTAL','','','',
'',
$total_tagihan,
$total_bayar,
$total_sisa,
$total_pph15,
$total_pph22,
$total_pph23

],NULL,"A{$rowIndex}");

$sheet->getStyle("A{$rowIndex}:L{$rowIndex}")
->getFont()
->setBold(true);

/* ===========================
   FORMAT ANGKA
=========================== */

$sheet->getStyle("F2:K{$rowIndex}")
->getNumberFormat()
->setFormatCode('#,##0.00');

/* ===========================
   AUTO SIZE
=========================== */

foreach(range('A','L') as $col){
$sheet->getColumnDimension($col)->setAutoSize(true);
}

/* ===========================
   DOWNLOAD FILE
=========================== */

$filename="Laporan_AP_Supplier.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer=new Xlsx($spreadsheet);
$writer->save('php://output');

$conn->close();

exit;
?>