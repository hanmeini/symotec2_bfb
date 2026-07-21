<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
$res = $conn->query("SELECT * FROM coa WHERE account_name LIKE '%hutang%' OR account_name LIKE '%payable%'");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
