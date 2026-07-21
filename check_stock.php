<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
$res = $conn->query("SHOW COLUMNS FROM stock");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
