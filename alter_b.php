<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
$conn->query("ALTER TABLE b ADD COLUMN format_rasio VARCHAR(50) DEFAULT ''");
if($conn->error) echo $conn->error; else echo "Column added successfully";
?>
