<?php
require_once 'config1.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    if (empty($account_code) || empty($account_name)) {
        echo "<script>alert('Account Code dan Account Name harus diisi!'); window.history.back();</script>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO coa (account_code, account_name, layer, posisi, dc, open, parent_account) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssds", $account_code, $account_name, $layer, $posisi, $dc, $open, $parent_account);

    if ($stmt->execute()) {
        echo "<script>alert('COA berhasil disimpan!'); window.location.href = 'coa.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan COA: " . $conn->error . "'); window.history.back();</script>";
    }
    $stmt->close();
}
?>
