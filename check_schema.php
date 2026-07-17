<?php
require 'config1.php';
$tables = ['stock', 'penjualanHO1', 'rpc_header'];
foreach ($tables as $t) {
    echo "TABLE: $t\n";
    $res = $conn->query("DESCRIBE $t");
    if($res) {
        while($r = $res->fetch_assoc()) echo $r['Field'].' - '.$r['Type']."\n";
    } else {
        echo "Table does not exist.\n";
    }
    echo "------------------\n";
}
?>
