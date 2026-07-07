<?php
require_once 'config1.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int)($_POST['id'] ?? 0);
    $account_code = $_POST['account_code'] ?? '';
    $account_name = $_POST['account_name'] ?? '';
    $layer = $_POST['layer'] ?? '';
    $posisi = $_POST['posisi'] ?? '';
    $dc = $_POST['dc'] ?? '';
    $open = (float)($_POST['open'] ?? 0);
    $parent_account = $_POST['parent_account'] ?? '';

    if (empty($parent_account)) {
        $parent_account = null;
    }

    if ($id <= 0 || empty($account_code) || empty($account_name)) {
        echo "<script>alert('Data tidak lengkap!'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("UPDATE coa SET account_code=?, account_name=?, layer=?, posisi=?, dc=?, open=?, parent_account=? WHERE id=?");
    $stmt->bind_param("sssssdsi", $account_code, $account_name, $layer, $posisi, $dc, $open, $parent_account, $id);

    if ($stmt->execute()) {
        echo "<script>alert('COA berhasil diupdate!'); window.location.href = 'coa.php';</script>";
    } else {
        echo "<script>alert('Gagal mengupdate COA: " . $conn->error . "'); window.history.back();</script>";
    }
    $stmt->close();
}
?>
