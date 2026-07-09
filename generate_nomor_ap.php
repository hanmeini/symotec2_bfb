<?php
function generateNomorAP($conn, $tipe_dokumen, $tanggal = null) {
    if (!$tanggal) {
        $tanggal = date('Y-m-d');
    }
    
    $tahun = date('y', strtotime($tanggal)); // format 2 digit: 26
    $bulan = date('m', strtotime($tanggal)); // format 2 digit: 07

    // Ambil nomor terakhir untuk tipe, tahun, dan bulan ini
    $sql_cek = "SELECT nomor_terakhir FROM master_nomor_dokumen WHERE tipe_dokumen = ? AND tahun = ? AND bulan = ? FOR UPDATE";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("sss", $tipe_dokumen, $tahun, $bulan);
    $stmt_cek->execute();
    $result = $stmt_cek->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nomor_baru = $row['nomor_terakhir'] + 1;
        
        $sql_update = "UPDATE master_nomor_dokumen SET nomor_terakhir = ? WHERE tipe_dokumen = ? AND tahun = ? AND bulan = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("isss", $nomor_baru, $tipe_dokumen, $tahun, $bulan);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $nomor_baru = 1;
        $sql_insert = "INSERT INTO master_nomor_dokumen (tipe_dokumen, tahun, bulan, nomor_terakhir) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("sssi", $tipe_dokumen, $tahun, $bulan, $nomor_baru);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    $stmt_cek->close();

    // Format: BOS-2607-0001
    return sprintf("%s-%s%s-%04d", $tipe_dokumen, $tahun, $bulan, $nomor_baru);
}
?>
