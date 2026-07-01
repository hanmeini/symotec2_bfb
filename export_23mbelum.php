<?php
require 'vendor/autoload.php'; // Pastikan path ke vendor/autoload.php benar

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
require_once 'config1.php';

// Koneksi database



if ($conn->connect_error || $conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error . " / " . $conn->connect_error);
}

// Ambil filter (jika ada)
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$filter_sql = "";

if (!empty($filter)) {
    $filter_escaped2 = $conn->real_escape_string($filter);
    $sql_cust = "SELECT id FROM customer WHERE customer LIKE '%$filter_escaped2%'";
    $result_cust = $conn->query($sql_cust);

    $cust_ids = [];
    if ($result_cust && $result_cust->num_rows > 0) {
        while ($row_cust = $result_cust->fetch_assoc()) {
            $cust_ids[] = (int)$row_cust['id'];
        }
    }

    $cust_filter = '';
    if (!empty($cust_ids)) {
        $cust_ids_str = implode(",", $cust_ids);
        $cust_filter = " OR beli.cust_id IN ($cust_ids_str)";
    }

    $filter_escaped = $conn->real_escape_string($filter);
    $filter_sql = " AND (beli.inv LIKE '%$filter_escaped%' OR beli.kodebooking LIKE '%$filter_escaped%' $cust_filter)";
}

// Query utama
$sql = "
    SELECT 
        beli.id,
        beli.inv,
        beli.kodebooking,
        beli.cust_id,
        beli.pph23,
        beli.bukpot23,
        (
            SELECT SUM(jurnal.debet)
            FROM jurnal
            WHERE jurnal.journal_number = beli.inv
              AND jurnal.coa = '13103'
        ) AS PPN,
        (
            SELECT SUM(jurnal.debet)
            FROM jurnal
            WHERE jurnal.journal_number = beli.inv
              AND jurnal.coa LIKE '51%'
        ) AS DPP
    FROM 
        BELI beli
    WHERE 
        (beli.bukpot23 IS NULL OR beli.bukpot23 = '')
        AND (beli.pph23 IS NOT NULL AND beli.pph23 > 0)
        $filter_sql
    ORDER BY 
        beli.id
";

$result = $conn->query($sql);

// Buat spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header kolom
$sheet->setCellValue('A1', 'ID');
$sheet->setCellValue('B1', 'Invoice');
$sheet->setCellValue('C1', 'Kode Booking');
$sheet->setCellValue('D1', 'Supplier');
$sheet->setCellValue('E1', 'Transaksi');
$sheet->setCellValue('F1', 'PPH23');
$sheet->setCellValue('G1', 'Tarif (%)');

$rowNum = 2;
$total_dpp = 0;
$total_pph23 = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cust_id = (int)$row['cust_id'];
        $customer_name = 'Tidak Ditemukan';

        $sql_customer = "SELECT customer FROM customer WHERE id = $cust_id";
        $result_customer = $conn->query($sql_customer);
        if ($result_customer && $result_customer->num_rows > 0) {
            $customer_row = $result_customer->fetch_assoc();
            $customer_name = $customer_row['customer'];
        }

        $dpp = floatval($row['DPP']);
        $pph23 = floatval($row['pph23']);
        $tarif = ($dpp > 0) ? ($pph23 / $dpp * 100) : 0;

        $total_dpp += $dpp;
        $total_pph23 += $pph23;

        $sheet->setCellValue('A' . $rowNum, $row['id']);
        $sheet->setCellValue('B' . $rowNum, $row['inv']);
        $sheet->setCellValue('C' . $rowNum, $row['kodebooking']);
        $sheet->setCellValue('D' . $rowNum, $customer_name);
        $sheet->setCellValue('E' . $rowNum, $dpp);
        $sheet->setCellValue('F' . $rowNum, $pph23);
        $sheet->setCellValue('G' . $rowNum, round($tarif, 2));
        $rowNum++;
    }

    // Tambahkan baris total
    $sheet->setCellValue('D' . $rowNum, 'TOTAL');
    $sheet->setCellValue('E' . $rowNum, $total_dpp);
    $sheet->setCellValue('F' . $rowNum, $total_pph23);
}

// Siapkan file untuk diunduh
$filename = "PPH23_Belum_Dibuat_" . date('Ymd') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
