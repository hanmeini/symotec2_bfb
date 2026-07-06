<?php
$servername = "localhost";
$db_username = "root";
$db_password = "";
$database = "symotec2_mkb";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die("Error");
}
$stmt = $pdo->query("SHOW CREATE TABLE pph23");
print_r($stmt->fetch());
