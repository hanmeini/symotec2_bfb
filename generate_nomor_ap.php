<?php
function generateNomorAP($conn, $tipe_dokumen, $tanggal = null) {
    if (!$tanggal) {
        $tanggal = date('Y-m-d');
    }
    
    $tahun_lengkap = date('Y', strtotime($tanggal)); // 4 digit (2026)
    $tahun = date('y', strtotime($tanggal)); // format 2 digit: 26 (tetap untuk DB)
    $bulan = date('m', strtotime($tanggal)); // format 2 digit: 07
    $bulan_int = (int)date('n', strtotime($tanggal));

    $bulan_romawi = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
    ];
    $romawi = $bulan_romawi[$bulan_int];

    // Ambil nomor terakhir untuk tipe, tahun, dan bulan ini
    $sql_cek = "SELECT nomor_terakhir FROM master_nomor_dokumen WHERE kode_dokumen = ? AND tahun = ? AND bulan = ? FOR UPDATE";
    $stmt_cek = $conn->prepare($sql_cek);
    $stmt_cek->bind_param("sss", $tipe_dokumen, $tahun, $bulan);
    $stmt_cek->execute();
    $result = $stmt_cek->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nomor_baru = $row['nomor_terakhir'] + 1;
        
        $sql_update = "UPDATE master_nomor_dokumen SET nomor_terakhir = ? WHERE kode_dokumen = ? AND tahun = ? AND bulan = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("isss", $nomor_baru, $tipe_dokumen, $tahun, $bulan);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $nomor_baru = 1;
        $sql_insert = "INSERT INTO master_nomor_dokumen (kode_dokumen, tahun, bulan, nomor_terakhir) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("sssi", $tipe_dokumen, $tahun, $bulan, $nomor_baru);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    
    $stmt_cek->close();

    // Format: 0001/PREFIX/ROMAN_MONTH/YYYY
    $urut_str = str_pad($nomor_baru, 4, '0', STR_PAD_LEFT);
    return $urut_str . '/' . $tipe_dokumen . '/' . $romawi . '/' . $tahun_lengkap;
}
?>
