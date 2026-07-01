<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config1.php';

// Koneksi database



$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header
$headers = ['ID', 'Tanggal', 'Invoice', 'Kode Booking', 'Customer', 'Tagihan', 'Bayar', 'Sisa', 'Location - Devisi', 'Umur (Hari)'];
$sheet->fromArray($headers, NULL, 'A1');

// Tangkap parameter filter dari URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Membuat query SQL dengan filter jika ada
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
    $filter_sql = "AND (kodebooking LIKE '%$escaped_filter%' OR inv LIKE '%$escaped_filter%' " . (!empty($id_list) ? " OR cust_id IN ($id_list)" : "") . ")";
}

// Query SQL yang diterapkan filter (jika ada)
$sql = "
    SELECT id, tanggal, inv, kodebooking, cust_id, tagihan, bayar, sisa, location, devisi,
           DATEDIFF(CURDATE(), tanggal) AS umur
    FROM pph23
    WHERE sisa > 0 $filter_sql
    ORDER BY DATEDIFF(CURDATE(), tanggal) DESC
";

// Eksekusi query
$result = $conn->query($sql);

$rowNum = 2;
$total_tagihan = 0;
$total_bayar = 0;
$total_sisa = 0;

// Memasukkan data ke dalam spreadsheet
while ($row = $result->fetch_assoc()) {
    $customer_name = 'Tidak Ditemukan';
    $cust_query = $conn->query("SELECT customer FROM customer WHERE id = " . intval($row['cust_id']));
    if ($cust_query && $cust_query->num_rows > 0) {
        $customer_name = $cust_query->fetch_assoc()['customer'];
    }

    $sheet->fromArray([
        $row['id'],
        $row['tanggal'],
        $row['inv'],
        $row['kodebooking'],
        $customer_name,
        $row['tagihan'],
        $row['bayar'],
        $row['sisa'],
        $row['location'] . " - " . $row['devisi'],
        $row['umur'] . " Hari"
    ], NULL, 'A' . $rowNum);

    $total_tagihan += $row['tagihan'];
    $total_bayar += $row['bayar'];
    $total_sisa += $row['sisa'];

    $rowNum++;
}

// Tambahkan total di baris terakhir
$sheet->setCellValue("E$rowNum", 'TOTAL:');
$sheet->setCellValue("F$rowNum", $total_tagihan);
$sheet->setCellValue("G$rowNum", $total_bayar);
$sheet->setCellValue("H$rowNum", $total_sisa);

// Format bold untuk total
$sheet->getStyle("E$rowNum:H$rowNum")->getFont()->setBold(true);

// Output file Excel jika tombol export ditekan
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="AR.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>
