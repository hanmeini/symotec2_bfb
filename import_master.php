<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';
require_once 'functions.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$conn = db_connect();
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['import_supplier']) && isset($_FILES['file_supplier']['tmp_name'])) {
        $file = $_FILES['file_supplier']['tmp_name'];
        if ($file) {
            try {
                $spreadsheet = IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $rowIterator = $sheet->getRowIterator(2); // Skip header
                $count = 0;
                foreach ($rowIterator as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $data = [];
                    foreach ($cellIterator as $cell) {
                        $val = $cell->getValue();
                        if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                            $val = $val->getPlainText();
                        }
                        $data[] = $val;
                    }
                    
                    if (empty($data[2])) continue; // No ID Pemasok
                    
                    $kode = $conn->real_escape_string((string)$data[2]);
                    $nama = $conn->real_escape_string((string)$data[3]);
                    $kategori = $conn->real_escape_string((string)$data[1]);
                    $kontak = $conn->real_escape_string((string)$data[4]);
                    $telp = $conn->real_escape_string((string)$data[5]);
                    $email = $conn->real_escape_string((string)$data[7]);
                    $syarat_bayar = $conn->real_escape_string((string)$data[11]);
                    $alamat = $conn->real_escape_string((string)$data[12]);
                    $npwp = $conn->real_escape_string((string)$data[23]);
                    
                    // Cek exist
                    $cek = $conn->query("SELECT id FROM sup WHERE kode='$kode'");
                    if ($cek->num_rows > 0) {
                        $conn->query("UPDATE sup SET nama='$nama', kategori='$kategori', kontak='$kontak', telp='$telp', email='$email', syarat_bayar='$syarat_bayar', alamat='$alamat', npwp='$npwp' WHERE kode='$kode'");
                    } else {
                        $conn->query("INSERT INTO sup (kode, nama, kategori, kontak, telp, email, syarat_bayar, alamat, npwp) VALUES ('$kode', '$nama', '$kategori', '$kontak', '$telp', '$email', '$syarat_bayar', '$alamat', '$npwp')");
                    }
                    $count++;
                }
                $msg = "<div class='alert alert-success'>$count Supplier berhasil diimport!</div>";
            } catch (Exception $e) {
                $msg = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    if (isset($_POST['import_barang']) && isset($_FILES['file_barang']['tmp_name'])) {
        $file = $_FILES['file_barang']['tmp_name'];
        if ($file) {
            try {
                $spreadsheet = IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();
                $rowIterator = $sheet->getRowIterator(2); // Skip header
                $count = 0;
                foreach ($rowIterator as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $data = [];
                    foreach ($cellIterator as $cell) {
                        $val = $cell->getValue();
                        if ($val instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                            $val = $val->getPlainText();
                        }
                        $data[] = $val;
                    }
                    
                    if (empty($data[2])) continue; // No Kode Barang
                    
                    $kode_b = $conn->real_escape_string((string)$data[2]);
                    $nama_b = $conn->real_escape_string((string)$data[3]);
                    $kategori = $conn->real_escape_string((string)$data[1]);
                    $jenis = $conn->real_escape_string((string)$data[4]);
                    
                    $satuan_kecil = $conn->real_escape_string((string)$data[5]);
                    
                    $s2 = $conn->real_escape_string((string)$data[6]);
                    $r2 = (float)$data[7];
                    $s3 = $conn->real_escape_string((string)$data[8]);
                    $r3 = (float)$data[9];
                    
                    // Susun rasio dari terbesar ke terkecil
                    $satuan_besar = ''; $rasio_besar = 0;
                    $satuan_tengah = ''; $rasio_tengah = 0;
                    
                    if ($r2 > $r3) {
                        $satuan_besar = $s2; $rasio_besar = $r2;
                        $satuan_tengah = $s3; $rasio_tengah = $r3;
                    } else {
                        $satuan_besar = $s3; $rasio_besar = $r3;
                        $satuan_tengah = $s2; $rasio_tengah = $r2;
                    }
                    
                    $barcode = $conn->real_escape_string((string)$data[15]);
                    $harga_b = (float)$data[23]; // Jual
                    $harga_m = (float)$data[32]; // Beli
                    $pemasok = $conn->real_escape_string((string)$data[28]);
                    $brand = $conn->real_escape_string((string)$data[29]);
                    
                    $cek = $conn->query("SELECT id FROM b WHERE kode_b='$kode_b'");
                    if ($cek->num_rows > 0) {
                        $conn->query("UPDATE b SET nama_b='$nama_b', kategori='$kategori', jenis='$jenis', satuan_kecil='$satuan_kecil', satuan_tengah='$satuan_tengah', rasio_tengah='$rasio_tengah', satuan_besar='$satuan_besar', rasio_besar='$rasio_besar', barcode='$barcode', harga_b='$harga_b', harga_m='$harga_m', pemasok='$pemasok', brand='$brand' WHERE kode_b='$kode_b'");
                    } else {
                        $conn->query("INSERT INTO b (kode_b, nama_b, kategori, jenis, satuan_kecil, satuan_tengah, rasio_tengah, satuan_besar, rasio_besar, barcode, harga_b, harga_m, pemasok, brand) VALUES ('$kode_b', '$nama_b', '$kategori', '$jenis', '$satuan_kecil', '$satuan_tengah', '$rasio_tengah', '$satuan_besar', '$rasio_besar', '$barcode', '$harga_b', '$harga_m', '$pemasok', '$brand')");
                    }
                    $count++;
                }
                $msg = "<div class='alert alert-success'>$count Barang berhasil diimport!</div>";
            } catch (Exception $e) {
                $msg = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Master Data - BFB</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h2 class="mb-4">Import Master Data BFB</h2>
        <?= $msg ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">Import Pemasok (Supplier)</div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label>Pilih File Excel Pemasok (daftar-pemasok.xlsx)</label>
                                <input type="file" name="file_supplier" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <button type="submit" name="import_supplier" class="btn btn-primary w-100">Upload & Import Pemasok</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-success text-white">Import Barang (Item)</div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label>Pilih File Excel Barang (daftar-barang.xlsx)</label>
                                <input type="file" name="file_barang" class="form-control" accept=".xlsx,.xls" required>
                            </div>
                            <button type="submit" name="import_barang" class="btn btn-success w-100">Upload & Import Barang</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="home.php" class="btn btn-secondary">&larr; Kembali ke Home</a>
        </div>
    </div>
</body>
</html>
