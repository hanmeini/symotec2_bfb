<?php
require_once 'config1.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nama_b = $_POST['nama_b'] ?? '';
    $jenis = $_POST['jenis'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $dpp = isset($_POST['dpp']) ? (float)$_POST['dpp'] : 0.0;
    $ppn_beli_type = isset($_POST['ppn_beli_type']) ? (int)$_POST['ppn_beli_type'] : 11;
    $harga_jual_total = isset($_POST['harga']) ? (float)$_POST['harga'] : 0.0;
    $harga_retail = isset($_POST['harga_retail']) ? (float)$_POST['harga_retail'] : 0.0;
    $ppn_jual_type = isset($_POST['ppn_jual_type']) ? (int)$_POST['ppn_jual_type'] : 11;

    if ($id <= 0 || empty($nama_b) || empty($jenis) || empty($brand)) {
        echo "<script>alert('Semua data harus diisi dengan benar!'); window.history.back();</script>";
        exit;
    }

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

    // Update query
    $stmt = $conn->prepare("UPDATE b SET nama_b = ?, jenis = ?, brand = ?, harga_b = ?, ppn_b = ?, hargat_b = ?, dpp = ?, harga_m = ?, ppn_m = ?, hargat_m = ?, harga_retail = ? WHERE id = ?");
    $stmt->bind_param("sssddddddddi", $nama_b, $jenis, $brand, $harga_b, $ppn_b, $hargat_b, $dpp, $harga_m, $ppn_m, $hargat_m, $harga_retail, $id);

    if ($stmt->execute()) {
        echo "Data berhasil diperbarui!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();

// Redirect back to list
header("refresh:3; url=barang.php");
exit();
?>
