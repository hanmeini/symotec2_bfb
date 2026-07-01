<?php
require 'config1.php';
$stmt = $pdo->query("SELECT * FROM master_sales");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
