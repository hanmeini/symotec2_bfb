<?php
require 'config1.php';
$res = $conn->query("SELECT * FROM menu");
while($r = $res->fetch_assoc()){
    echo $r['nama_menu'] . " -> " . $r['link_menu'] . "\n";
}
?>
