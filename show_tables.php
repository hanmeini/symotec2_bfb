<?php
require 'config.php';
$c = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));

// Cek akses bagian untuk menu pos.php (id_menu = 59)
echo "=== Akses Bagian untuk pos.php (id_menu=59) ===\n";
$r = $c->query("SELECT * FROM akses_bagian WHERE id_menu = 59");
while ($row = $r->fetch_assoc()) {
    echo "bagian: {$row['bagian']}\n";
}

// Copy akses menu 59 ke menu 118 (pos_inv.php)
echo "\n=== Copy akses dari menu 59 ke menu 118 ===\n";
$copy = $c->query("INSERT INTO akses_bagian (id_menu, bagian)
                   SELECT 118, bagian FROM akses_bagian WHERE id_menu = 59");
if ($copy) {
    echo "SUCCESS: Akses berhasil di-copy (" . $c->affected_rows . " baris)\n";
} else {
    echo "ERROR: " . $c->error . "\n";
}

// Cek juga akses_jabatan
echo "\n=== Akses Jabatan untuk pos.php (id_menu=59) ===\n";
$r2 = $c->query("SELECT * FROM akses_jabatan WHERE id_menu = 59");
while ($row = $r2->fetch_assoc()) {
    echo "bagian: {$row['bagian']} | jabatan: {$row['jabatan']}\n";
}

// Copy akses jabatan
$copy2 = $c->query("INSERT INTO akses_jabatan (id_menu, bagian, jabatan)
                    SELECT 118, bagian, jabatan FROM akses_jabatan WHERE id_menu = 59");
if ($copy2) {
    echo "Akses jabatan di-copy: " . $c->affected_rows . " baris\n";
}
