<?php
require_once 'config1.php';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Ambil account_code dulu untuk mengecek apakah sudah terpakai di jurnal
    $stmt = $conn->prepare("SELECT account_code, account_name FROM coa WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $acc_code = $row['account_code'];
        
        // Cek apakah account_code sudah ada di tabel jurnal
        $stmt_check = $conn->prepare("SELECT id FROM jurnal WHERE coa = ? LIMIT 1");
        $stmt_check->bind_param("s", $acc_code);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            echo "<script>alert('Gagal Hapus: Akun {$acc_code} ({$row['account_name']}) tidak bisa dihapus karena sudah memiliki riwayat transaksi di Jurnal!'); window.location.href='coa.php';</script>";
        } else {
            // Cek apakah akun ini adalah parent dari akun lain
            $stmt_parent = $conn->prepare("SELECT id FROM coa WHERE parent_account = ? LIMIT 1");
            $stmt_parent->bind_param("s", $acc_code);
            $stmt_parent->execute();
            $stmt_parent->store_result();
            
            if ($stmt_parent->num_rows > 0) {
                echo "<script>alert('Gagal Hapus: Akun {$acc_code} ({$row['account_name']}) masih menjadi Parent Account untuk akun lain!'); window.location.href='coa.php';</script>";
            } else {
                // Hapus jika belum dipakai dan bukan parent
                $stmt_del = $conn->prepare("DELETE FROM coa WHERE id = ?");
                $stmt_del->bind_param("i", $id);
                if ($stmt_del->execute()) {
                    echo "<script>alert('Berhasil: COA berhasil dihapus!'); window.location.href='coa.php';</script>";
                } else {
                    echo "<script>alert('Gagal Hapus: Terjadi kesalahan pada database.'); window.location.href='coa.php';</script>";
                }
                $stmt_del->close();
            }
            $stmt_parent->close();
        }
        $stmt_check->close();
    } else {
        echo "<script>alert('Gagal: Data COA tidak ditemukan!'); window.location.href='coa.php';</script>";
    }
    $stmt->close();
} else {
    echo "<script>alert('Gagal: Parameter ID tidak valid!'); window.location.href='coa.php';</script>";
}

$conn->close();
?>
