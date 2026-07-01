<?php
require 'config1.php';
$res = $conn->query("SHOW COLUMNS FROM booking_request");
$cols = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row;
    }
}
file_put_contents('booking_request_schema.json', json_encode($cols, JSON_PRETTY_PRINT));
