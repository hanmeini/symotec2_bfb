<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

// Tambah kolom format_qty di stock
$conn->query("ALTER TABLE stock ADD COLUMN format_qty VARCHAR(50) DEFAULT ''");
if($conn->error) echo "Error stock: " . $conn->error . "\n";
else echo "Kolom format_qty berhasil ditambahkan ke tabel stock.\n";

// Tambah kolom format_qty di transaksiho1
$conn->query("ALTER TABLE transaksiho1 ADD COLUMN format_qty VARCHAR(50) DEFAULT ''");
if($conn->error) echo "Error transaksiho1: " . $conn->error . "\n";
else echo "Kolom format_qty berhasil ditambahkan ke tabel transaksiho1.\n";

?>
