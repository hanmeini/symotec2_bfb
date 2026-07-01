<?php
require 'config1.php';

// Daftar menu baru (batch 2)
$new_menus = [
    [
        'nama_menu' => 'Report Uang Masuk',
        'url' => 'pembayaranreport.php',
        'icon' => 'fa-money-bill-wave',
    ],
    [
        'nama_menu' => 'Stock & Harga',
        'url' => 'stockb.php',
        'icon' => 'fa-boxes',
    ],
    [
        'nama_menu' => 'Report Barang Masuk',
        'url' => 'reportin.php',
        'icon' => 'fa-arrow-right-to-bracket',
    ]
];

foreach ($new_menus as $menu) {
    try {
        $stmt = $pdo->prepare("SELECT id_menu FROM menu WHERE file_menu = ? LIMIT 1");
        $stmt->execute([$menu['url']]);
        
        if ($stmt->rowCount() == 0) {
            $stmt_max = $pdo->query("SELECT MAX(id_menu) as max_id FROM menu");
            $max_id = $stmt_max->fetch(PDO::FETCH_ASSOC)['max_id'];
            $next_id = $max_id ? $max_id + 1 : 1;
            
            $in = $pdo->prepare("INSERT INTO menu (id_menu, nama_menu, file_menu, icon_menu, urutan) VALUES (?, ?, ?, ?, ?)");
            $in->execute([$next_id, $menu['nama_menu'], $menu['url'], $menu['icon'], $next_id]);
            
            echo "Inserted {$menu['nama_menu']} with ID $next_id\n";
        } else {
            echo "Menu {$menu['nama_menu']} sudah ada.\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
echo "Selesai.\n";
