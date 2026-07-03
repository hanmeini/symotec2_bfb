<?php
require 'config1.php';
$cols = [
    'jurnal_sementara' => 'VARCHAR(50) NULL',
    'account_name' => 'VARCHAR(255) NULL',
    'kode_booking' => 'VARCHAR(50) NULL',
    'supcust' => 'VARCHAR(50) NULL'
];
foreach ($cols as $col => $def) {
    $res = mysqli_query($conn, 'SHOW COLUMNS FROM jurnal LIKE \''.$col.'\'');
    if (mysqli_num_rows($res) == 0) {
        mysqli_query($conn, 'ALTER TABLE jurnal ADD COLUMN '.$col.' '.$def);
        echo 'Added '.$col.'\n';
    } else {
        echo $col.' already exists\n';
    }
}
?>
