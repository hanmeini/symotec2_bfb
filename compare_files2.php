<?php
$dir_mkb = 'c:/Users/X1 CARBON/OneDrive/Desktop/symotec2_mkb';
$dir_bfb = 'c:/Users/X1 CARBON/Downloads/symotec2_bfb';

$files_to_check = [
    'save_jurnalbank.php', 'save_jurnalkas.php', 'export_jurnalall.php'
];

echo "Comparing MORE files...\n";

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
