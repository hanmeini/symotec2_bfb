<?php



require_once 'config1.php';



if (isset($_GET['kode_b'])) {
    $kode_b = $conn->real_escape_string($_GET['kode_b']);
    $id_gudang = isset($_GET['id_gudang']) ? (int)$_GET['id_gudang'] : 0;

    // Query untuk mencari barang dan menghitung sisa stok KHUSUS di Gudang tersebut
    $sql = "SELECT b.kode_b, b.nama_b, b.harga_retail AS harga_b, b.hargapack, b.qpack, b.ppn_b,
                   COALESCE(SUM(s.jumlah_m) - SUM(s.jumlah_k), 0) AS stok
            FROM b 
            LEFT JOIN stock s ON b.kode_b = s.kodeb AND s.id_gudang = $id_gudang
            WHERE b.kode_b LIKE '%$kode_b%' OR b.nama_b LIKE '%$kode_b%' 
            GROUP BY b.kode_b
            LIMIT 5";
    $result = $conn->query($sql);

    $barangList = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $barangList[] = [
                'kode_b' => $row['kode_b'],
                'nama_b' => $row['nama_b'],
                'harga_b' => $row['harga_b'],
                'hargapack' => $row['hargapack'] ?? 0,
                'qpack' => $row['qpack'] ?? 0,
                'ppn_b' => $row['ppn_b'] ?? 0,
                'stok' => $row['stok']
            ];
        }
    }

    // Return JSON ke frontend
    echo json_encode($barangList);
}

$conn->close();
?>
