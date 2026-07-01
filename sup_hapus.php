<?php
require_once 'config1.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM sup WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Data Supplier berhasil dihapus!'); window.location.href='sup.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data Supplier.'); window.location.href='sup.php';</script>";
    }
    $stmt->close();
} else {
    header("Location: sup.php");
}
$conn->close();
?>
