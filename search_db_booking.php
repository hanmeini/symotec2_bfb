<?php
require 'config1.php';

// Find tables with name containing booking
$res = $conn->query("SHOW TABLES");
echo "Tables:\n";
while ($row = $res->fetch_row()) {
    if (stripos($row[0], 'book') !== false) {
        echo "  - " . $row[0] . "\n";
    }
}

// Find columns containing booking
echo "\nColumns containing 'booking':\n";
$res = $conn->query("SHOW TABLES");
while ($table_row = $res->fetch_row()) {
    $table = $table_row[0];
    $cols = $conn->query("SHOW COLUMNS FROM `$table` desc");
    if ($cols) {
        while ($col_row = $cols->fetch_assoc()) {
            if (stripos($col_row['Field'], 'booking') !== false) {
                echo "  - $table.{$col_row['Field']}\n";
            }
        }
    }
}
