<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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
    $filter_escaped = $conn->real_escape_string($filter);
    $sql_cust = "SELECT id FROM customer WHERE customer LIKE '%$filter_escaped%'";
    $result_cust = $conn->query($sql_cust);

    $cust_ids = [];
    if ($result_cust && $result_cust->num_rows > 0) {
        while ($row_cust = $result_cust->fetch_assoc()) {
            $cust_ids[] = $row_cust['id'];
        }
    }

    $cust_filter = '';
    if (!empty($cust_ids)) {
        $cust_ids_str = implode(",", $cust_ids);
        $cust_filter = " OR pph23.cust_id IN ($cust_ids_str)";
    }

    $filter_escaped = $conn->real_escape_string($filter);
    $filter_sql = " AND (pph23.inv LIKE '%$filter_escaped%' OR pph23.kodebooking LIKE '%$filter_escaped%' $cust_filter)";
}

// Query utama
$sql = "
     SELECT 
        pph23.id,
        pph23.inv,
        pph23.kodebooking,
        pph23.cust_id,
        pph23.fp,
        (
            SELECT SUM(jurnal.kredit)
            FROM jurnal
            WHERE jurnal.journal_number = pph23.inv
              AND jurnal.coa = '21206'
        ) AS PPN,
        (
            SELECT SUM(jurnal.kredit)
            FROM jurnal
            WHERE jurnal.journal_number = pph23.inv
              AND jurnal.coa LIKE '41%'
        ) AS DPP
    FROM 
        pph23 pph23
    WHERE 
        (pph23.fp IS NOT NULL AND pph23.fp <> '')
        AND pph23.cust_id > 0
        $filter_sql
    ORDER BY 
        pph23.id
";

$result = $conn->query($sql);

// Cek error query (debugging tambahan)
if (!$result) {
    die("Query error: " . $conn->error);
}

// Buat spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header kolom
$sheet->setCellValue('A1', 'ID');
$sheet->setCellValue('B1', 'Invoice');
$sheet->setCellValue('C1', 'Kode Booking');
$sheet->setCellValue('D1', 'Supplier');
$sheet->setCellValue('E1', 'Transaksi');
$sheet->setCellValue('F1', 'DPP 11/12');
$sheet->setCellValue('G1', 'PPN');
$sheet->setCellValue('H1', 'Tarif (%)');
$sheet->setCellValue('I1', 'Nomor FP');

$rowNum = 2;
$total_dpp = 0;
$total_dpp11 = 0;
$total_ppn = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cust_id = intval($row['cust_id']);
        $customer_name = 'Tidak Ditemukan';
        $sql_customer = "SELECT customer FROM customer WHERE id = $cust_id";
        $result_customer = $conn->query($sql_customer);
        if ($result_customer && $result_customer->num_rows > 0) {
            $customer_row = $result_customer->fetch_assoc();
            $customer_name = $customer_row['customer'];
        }

        $dpp = floatval($row['DPP']);
        $dpp11 = $dpp * (11 / 12);
        $ppn = floatval($row['PPN']);
        $tarif = ($dpp11 > 0) ? ($ppn / $dpp11 * 100) : 0;

        $total_dpp += $dpp;
        $total_dpp11 += $dpp11;
        $total_ppn += $ppn;

        $sheet->setCellValue('A' . $rowNum, $row['id']);
        $sheet->setCellValue('B' . $rowNum, $row['inv']);
        $sheet->setCellValue('C' . $rowNum, $row['kodebooking']);
        $sheet->setCellValue('D' . $rowNum, $customer_name);
        $sheet->setCellValue('E' . $rowNum, $dpp);
        $sheet->setCellValue('F' . $rowNum, $dpp11);
        $sheet->setCellValue('G' . $rowNum, $ppn);
        $sheet->setCellValue('H' . $rowNum, round($tarif, 2));
        $sheet->setCellValue('I' . $rowNum, $row['fp']);
        $rowNum++;
    }

    // Tambahkan baris total
    $sheet->setCellValue('D' . $rowNum, 'TOTAL');
    $sheet->setCellValue('E' . $rowNum, $total_dpp);
    $sheet->setCellValue('F' . $rowNum, $total_dpp11);
    $sheet->setCellValue('G' . $rowNum, $total_ppn);
}

// Siapkan file untuk diunduh
$filename = "Faktur_Pajak_Sudah_Diterima_" . date('Ymd') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
