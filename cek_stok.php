<?php
require_once 'config1.php';

$kode_b = $_GET['kode_b'] ?? '';
$id_gudang = isset($_GET['id_gudang']) ? (int)$_GET['id_gudang'] : 0;

if (empty($kode_b)) {
    echo json_encode(['total_stock' => 0]);
    exit;
}

// Ambil total stok dari tabel stock (Masuk - Keluar) KHUSUS di Gudang tersebut
$stmt = $conn->prepare("
    SELECT (COALESCE(SUM(jumlah_m), 0) - COALESCE(SUM(jumlah_k), 0)) AS total_stock 
    FROM stock 
    WHERE kodeb = ? AND id_gudang = ?
");
$stmt->bind_param("si", $kode_b, $id_gudang);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$total_stock = $row['total_stock'] ?? 0;

echo json_encode(['total_stock' => (float) $total_stock]);
$stmt->close();
?>
