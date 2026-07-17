<?php
require 'config1.php';

echo "Memulai migrasi database BFB...\n";

// 1. Tambah kolom no_rpc di tabel penjualanHO1
try {
    $conn->query("ALTER TABLE penjualanHO1 ADD COLUMN no_rpc VARCHAR(50) DEFAULT NULL");
    echo "Kolom no_rpc berhasil ditambahkan.\n";
} catch (Exception $e) {
    echo "Info: Kolom no_rpc mungkin sudah ada atau terjadi error: " . $e->getMessage() . "\n";
}

// 2. Buat tabel rpc_header
try {
    $query = "
    CREATE TABLE IF NOT EXISTS rpc_header (
        no_rpc VARCHAR(50) PRIMARY KEY,
        tanggal_rpc DATETIME,
        user_pembuat VARCHAR(50),
        supir VARCHAR(100) DEFAULT NULL,
        plat_nomor VARCHAR(50) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    $conn->query($query);
    echo "Tabel rpc_header siap.\n";
} catch (Exception $e) {
    echo "Error tabel rpc_header: " . $e->getMessage() . "\n";
}

// 3. Tambahkan menu baru ke tabel menu
try {
    $conn->query("INSERT INTO menu (nama_menu, file_menu, icon_menu, urutan, aktif) VALUES ('Verifikasi Order', 'verifikasi_order.php', 'fas fa-check-circle', 5, 1)");
    $conn->query("INSERT INTO menu (nama_menu, file_menu, icon_menu, urutan, aktif) VALUES ('Rekap SJ (RPC)', 'rpc.php', 'fas fa-truck-loading', 6, 1)");
    echo "Menu berhasil ditambahkan.\n";
} catch (Exception $e) {
    echo "Info: Menu mungkin sudah ada atau terjadi error: " . $e->getMessage() . "\n";
}

echo "Migrasi Selesai.\n";
?>
