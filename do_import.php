<?php
$_SERVER['REQUEST_METHOD'] = 'POST';

// Mock for Supplier
$_POST['import_supplier'] = '1';
$_FILES['file_supplier'] = [
    'tmp_name' => __DIR__ . '/temp/daftar-pemasok.xlsx',
    'error' => 0
];
ob_start();
require 'import_master.php';
$output = ob_get_clean();
if (strpos($output, 'berhasil diimport') !== false) {
    echo "Supplier import success!\n";
} else {
    echo "Supplier import failed!\n";
}

// Mock for Barang
$_POST = ['import_barang' => '1'];
$_FILES['file_barang'] = [
    'tmp_name' => __DIR__ . '/temp/daftar-barang.xlsx',
    'error' => 0
];
ob_start();
require 'import_master.php';
$output2 = ob_get_clean();
if (strpos($output2, 'berhasil diimport') !== false) {
    echo "Barang import success!\n";
} else {
    echo "Barang import failed!\n";
}
?>
