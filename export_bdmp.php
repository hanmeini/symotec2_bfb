<?php
// Tampilkan semua error
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload dan konfigurasi
require 'vendor/autoload.php';

require_once 'config1.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Koneksi database





// Ambil parameter
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('m');
$tahun = isset($_GET['tahun']) ? $_GET['tahun'] : date('Y');
$cari = isset($_GET['cari']) ? $_GET['cari'] : '';

if ($bulan == "tahunan") {
    $periode_start = "$tahun-01-01";
    $periode_end = "$tahun-12-31";
    $cutoff_sebelumnya = date("Y-12-31", strtotime(($tahun - 1) . "-12-31"));
    $label_periode = "Tahunan $tahun";
} else {
    $bulan = str_pad($bulan, 2, "0", STR_PAD_LEFT);
    $periode_start = "$tahun-$bulan-01";
    $periode_end = date("Y-m-t", strtotime($periode_start));
    $cutoff_sebelumnya = ($bulan == "01") ?
        date("Y-12-31", strtotime(($tahun - 1) . "-12-31")) :
        date("Y-m-t", strtotime("-1 month", strtotime($periode_start)));
    $label_periode = "Periode " . date("F Y", strtotime($periode_start));
}

// Buat spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Laporan BDM');

// Judul
$sheet->setCellValue('A1', "Laporan BDM dan Penyusutan - $label_periode");
$sheet->mergeCells('A1:I1');

// Header tabel
$headers = ['No', 'Kode', 'Nama', 'Tanggal Perolehan', 'Harga Perolehan', 'Nilai Buku Awal Periode', 'Akumulasi Penyusutan', 'Penyusutan Periode', 'Nilai Buku Akhir'];
$sheet->fromArray($headers, null, 'A3');

// Ambil data aset (tanpa get_result)
$sql = "SELECT kode, nama, tanggal, beli FROM bdm WHERE kode LIKE ? OR nama LIKE ? ORDER BY kode ASC";
$stmt = $conn->prepare($sql);
$like_cari = "%$cari%";
$stmt->bind_param("ss", $like_cari, $like_cari);
$stmt->execute();
$stmt->bind_result($kode, $nama, $tanggal, $beli);

// Buffer semua data aset dulu ke array
$data_aset = [];
while ($stmt->fetch()) {
    $data_aset[] = [
        'kode' => $kode,
        'nama' => $nama,
        'tanggal' => $tanggal,
        'beli' => (float) $beli
    ];
}


// Sekarang lakukan loop data_aset
$row = 4;
$no = 1;
$total_harga = $total_akumulasi = $total_akhir = 0;

foreach ($data_aset as $data) {
    $kode = $data['kode'];
    $nama = $data['nama'];
    $tanggal = $data['tanggal'];
    $beli = $data['beli'];

    // Akumulasi sampai akhir periode
    $stmt1 = $conn->prepare("SELECT SUM(susut) FROM susut WHERE kode = ? AND tanggal <= ?");
    $stmt1->bind_param("ss", $kode, $periode_end);
    $stmt1->execute();
    $stmt1->bind_result($akumulasi);
    $stmt1->fetch();
    $stmt1->close();
    $akumulasi = $akumulasi ?: 0;

    // Akumulasi sampai cutoff sebelumnya
    $stmt2 = $conn->prepare("SELECT SUM(susut) FROM susut WHERE kode = ? AND tanggal <= ?");
    $stmt2->bind_param("ss", $kode, $cutoff_sebelumnya);
    $stmt2->execute();
    $stmt2->bind_result($akum_prev);
    $stmt2->fetch();
    $stmt2->close();
    $akum_prev = $akum_prev ?: 0;

    // Hitung nilai buku
    $nilaibukuawal = $beli - $akum_prev;
    $penyusutan_periode = $akumulasi - $akum_prev;
    $nilaibukuakhir = $beli - $akumulasi;

    // Masukkan ke spreadsheet
    $sheet->fromArray([
        $no,
        $kode,
        $nama,
        $tanggal,
        $beli,
        $nilaibukuawal,
        $akumulasi,
        $penyusutan_periode,
        $nilaibukuakhir
    ], null, 'A' . $row);

    $total_harga += $beli;
    $total_akumulasi += $akumulasi;
    $total_akhir += $nilaibukuakhir;

    $no++;
    $row++;
}

$stmt->close();

// Baris total
$sheet->setCellValue("A{$row}", "TOTAL");
$sheet->mergeCells("A{$row}:D{$row}");
$sheet->setCellValue("E{$row}", $total_harga);
$sheet->setCellValue("G{$row}", $total_akumulasi);
$sheet->setCellValue("I{$row}", $total_akhir);

// Format angka
foreach (range('E', 'I') as $col) {
    $sheet->getStyle("{$col}4:{$col}{$row}")->getNumberFormat()
        ->setFormatCode('#,##0');
}

// Output file
$filename = "Laporan_BDM_{$tahun}_{$bulan}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
