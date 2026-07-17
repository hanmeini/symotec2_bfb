<?php
require_once 'config1.php';
session_start();

if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_b = $_POST['kode_b'] ?? '';
    $harga_retail = $_POST['harga_retail'] ?? 0;
    
    if (!empty($kode_b)) {
        $stmt = $conn->prepare("UPDATE b SET harga_retail = ? WHERE kode_b = ?");
        $stmt->bind_param("ds", $harga_retail, $kode_b);
        $stmt->execute();
        $stmt->close();
    }
}
echo "<script>alert('Harga Retail diupdate!'); window.location.href='barang.php';</script>";
exit();
?>
