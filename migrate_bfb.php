<?php
require_once 'config.php';
$conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
if($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$queries = [
    // Table b additions
    "ALTER TABLE b ADD COLUMN kategori VARCHAR(100) AFTER nama_b",
    "ALTER TABLE b ADD COLUMN satuan_besar VARCHAR(50) AFTER kategori",
    "ALTER TABLE b ADD COLUMN rasio_besar DECIMAL(10,2) DEFAULT 0 AFTER satuan_besar",
    "ALTER TABLE b ADD COLUMN satuan_tengah VARCHAR(50) AFTER rasio_besar",
    "ALTER TABLE b ADD COLUMN rasio_tengah DECIMAL(10,2) DEFAULT 0 AFTER satuan_tengah",
    "ALTER TABLE b ADD COLUMN satuan_kecil VARCHAR(50) AFTER rasio_tengah",
    "ALTER TABLE b ADD COLUMN pemasok VARCHAR(100)",
    "ALTER TABLE b ADD COLUMN barcode VARCHAR(100)",

    // Table sup additions
    "ALTER TABLE sup ADD COLUMN kategori VARCHAR(100) AFTER nama",
    "ALTER TABLE sup ADD COLUMN kontak VARCHAR(100)",
    "ALTER TABLE sup ADD COLUMN telp VARCHAR(50)",
    "ALTER TABLE sup ADD COLUMN email VARCHAR(100)",
    "ALTER TABLE sup ADD COLUMN syarat_bayar VARCHAR(50)"
];

foreach ($queries as $q) {
    if ($conn->query($q) === TRUE) {
        echo "Success: $q\n";
    } else {
        echo "Failed/Ignored: $q - " . $conn->error . "\n";
    }
}
?>
