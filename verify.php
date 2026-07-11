<?php
$conn = new mysqli('localhost', 'root', '', 'symotec2_bfb');
$res = $conn->query("SELECT kode_b, nama_b, satuan_besar, rasio_besar, satuan_tengah, rasio_tengah, satuan_kecil FROM b LIMIT 3");
while($row = $res->fetch_assoc()) print_r($row);

$res = $conn->query("SELECT kode, nama, kategori, kontak FROM sup LIMIT 3");
while($row = $res->fetch_assoc()) print_r($row);
?>
