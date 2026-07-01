<?php
// functions_stock.php

function recalculate_stock_history($conn, $kodeb) {
    // Ambil semua histori stock untuk kodeb ini, urutkan berdasarkan tanggal dan ids
    $stmt = $conn->prepare("SELECT ids, jumlah_m, harga_m, jumlah_k FROM stock WHERE kodeb = ? ORDER BY tanggal_transaksi ASC, ids ASC");
    $stmt->bind_param("s", $kodeb);
    $stmt->execute();
    $result = $stmt->get_result();

    $s_current = 0.0;
    $r_current = 0.0;

    $update_stmt = $conn->prepare("UPDATE stock SET s = ?, r = ?, hpp = ? WHERE ids = ?");

    while ($row = $result->fetch_assoc()) {
        $ids = $row['ids'];
        $jm = (float)$row['jumlah_m'];
        $hm = (float)$row['harga_m'];
        $jk = (float)$row['jumlah_k'];
        
        $hpp = 0.0;

        if ($jm > 0) {
            // Masuk
            $s_baru = $s_current + $jm;
            if ($s_baru > 0) {
                $r_baru = (($s_current * $r_current) + ($jm * $hm)) / $s_baru;
            } else {
                $r_baru = $r_current;
            }
            $s_current = $s_baru;
            $r_current = $r_baru;
        }

        if ($jk > 0) {
            // Keluar
            $s_baru = $s_current - $jk;
            $hpp = $r_current; // HPP diambil dari harga rata-rata saat ini
            
            $s_current = $s_baru;
            // r_current tetap
        }

        // Update row
        $update_stmt->bind_param("dddi", $s_current, $r_current, $hpp, $ids);
        $update_stmt->execute();
    }

    $stmt->close();
    $update_stmt->close();
}
?>
