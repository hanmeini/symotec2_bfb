<?php
$dir_mkb = 'c:/Users/X1 CARBON/OneDrive/Desktop/symotec2_mkb';
$dir_bfb = 'c:/Users/X1 CARBON/Downloads/symotec2_bfb';

$files_to_check = [
    // Pembelian
    'beli.php', 'save_beli.php', 'sjbeli.php', 'save_sjbeli.php', 'retur_pembelian.php', 'save_retur_pembelian.php',
    
    // Pelunasan AP
    'ap.php', 'apt.php', 'apb.php', 'apc.php', 
    'savein_apt.php', 'savein_apb.php', 'savein_apc.php',
    'ap_bayar.php', 'ap_titipan.php',
    
    // POS / Penjualan
    'pos.php', 'save_pos.php', 'penjualan.php', 'save_penjualan.php',
    'retur_penjualan.php', 'save_retur_penjualan.php',
    
    // Pelunasan AR
    'ar.php', 'art.php', 'arb.php', 'arc.php',
    'savein_art.php', 'savein_arb.php', 'savein_arc.php',
    'ar_bayar.php', 'ar_titipan.php',
    
    // Jurnal
    'jurnal.php', 'save_jurnal.php', 'cetak_jurnal.php', 'cetak_jurnal_sementara.php',
    'aprovekas.php', 'save_aprovekas.php'
];

echo "Comparing MKB vs BFB...\n";

foreach ($files_to_check as $file) {
    $path_mkb = $dir_mkb . '/' . $file;
    $path_bfb = $dir_bfb . '/' . $file;
    
    $mkb_exists = file_exists($path_mkb);
    $bfb_exists = file_exists($path_bfb);
    
    if ($mkb_exists && $bfb_exists) {
        $hash_mkb = md5_file($path_mkb);
        $hash_bfb = md5_file($path_bfb);
        if ($hash_mkb !== $hash_bfb) {
            echo "DIFFERENT: $file\n";
        } else {
            echo "SAME:      $file\n";
        }
    } else if ($mkb_exists && !$bfb_exists) {
        echo "MISSING IN BFB: $file\n";
    } else if (!$mkb_exists && $bfb_exists) {
        echo "MISSING IN MKB: $file\n";
    }
}
?>
