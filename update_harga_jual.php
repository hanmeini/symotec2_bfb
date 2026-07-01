<?php
require_once 'config1.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_b = $_POST['kode_b'] ?? '';
    $old_harga = (float)($_POST['old_harga'] ?? 0);
    $new_harga = (float)($_POST['new_harga'] ?? 0);

    if (empty($kode_b)) {
        echo "<script>alert('Kode barang tidak valid!'); window.location.href='barang.php';</script>";
        exit;
    }

    // Aturan Bisnis: Perubahan harga hanya bisa naik
    if ($new_harga < $old_harga) {
        echo "<script>
            alert('PERINGATAN: Perubahan harga DITOLAK! Aturan menetapkan bahwa Harga Jual hanya bisa dinaikkan (tidak boleh lebih murah dari harga saat ini yaitu " . number_format($old_harga, 2, ',', '.') . ").');
            window.location.href='barang.php';
        </script>";
        exit;
    }

    // Jika lolos validasi, update harga_b dan hargat_b (Harga Jual Include)
    // Asumsi hargat_b = harga jual include, maka harga_b (DPP) = hargat_b / 1.11
    $harga_b = $new_harga / 1.11;
    $ppn_b = $new_harga - $harga_b;

    $stmt = $conn->prepare("UPDATE b SET hargat_b = ?, harga_b = ?, ppn_b = ? WHERE kode_b = ?");
    $stmt->bind_param("ddds", $new_harga, $harga_b, $ppn_b, $kode_b);
    
    if ($stmt->execute()) {
        echo "<script>alert('Sukses: Harga Jual untuk barang " . htmlspecialchars($kode_b) . " berhasil dinaikkan menjadi " . number_format($new_harga, 2, ',', '.') . "!'); window.location.href='barang.php';</script>";
    } else {
        echo "<script>alert('Error: Gagal mengupdate harga di database.'); window.location.href='barang.php';</script>";
    }
    
    $stmt->close();
}
$conn->close();
?>
