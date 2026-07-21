<?php
$conn = new mysqli('127.0.0.1', 'root', '');
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);
$res = $conn->query("SHOW DATABASES LIKE '%bfb%'");
while($row = $res->fetch_array()) {
    echo $row[0] . "\n";
}
?>
