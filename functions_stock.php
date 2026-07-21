<?php
// functions_stock.php

function recalculate_stock_history($conn, $kodeb) {
    // Ambil semua histori stock untuk kodeb ini, urutkan berdasarkan tanggal dan ids
    $stmt = $conn->prepare("SELECT s.ids, s.jumlah_m, s.harga_m, s.hargat_m, s.jumlah_k, s.sj, 
                                   p.no_rpc 
                            FROM stock s 
                            LEFT JOIN penjualanho1 p ON s.sj = p.inv 
                            WHERE s.kodeb = ? 
                            ORDER BY s.tanggal_transaksi ASC, s.ids ASC");
    $stmt->bind_param("s", $kodeb);
    $stmt->execute();
    $result = $stmt->get_result();

    $s_current = 0.0;
    $sg_current = 0.0;
    $r_current = 0.0;

    $update_stmt = $conn->prepare("UPDATE stock SET s = ?, sg = ?, r = ?, hpp = ? WHERE ids = ?");

    while ($row = $result->fetch_assoc()) {
        $ids = $row['ids'];
        $jm = (float)$row['jumlah_m'];
        $hm = (float)$row['harga_m'];
        $htm = (float)$row['hargat_m'];
        $jk = (float)$row['jumlah_k'];
        
        $hpp = 0.0;

        if ($jm > 0) {
            // Masuk
            $s_baru = $s_current + $jm;
            $sg_baru = $sg_current + $jm; // Barang masuk langsung nambah di gudang
            if ($s_baru > 0) {
                // Harga total pembelanjaan saat ini. Jika hargat_m 0, fallback ke jm * hm
                $cost_current = ($htm > 0) ? $htm : ($jm * $hm);
                $r_baru = (($s_current * $r_current) + $cost_current) / $s_baru;
            } else {
                $r_baru = $r_current;
            }
            $s_current = $s_baru;
            $sg_current = $sg_baru;
            $r_current = $r_baru;
        }

        if ($jk > 0) {
            // Keluar
            
            // Stok sistem (s) berkurang HANYA JIKA RPC SUDAH DICETAK
            if (!empty($row['no_rpc'])) {
                $s_baru = $s_current - $jk;
            } else {
                $s_baru = $s_current; // Belum cetak RPC, stok sistem tetap
            }
            
            // Stok gudang (sg) berkurang HANYA JIKA SUDAH VERIFIKASI ADMIN (SJ diterbitkan)
            // Bisa dicek dari nomor $row['sj'] yang diawali 'SJ' atau 'INV' (bukan lagi 'ORD')
            $sj_val = $row['sj'] ?? '';
            if (strpos($sj_val, 'SJ') !== false || strpos($sj_val, 'INV') !== false) {
                $sg_baru = $sg_current - $jk;
            } else {
                $sg_baru = $sg_current; // Masih berupa pesanan POS (ORD), stok gudang tetap
            }

            $hpp = $r_current; // HPP diambil dari harga rata-rata saat ini
            
            $s_current = $s_baru;
            $sg_current = $sg_baru;
            // r_current tetap
        }

        // Update row
        $update_stmt->bind_param("ddddi", $s_current, $sg_current, $r_current, $hpp, $ids);
        $update_stmt->execute();
    }

    $stmt->close();
    $update_stmt->close();

    // Update dpp (Harga Rata-Rata Terakhir) di tabel b
    if ($r_current > 0) {
        $stmt_b = $conn->prepare("UPDATE b SET dpp = ? WHERE kode_b = ?");
        $stmt_b->bind_param("ds", $r_current, $kodeb);
        $stmt_b->execute();
        $stmt_b->close();
    }
}
?>
