<?php
require_once 'config1.php';

// Ambil data dari form
$kode_b = $_POST['kode_b'];
$nama_b = $_POST['nama_b'];
$jenis = $_POST['jenis'];
$brand = $_POST['brand'];
$dpp = (float)$_POST['dpp'];
$ppn_beli_type = (int)$_POST['ppn_beli_type'];
$harga_jual_total = (float)$_POST['harga'];
$harga_retail = (float)($_POST['harga_retail'] ?? 0);
$ppn_jual_type = (int)$_POST['ppn_jual_type'];

// Hitung PPN & DPP Jual
if ($ppn_jual_type === 11) {
    $harga_b = $harga_jual_total / 1.11;
    $ppn_b = $harga_jual_total - $harga_b;
} else {
    $harga_b = $harga_jual_total;
    $ppn_b = 0.0;
}
$hargat_b = $harga_jual_total;

// Hitung PPN & DPP Beli
if ($ppn_beli_type === 11) {
    $harga_m = $dpp / 1.11;
    $ppn_m = $dpp - $harga_m;
} else {
    $harga_m = $dpp;
    $ppn_m = 0.0;
}
$hargat_m = $dpp;

// Tambahan satuan & rasio
$satuan_kecil = 'Pcs';
$satuan_tengah = 'Lusin';
$satuan_besar = 'Box';
$rasio_tengah = (float)$_POST['rasio_tengah'];
$rasio_besar = (float)$_POST['rasio_besar'];

// Persiapkan query untuk menyimpan data
$stmt = $conn->prepare("INSERT INTO b (kode_b, nama_b, jenis, brand, harga_b, ppn_b, hargat_b, dpp, harga_m, ppn_m, hargat_m, harga_retail, satuan_kecil, satuan_tengah, rasio_tengah, satuan_besar, rasio_besar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssddddddddssdsd", $kode_b, $nama_b, $jenis, $brand, $harga_b, $ppn_b, $hargat_b, $dpp, $harga_m, $ppn_m, $hargat_m, $harga_retail, $satuan_kecil, $satuan_tengah, $rasio_tengah, $satuan_besar, $rasio_besar);

// Eksekusi query
if ($stmt->execute()) {
    echo "Data berhasil disimpan!";
} else {
    echo "Error: " . $stmt->error;
}

// Menutup koneksi
$stmt->close();
$conn->close();

// Redirect kembali ke halaman sebelumnya setelah 3 detik
header("refresh:1; url=barang.php");
exit();
?>
