<?php
require_once 'config1.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("DELETE FROM cust WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        echo "<script>alert('Data Customer berhasil dihapus!'); window.location.href='cust.php';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data Customer.'); window.location.href='cust.php';</script>";
    }
    $stmt->close();
} else {
    header("Location: cust.php");
}
$conn->close();
?>
