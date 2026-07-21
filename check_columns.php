<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);
$res = $conn->query("SHOW COLUMNS FROM b");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
