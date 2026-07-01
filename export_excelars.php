<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config1.php';




$spreadsheet = new Spreadsheet();

// Worksheet 1 - Detail Data
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Detail Data');
$headers = [
    'ID', 'Marketing', 'Tanggal', 'Invoice', 'Kode Booking', 'Customer', 'Tagihan', 'Bayar', 'Sisa', 
    'Location - Devisi', 'Umur (Hari)', '1-30 Hari', '31-60 Hari', '61-90 Hari', '>90 Hari'
];
$sheet1->fromArray($headers, NULL, 'A1');

$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$filter_sql = '';
if ($filter !== '') {
    $escaped_filter = $conn->real_escape_string($filter);
    $escaped_filter2 = $conn->real_escape_string($filter);

    $customer_ids = [];
    $customer_query = $conn->query("SELECT id FROM customer WHERE customer LIKE '%$escaped_filter2%'");
    while ($cust = $customer_query->fetch_assoc()) {
        $customer_ids[] = intval($cust['id']);
    }
    $id_list = implode(",", $customer_ids);

    $booking_kode_list = [];
    $booking_query = $conn->query("SELECT DISTINCT kode_booking FROM booking_request WHERE marketing LIKE '%$escaped_filter2%'");
    while ($bk = $booking_query->fetch_assoc()) {
        $booking_kode_list[] = "'" . $conn->real_escape_string($bk['kode_booking']) . "'";
    }
    $kodebooking_list = implode(",", $booking_kode_list);

      $filter_sql .= " AND (
        kodebooking LIKE '%$escaped_filter%'
        OR inv LIKE '%$escaped_filter%'
        " . (!empty($id_list) ? " OR cust_id IN ($id_list)" : "") . "
        " . (!empty($kodebooking_list) ? " OR kodebooking IN ($kodebooking_list)" : "") . "
    )";
}


$sql = "
    SELECT 
        id, tanggal, inv, kodebooking, cust_id, bukpot, pph23, tagihan, fp, bayar, sisa, location, devisi,
        DATEDIFF(CURDATE(), tanggal) AS umur
    FROM 
        pph23 
    WHERE 
        sisa > 0
        $filter_sql
    ORDER BY kodebooking
";

$result = $conn->query($sql);

$rowNum = 2;
$total_tagihan = $total_bayar = $total_sisa = $total_1_30 = $total_31_60 = $total_61_90 = $total_90_plus = 0;

$perMarketing = [];
$perCustomer = [];

while ($row = $result->fetch_assoc()) {
    $customer_name = 'Tidak Ditemukan';
    $cust_query = $conn->query("SELECT customer FROM customer WHERE id = " . intval($row['cust_id']));
    if ($cust_query && $cust_query->num_rows > 0) {
        $customer_name = $cust_query->fetch_assoc()['customer'];
    }
    $marketing_name = 'Tidak Ditemukan';
    $kode_booking = $conn->real_escape_string($row['kodebooking']);
    $sql_marketing = "SELECT marketing FROM booking_request WHERE kode_booking = '$kode_booking' LIMIT 1";
    $result_marketing = $conn->query($sql_marketing);
    if ($result_marketing && $result_marketing->num_rows > 0) {
        $marketing_name = $result_marketing->fetch_assoc()['marketing'];
    }

    $umur = intval($row['umur']);
    $sisa = floatval($row['sisa']);

    $sisa_1_30 = ($umur >= 1 && $umur <= 30) ? $sisa : 0;
    $sisa_31_60 = ($umur >= 31 && $umur <= 60) ? $sisa : 0;
    $sisa_61_90 = ($umur >= 61 && $umur <= 90) ? $sisa : 0;
    $sisa_90_plus = ($umur > 90) ? $sisa : 0;

    $sheet1->fromArray([
        $row['id'], 
        $marketing_name,
        $row['tanggal'],
        $row['inv'],
        $row['kodebooking'],
        $customer_name,
        $row['tagihan'],
        $row['bayar'],
        $row['sisa'],
        $row['location'] . " - " . $row['devisi'],
        $umur . " Hari",
        $sisa_1_30,
        $sisa_31_60,
        $sisa_61_90,
        $sisa_90_plus
    ], NULL, 'A' . $rowNum);

    $total_tagihan += floatval($row['tagihan']);
    $total_bayar += floatval($row['bayar']);
    $total_sisa += $sisa;
    $total_1_30 += $sisa_1_30;
    $total_31_60 += $sisa_31_60;
    $total_61_90 += $sisa_61_90;
    $total_90_plus += $sisa_90_plus;

    // Kumpulkan data per marketing
    if (!isset($perMarketing[$marketing_name])) {
        $perMarketing[$marketing_name] = ['tagihan' => 0, 'bayar' => 0, 'sisa' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
    }
    $perMarketing[$marketing_name]['tagihan'] += floatval($row['tagihan']);
    $perMarketing[$marketing_name]['bayar'] += floatval($row['bayar']);
    $perMarketing[$marketing_name]['sisa'] += $sisa;
    $perMarketing[$marketing_name]['1_30'] += $sisa_1_30;
    $perMarketing[$marketing_name]['31_60'] += $sisa_31_60;
    $perMarketing[$marketing_name]['61_90'] += $sisa_61_90;
    $perMarketing[$marketing_name]['90_plus'] += $sisa_90_plus;

    // Kumpulkan data per customer
    if (!isset($perCustomer[$customer_name])) {
        $perCustomer[$customer_name] = ['tagihan' => 0, 'bayar' => 0, 'sisa' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
    }
    $perCustomer[$customer_name]['tagihan'] += floatval($row['tagihan']);
    $perCustomer[$customer_name]['bayar'] += floatval($row['bayar']);
    $perCustomer[$customer_name]['sisa'] += $sisa;
    $perCustomer[$customer_name]['1_30'] += $sisa_1_30;
    $perCustomer[$customer_name]['31_60'] += $sisa_31_60;
    $perCustomer[$customer_name]['61_90'] += $sisa_61_90;
    $perCustomer[$customer_name]['90_plus'] += $sisa_90_plus;

    $rowNum++;
}

$sheet1->setCellValue("F$rowNum", 'TOTAL:');
$sheet1->setCellValue("G$rowNum", $total_tagihan);
$sheet1->setCellValue("H$rowNum", $total_bayar);
$sheet1->setCellValue("I$rowNum", $total_sisa);
$sheet1->setCellValue("L$rowNum", $total_1_30);
$sheet1->setCellValue("M$rowNum", $total_31_60);
$sheet1->setCellValue("N$rowNum", $total_61_90);
$sheet1->setCellValue("O$rowNum", $total_90_plus);
$sheet1->getStyle("F$rowNum:O$rowNum")->getFont()->setBold(true);

// Worksheet 2 - Per Marketing
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Per Marketing');
$sheet2->fromArray(['Marketing', 'Tagihan', 'Bayar', 'Sisa', '1-30', '31-60', '61-90', '>90'], NULL, 'A1');
$row2 = 2;
foreach ($perMarketing as $mkt => $data) {
    $sheet2->fromArray([
        $mkt, $data['tagihan'], $data['bayar'], $data['sisa'], $data['1_30'], $data['31_60'], $data['61_90'], $data['90_plus']
    ], NULL, 'A' . $row2);
    $row2++;
}
$sheet2->fromArray([
    'TOTAL',
    array_sum(array_column($perMarketing, 'tagihan')),
    array_sum(array_column($perMarketing, 'bayar')),
    array_sum(array_column($perMarketing, 'sisa')),
    array_sum(array_column($perMarketing, '1_30')),
    array_sum(array_column($perMarketing, '31_60')),
    array_sum(array_column($perMarketing, '61_90')),
    array_sum(array_column($perMarketing, '90_plus'))
], NULL, 'A' . $row2);
$sheet2->getStyle("A$row2:H$row2")->getFont()->setBold(true);

// Worksheet 3 - Per Customer
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Per Customer');
$sheet3->fromArray(['Customer', 'Tagihan', 'Bayar', 'Sisa', '1-30', '31-60', '61-90', '>90'], NULL, 'A1');
$row3 = 2;
foreach ($perCustomer as $cust => $data) {
    $sheet3->fromArray([
        $cust, $data['tagihan'], $data['bayar'], $data['sisa'], $data['1_30'], $data['31_60'], $data['61_90'], $data['90_plus']
    ], NULL, 'A' . $row3);
    $row3++;
}
$sheet3->fromArray([
    'TOTAL',
    array_sum(array_column($perCustomer, 'tagihan')),
    array_sum(array_column($perCustomer, 'bayar')),
    array_sum(array_column($perCustomer, 'sisa')),
    array_sum(array_column($perCustomer, '1_30')),
    array_sum(array_column($perCustomer, '31_60')),
    array_sum(array_column($perCustomer, '61_90')),
    array_sum(array_column($perCustomer, '90_plus'))
], NULL, 'A' . $row3);
$sheet3->getStyle("A$row3:H$row3")->getFont()->setBold(true);

// Output Excel file
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="ARS_MultiSheet.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
