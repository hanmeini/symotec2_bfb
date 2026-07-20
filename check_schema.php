<?php
$conn_mkb = new mysqli('localhost', 'root', '', 'symotec2_mkb');
$res = $conn_mkb->query("SHOW TABLES");
if ($res) {
    while ($r = $res->fetch_row()) {
        if (strpos($r[0], 'po') !== false || strpos($r[0], 'purchase') !== false || strpos($r[0], 'order') !== false) {
            echo "MKB: " . $r[0] . "\n";
        }
    }
}
?>








