<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
$res = $conn->query("SELECT id, journal_number, jurnal_sementara, keterangan FROM jurnal WHERE id IN (80, 81, 84, 85)");
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>
