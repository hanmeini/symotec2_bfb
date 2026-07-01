<?php
// install_menu.php
require_once 'config.php';

$servername  = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database    = getenv('DB_NAME');

$conn = new mysqli($servername, $db_username, $db_password !== false ? $db_password : '', $database);
if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Menus to be installed/aligned
$menus = [
    [
        'nama_menu' => 'Absensi',
        'file_menu' => 'absen.php',
        'icon_menu' => 'fa-solid fa-calendar-check',
        'urutan' => 90
    ],
    [
        'nama_menu' => 'Karyawan',
        'file_menu' => 'karyawan.php',
        'icon_menu' => 'fa-solid fa-users',
        'urutan' => 91
    ],
    [
        'nama_menu' => 'Gaji Harian',
        'file_menu' => 'gaji_harian.php',
        'icon_menu' => 'fa-solid fa-money-bill-wave',
        'urutan' => 92
    ],
    [
        'nama_menu' => 'Mutasi Harian',
        'file_menu' => 'mutasi_harian.php',
        'icon_menu' => 'fa-solid fa-arrows-spin',
        'urutan' => 93
    ],
    [
        'nama_menu' => 'Riwayat',
        'file_menu' => 'riwayat_gaji.php',
        'icon_menu' => 'fa-solid fa-clock-rotate-left',
        'urutan' => 94
    ]
];

echo "<h3>Memulai Install / Sync Menu BFB...</h3><hr>";

foreach ($menus as $m) {
    // Check if menu with the same file name already exists
    $stmt = $conn->prepare("SELECT id_menu FROM menu WHERE file_menu = ?");
    $stmt->bind_param("s", $m['file_menu']);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        // Insert new menu
        $ins = $conn->prepare("INSERT INTO menu (nama_menu, file_menu, icon_menu, urutan, aktif) VALUES (?, ?, ?, ?, 1)");
        $ins->bind_param("sssi", $m['nama_menu'], $m['file_menu'], $m['icon_menu'], $m['urutan']);
        if ($ins->execute()) {
            echo "✅ Menu '<strong>{$m['nama_menu']}</strong>' ({$m['file_menu']}) berhasil ditambahkan.<br>";
        } else {
            echo "❌ Gagal menambahkan menu '{$m['nama_menu']}': " . $conn->error . "<br>";
        }
        $ins->close();
    } else {
        // Update existing menu icon, name, urutan
        $upd = $conn->prepare("UPDATE menu SET nama_menu = ?, icon_menu = ?, urutan = ?, aktif = 1 WHERE file_menu = ?");
        $upd->bind_param("ssis", $m['nama_menu'], $m['icon_menu'], $m['urutan'], $m['file_menu']);
        if ($upd->execute()) {
            echo "ℹ️ Menu '<strong>{$m['nama_menu']}</strong>' ({$m['file_menu']}) sudah ada - berhasil disinkronisasi.<br>";
        } else {
            echo "❌ Gagal memperbarui menu '{$m['nama_menu']}': " . $conn->error . "<br>";
        }
        $upd->close();
    }
    $stmt->close();
}

// Clean up old rekap harian menu if any
$conn->query("DELETE FROM menu WHERE file_menu = 'rekap_harian.php'");

echo "<hr><h4>Selesai!</h4>";
?>
