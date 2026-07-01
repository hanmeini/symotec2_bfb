<?php
require 'config1.php';
$r = $conn->query('DESCRIBE penjualanHO1');
while($row = $r->fetch_assoc()) echo $row['Field'] . ' ';
echo "\n";
