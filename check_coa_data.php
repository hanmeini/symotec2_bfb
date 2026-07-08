<?php
$c = new mysqli('localhost', 'root', '', 'symotec2_bfb');
$res = $c->query("SELECT * FROM coa LIMIT 5");
echo "Count: " . $c->query("SELECT COUNT(*) as c FROM coa")->fetch_assoc()['c'] . "\n";
while ($row = $res->fetch_assoc()) print_r($row);
?>
