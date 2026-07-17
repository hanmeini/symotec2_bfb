<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

if (!isset($_SESSION['userid'])) {
    header("Location: index.html");
    exit();
}
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$userin = $_SESSION['username'];

require_once 'config1.php';
require_once 'functions.php';
require_once 'functions_stock.php';

// Format nilai J otomatis sesuai standar perusahaan (Bukti Penerimaan Barang)
$Jb = generateNomorDokumen($conn, 'BPB');
$_SESSION['Jb'] = $Jb;

// Ambil data dari form
$tanggal_transaksi = $_POST['tanggal_transaksi'];
$kode_b = $_POST['kode_b'];
$nama_b = $_POST['nama_b'];
$jumlah_m = $_POST['jumlah_m'];
$sup = $_POST['sup'];
$sj = $_POST['sj'];
$id_gudang = $_POST['id_gudang'] ?? 0;

// Validasi input: Pastikan semua field tidak kosong
if (empty($tanggal_transaksi) || empty($kode_b) || empty($nama_b) || empty($jumlah_m) || empty($sup) || empty($sj)) {
    echo "<script>
        alert('Semua data harus diisi! Pastikan tidak ada field yang kosong.');
        window.history.back();
    </script>";
    exit();
}

// Validasi tambahan untuk array
foreach ($kode_b as $index => $kode_barang) {
    if (empty($kode_barang) || empty($nama_b[$index]) || empty($jumlah_m[$index])) {
        echo "<script>
            alert('Data barang tidak boleh kosong! Periksa kembali input Anda.');
            window.history.back();
        </script>";
        exit();
    }
}

// Ambil nama gudang jika cabang
$nama_gudang = '';
if ($id_gudang > 0) {
    $q_gdg = $conn->query("SELECT nama_gudang FROM master_gudang WHERE id_gudang = $id_gudang");
    if ($q_gdg && $r_gdg = $q_gdg->fetch_assoc()) {
        $nama_gudang = $r_gdg['nama_gudang'];
    }
}

// Memulai transaksi
$conn->begin_transaction();

try {
    // 1. Insert ke transaksiHO1 (Ledger Transaksi)
    $stmtTransaksi = $conn->prepare("INSERT INTO transaksiHO1 (tanggal_transaksi, J, kode_b, nama_b, jumlah_m, cus, user, sj, cabang, id_gudang) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // 2. Insert ke stock (Ledger Inventori)
    $stmtStock = $conn->prepare("INSERT INTO stock (tanggal_transaksi, J, sup, kodeb, jumlah_m, userid, sj, id_gudang) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($kode_b as $index => $kode_barang) {
        $nama_barang = $nama_b[$index];
        $qty_masuk = $jumlah_m[$index];

        // Eksekusi Ledger Transaksi
        $stmtTransaksi->bind_param("ssssdssssi", 
            $tanggal_transaksi, 
            $Jb, 
            $kode_barang, 
            $nama_barang, 
            $qty_masuk,
            $sup,
            $userin,
            $sj,
            $nama_gudang,
            $id_gudang
        );
        if (!$stmtTransaksi->execute()) {
            throw new Exception("Gagal insert ke transaksiHO1: " . $stmtTransaksi->error);
        }

        // Eksekusi Ledger Inventori (Stock)
        $stmtStock->bind_param("ssssdssi", 
            $tanggal_transaksi, 
            $Jb,
            $sup,
            $kode_barang, 
            $qty_masuk,
            $userin,
            $sj,
            $id_gudang
        );
        if (!$stmtStock->execute()) {
            throw new Exception("Gagal insert ke stock: " . $stmtStock->error);
        }
        
        // Recalculate stock history for this item
        recalculate_stock_history($conn, $kode_barang);
    }
    
    $stmtTransaksi->close();
    $stmtStock->close();

    // Simpan ke tabel pembelian_b
    $stmt2 = $conn->prepare("INSERT INTO pembelianHO1 (sup, tanggal_transaksi, j, sj) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("ssss", $sup, $tanggal_transaksi, $Jb, $sj);
    if (!$stmt2->execute()) {
        throw new Exception($stmt2->error);
    }
    $stmt2->close();

    // Commit transaksi
    $conn->commit();

    // Notifikasi berhasil dan redirect ke nota.php
    echo "<script>
        alert('Data berhasil disimpan dengan kode transaksi: $Jb');
        window.location.href = 'beli.php?Jb=$Jb';
    </script>";

} catch (Exception $e) {
    // Rollback jika ada error
    $conn->rollback();

    // Notifikasi gagal
    echo "<script>
        alert('Error: " . $e->getMessage() . "');
        window.history.back();
    </script>";
}

// Tutup koneksi
$conn->close();
?>
