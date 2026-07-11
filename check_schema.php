<?php
$conn = new mysqli('localhost', 'root', '', 'symotec2_bfb');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
echo "--- Table b ---\n";
$res = $conn->query("SHOW COLUMNS FROM b");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "--- Table sup ---\n";
$res = $conn->query("SHOW COLUMNS FROM sup");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
