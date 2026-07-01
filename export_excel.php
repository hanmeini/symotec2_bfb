<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once "config.php";

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ============================
// Koneksi database
// ============================




// ============================
// Ambil data karyawan
// ============================
$sql = "SELECT no_staff, nama, LP, dept, jabatan, tgl_masuk, tgl_lahir,
               alamat, nik, kk, status_menikah, jumlah_tanggungan,
               no_telp, pendidikan, nama_darurat, no_darurat,
               bpjs_kes, bpjs_tk, gaji_pokok, upah_lembur, aktive
        FROM data_karyawan ORDER BY no_staff ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$stmt->store_result();

$stmt->bind_result(
    $no_staff, $nama, $LP, $dept_id, $jab_id, $tgl_masuk, $tgl_lahir,
    $alamat, $nik, $kk, $status_menikah, $jumlah_tanggungan,
    $no_telp, $pendidikan, $nama_darurat, $no_darurat,
    $bpjs_kes, $bpjs_tk, $gaji_pokok, $upah_lembur, $aktive
);

// ============================
// Fungsi ambil departemen
// ============================
function getDepartemen($conn, $id){
    $sql = "SELECT nama_bagian FROM bagian WHERE id=? LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $st->store_result();
    $st->bind_result($nama);
    $st->fetch();
    $st->close();
    return $nama;
}

// ============================
// Fungsi ambil jabatan
// ============================
function getJabatan($conn, $id){
    $sql = "SELECT jabatan FROM jabatan WHERE idj=? LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id);
    $st->execute();
    $st->store_result();
    $st->bind_result($nama);
    $st->fetch();
    $st->close();
    return $nama;
}

// ============================
// Buat Excel
// ============================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Data Karyawan");

// Header kolom
$header = [
    "NO STAFF", "NAMA", "LP", "DEPARTEMEN", "JABATAN",
    "TGL MASUK", "TGL LAHIR", "ALAMAT", "NIK", "KK",
    "STATUS MENIKAH", "TANGGUNGAN", "NO TELP",
    "PENDIDIKAN", "NAMA DARURAT", "NO DARURAT",
    "BPJS KES", "BPJS TK", "GAJI POKOK", "UPAH LEMBUR",
    "AKTIVE"
];

// Isi header
$col = 'A';
foreach ($header as $h) {
    $sheet->setCellValue($col . '1', $h);
    $col++;
}

// Style header
$sheet->getStyle("A1:U1")->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F618D']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
]);

// ============================
// Isi data
// ============================
$row = 2;

while ($stmt->fetch()) {

    $departemen = getDepartemen($conn, $dept_id);
    $jabatan    = getJabatan($conn, $jab_id);

    $data = [
        $no_staff,
        $nama,
        $LP,
        $departemen,
        $jabatan,
        $tgl_masuk,
        $tgl_lahir,
        $alamat,
        $nik,
        $kk,
        $status_menikah,
        $jumlah_tanggungan,
        $no_telp,
        $pendidikan,
        $nama_darurat,
        $no_darurat,
        $bpjs_kes,
        $bpjs_tk,
        $gaji_pokok,
        $upah_lembur,
        $aktive
    ];

    $col = 'A';
    foreach ($data as $d) {
        $sheet->setCellValue($col . $row, $d);
        $col++;
    }

    $row++;
}

// Auto-size kolom
foreach (range('A', 'U') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// ============================
// Output Excel
// ============================
$filename = "data_karyawan_" . date("Ymd_His") . ".xlsx";

header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Cache-Control: max-age=0");

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
