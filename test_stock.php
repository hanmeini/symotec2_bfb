<?php
require "config1.php";
require "functions_stock.php";
$stmt = $conn->query("SELECT kode_b FROM b LIMIT 1");
if($r = $stmt->fetch_assoc()) {
    echo "Recalculating " . $r['kode_b'] . "\n";
    recalculate_stock_history($conn, $r['kode_b']);
    echo "Success\n";
}
