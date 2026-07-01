<?php
session_start();
require_once 'config1.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

// --- Koneksi database ---


if ($conn->connect_error)  die("Koneksi DB utama gagal: " . $conn->connect_error);
if ($conn->connect_error) die("Koneksi DB customer gagal: " . $conn->connect_error);

// --- Ambil filter dari POST ---
$supcust    = $_POST['supcust'] ?? '';
$start_date = $_POST['start_date'] ?? date('Y-m-01');
$end_date   = $_POST['end_date'] ?? date('Y-m-t');

// --- Ambil nama customer ---
$customer_name = '';
if (!empty($supcust)) {
    $stmt = $conn->prepare("SELECT customer FROM customer WHERE id = ?");
    $stmt->bind_param("i", $supcust);
    $stmt->execute();
    $stmt->bind_result($cust_raw);
    $stmt->fetch();
    $customer_name = $cust_raw;
    $stmt->close();
}

// --- Hitung saldo awal ---
$saldo_awal = 0;
if (!empty($supcust)) {
    $sql_saldo = "
        SELECT inv, tagihan AS nilai_inv, pph23
        FROM BELI
        WHERE cust_id = ? AND tanggal < ?";
    $st = $conn->prepare($sql_saldo);
    $st->bind_param("is", $supcust, $start_date);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
        $inv = $row['inv'];
        $nilai = $row['nilai_inv'];
        $pph = $row['pph23'];

        $sql_bayar = "SELECT COALESCE(SUM(bayar1),0) FROM apby WHERE inv = ?";
        $st2 = $conn->prepare($sql_bayar);
        $st2->bind_param("s", $inv);
        $st2->execute();
        $st2->bind_result($total_bayar);
        $st2->fetch();
        $st2->close();

        $saldo_awal += ($nilai - $pph - $total_bayar);
    }
    $st->close();
}

// --- Ambil invoice periode ---
$invoices = [];
if (!empty($supcust)) {
    $sql = "SELECT inv, tanggal, tagihan AS nilai_inv, pph23, bukpot23, fp 
            FROM BELI
            WHERE cust_id = ? AND tanggal BETWEEN ? AND ?
            ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $supcust, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) $invoices[] = $r;
    $stmt->close();
}

// --- Generate Excel ---
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('SOA Detail');

// Header Info
$sheet->setCellValue('A1', 'STATEMENT OF ACCOUNT (SOA)');
$sheet->mergeCells('A1:I1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->setCellValue('A3', 'Customer:');
$sheet->setCellValue('B3', $customer_name);
$sheet->setCellValue('A4', 'Periode:');
$sheet->setCellValue('B4', "$start_date s/d $end_date");
$sheet->setCellValue('A5', 'Saldo Awal:');
$sheet->setCellValue('B5', $saldo_awal);

// Header Table
$headerRow = 7;
$headers = ['Tanggal', 'Invoice', 'Nilai Inv', 'PPh 23', 'Dibayar', 'Sisa', 'Akhir Sisa', 'Bukpot', 'Faktur'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $headerRow, $h);
    $col++;
}
$sheet->getStyle("A{$headerRow}:I{$headerRow}")->getFont()->setBold(true);
$sheet->getStyle("A{$headerRow}:I{$headerRow}")
      ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("A{$headerRow}:I{$headerRow}")
      ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('007BFF');

// Data
$rowNum = $headerRow + 1;

// Saldo awal
$sheet->setCellValue("A{$rowNum}", "Saldo Awal Sebelum {$start_date}");
$sheet->mergeCells("A{$rowNum}:E{$rowNum}");
$sheet->setCellValue("F{$rowNum}", $saldo_awal);
$sheet->setCellValue("G{$rowNum}", $saldo_awal);
$sheet->getStyle("A{$rowNum}:I{$rowNum}")->getFont()->setBold(true);
$rowNum++;

$total_inv = 0;
$total_pph = 0;
$total_akhir_sisa = $saldo_awal;

foreach ($invoices as $inv) {
    $inv_no    = $inv['inv'];
    $inv_tgl   = $inv['tanggal'];
    $nilai_inv = $inv['nilai_inv'];
    $pph23     = $inv['pph23'];
    $bukpot    = $inv['bukpot23'];
    $fp        = $inv['fp'];

    $sql_total_bayar = "SELECT COALESCE(SUM(bayar1),0) FROM apby WHERE inv = ?";
    $stx = $conn->prepare($sql_total_bayar);
    $stx->bind_param("s", $inv_no);
    $stx->execute();
    $stx->bind_result($total_bayar_inv);
    $stx->fetch();
    $stx->close();

    $sisa_awal_inv = $nilai_inv - $pph23;
    $akhir_sisa_inv = $sisa_awal_inv - $total_bayar_inv;

    $sheet->setCellValue("A{$rowNum}", $inv_tgl);
    $sheet->setCellValue("B{$rowNum}", $inv_no);
    $sheet->setCellValue("C{$rowNum}", $nilai_inv);
    $sheet->setCellValue("D{$rowNum}", $pph23);
    $sheet->setCellValue("E{$rowNum}", 0);
    $sheet->setCellValue("F{$rowNum}", $sisa_awal_inv);
    $sheet->setCellValue("G{$rowNum}", $akhir_sisa_inv);
    $sheet->setCellValue("H{$rowNum}", $bukpot ?: '-');
    $sheet->setCellValue("I{$rowNum}", $fp ?: '-');
    $sheet->getStyle("A{$rowNum}:I{$rowNum}")->getFont()->setBold(true);
    $rowNum++;

    // Detail pembayaran
    $sql_bayar = "SELECT tanggal, bayar1 FROM apby WHERE inv = ? ORDER BY tanggal ASC";
    $stb = $conn->prepare($sql_bayar);
    $stb->bind_param("s", $inv_no);
    $stb->execute();
    $res_bayar = $stb->get_result();
    $sisa = $sisa_awal_inv;
    while ($b = $res_bayar->fetch_assoc()) {
        $tgl_bayar = $b['tanggal'];
        $dibayar = $b['bayar1'];
        $sisa -= $dibayar;
        $sheet->setCellValue("A{$rowNum}", $tgl_bayar);
        $sheet->setCellValue("B{$rowNum}", $inv_no);
        $sheet->setCellValue("C{$rowNum}", 0);
        $sheet->setCellValue("D{$rowNum}", 0);
        $sheet->setCellValue("E{$rowNum}", $dibayar);
        $sheet->setCellValue("F{$rowNum}", $sisa);
        $sheet->setCellValue("G{$rowNum}", '-');
        $rowNum++;
    }
    $stb->close();

    $total_inv += $nilai_inv;
    $total_pph += $pph23;
    $total_akhir_sisa += $akhir_sisa_inv;
}

// Footer
$rowNum++;
$sheet->setCellValue("A{$rowNum}", "TOTAL");
$sheet->mergeCells("A{$rowNum}:B{$rowNum}");
$sheet->setCellValue("C{$rowNum}", $total_inv);
$sheet->setCellValue("D{$rowNum}", $total_pph);
$sheet->setCellValue("G{$rowNum}", $total_akhir_sisa);
$sheet->getStyle("A{$rowNum}:I{$rowNum}")->getFont()->setBold(true);

// Format angka
$sheet->getStyle("C8:I{$rowNum}")
      ->getNumberFormat()
      ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

// Auto width
foreach (range('A','I') as $col)
    $sheet->getColumnDimension($col)->setAutoSize(true);

// --- Output ---
$filename = "SOA_{$customer_name}_" . date('YmdHis') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
