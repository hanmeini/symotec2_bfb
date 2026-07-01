<?php
require 'config1.php';
$res = $conn->query("SHOW TABLES");
$tables = [];
if ($res) {
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }
}
file_put_contents('tables.json', json_encode($tables, JSON_PRETTY_PRINT));
