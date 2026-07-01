<?php
require 'config1.php';

// Create 'cos' table (Cash Out Slip - auto-increment counter for cash payment numbering)
$conn->query("
    CREATE TABLE IF NOT EXISTS cos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cos VARCHAR(50) DEFAULT NULL
    )
");
echo "Created cos table.\n";

// Also check if 'bos' table exists (Bank Out Slip - for bank payment numbering)
$check = $conn->query("SHOW TABLES LIKE 'bos'");
if ($check && $check->num_rows == 0) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS bos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bos VARCHAR(50) DEFAULT NULL
        )
    ");
    echo "Created bos table.\n";
} else {
    echo "bos table already exists.\n";
}

// Also check if 'cis' table exists (Cash In Slip - for cash receipt numbering)
$check = $conn->query("SHOW TABLES LIKE 'cis'");
if ($check && $check->num_rows == 0) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS cis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cis VARCHAR(50) DEFAULT NULL
        )
    ");
    echo "Created cis table.\n";
} else {
    echo "cis table already exists.\n";
}

// Also check if 'bis' table exists (Bank In Slip)
$check = $conn->query("SHOW TABLES LIKE 'bis'");
if ($check && $check->num_rows == 0) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS bis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bis VARCHAR(50) DEFAULT NULL
        )
    ");
    echo "Created bis table.\n";
} else {
    echo "bis table already exists.\n";
}

echo "\nDone!\n";
