<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);


// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan


// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// Load konfigurasi dari config.php
require_once 'config1.php';



// Ambil variabel dari environment


// Periksa koneksi pertama


// Buat koneksi ke database kedua


// Periksa koneksi kedua
if ($conn->connect_error) {
    die("Koneksi ke database kedua gagal: " . $conn->connect_error);
}

$query = "SELECT max(id) AS max_nomor FROM pv";
$result = $conn->query($query);

if ($result) {
    $row = $result->fetch_assoc();
    $max_nomor = $row['max_nomor'];

    if ($max_nomor) {
        // Tambahkan 1 ke nomor maksimum
        $nomor = intval($max_nomor) + 1;
    } else {
        // Jika tidak ada nomor maksimum, mulai dari 1
        $nomor = 1;
    }

    // Format nomor menjadi 4 digit
    $nomor_formatted = sprintf('%04d', $nomor);
} else {
    // Jika query gagal, atur nomor menjadi 1
    $nomor_formatted = '00001';
}

// Ambil data dari form
$tanggal = $_POST['tanggal'];
$keterangan = $_POST['keterangan'];

$coa = $_POST['coa'];
$account_name = $_POST['account_name'];
$debet = str_replace(',', '', $_POST['debet']); 
$kredit = str_replace(',', '', $_POST['kredit']);
$location = $_POST['location'];
$devisi = $_POST['devisi'];
$kode_booking = $_POST['kode_booking'];
$totalSum = $_POST['totalSum'];
$totalSisa = $_POST['totalSisa'];
$inv= $_POST['inv'];
$cust_id= $_POST['cust_id'];
$idbeli= $_POST['idbeli'];
$totalpph23= $_POST['totalpph23'];
$titipansisa= $_POST['titipansisa'];
$idn= $_POST['idt'];






$bulan = date('m', strtotime($tanggal)); // Format bulan dalam angka (01-12)
$tahun = date('Y', strtotime($tanggal)); // Format tahun dalam angka (contoh: 2024)

// Bentuk kode transaksi
$kode_transaksi = "PV" . "$tahun" . $nomor_formatted;
$description = "sisaDN - " . $idn;


// Menggabungkan semua nilai keterangan menjadi satu string
$keterangan_value = implode(', ', $keterangan);


// Query untuk menyimpan data
$sql = "INSERT INTO jurnal (journal_number, tanggal, keterangan, coa, debet, kredit) VALUES (?, ?, ?, ?, ?, ?)";

// Siapkan statement
$stmt = $conn->prepare($sql);

// Lakukan satu kali looping untuk menyimpan setiap baris jurnal
for ($i = 0; $i < count($coa); $i++) {
    // Set nilai parameter untuk setiap iterasi
    $tanggal_value = $tanggal;
    $coa_value = $coa[$i];
    $account_name_value = isset($account_name[$i]) ? $account_name[$i] : '';
    $debet_value = isset($debet[$i]) ? $debet[$i] : 0;
    $kredit_value = isset($kredit[$i]) ? $kredit[$i] : 0;
    $kode_booking_value = isset($kode_booking[$i]) ? $kode_booking[$i] : '';
    $location_value = $location;
    $devisi_value = $devisi;
  
    $cust_id_value = $cust_id;

    // Tambahkan "PPH23" jika baris ke-3 atau ke-4
    $journal_number_value = $kode_transaksi;
    $keterangan_loop_value = $keterangan_value;
    if ($i === 2 || $i === 3) {
        $journal_number_value .= " PPH23";
        $keterangan_loop_value .= " PPH23";
    }

    // Bind dan eksekusi
    $stmt->bind_param(
        "ssssss",
        $journal_number_value,
        $tanggal_value,
        $keterangan_loop_value,
        $coa_value,
        $debet_value,
        $kredit_value
    );

    $stmt->execute();
}


// Simpan kode transaksi di tabel bo
$sql1 = "INSERT INTO pv (pv) VALUES (?)"; // Pastikan 'jurnal' adalah nama kolom yang ada di tabel bo
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("s", $kode_transaksi);
$stmt1->execute();

// Tutup statement dan koneksi
$stmt1->close();

// Ambil nilai kredit baris ke-1 dan ke-3
$kedit1 = isset($kredit[0]) ? str_replace(',', '', $kredit[0]) : 0;
$kedit3 = isset($kredit[3]) ? str_replace(',', '', $kredit[3]) : 0;

// Update ke tabel BELI
$sql2 = "UPDATE BELI SET bayar = ?, sisa = ?, pph23 = ? WHERE id = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("ddds", $totalSum, $totalSisa, $totalpph23, $idbeli);
$stmt2->execute();
// Periksa apakah ada baris yang diperbarui
if ($stmt2->affected_rows > 0) {
    echo "Data berhasil diperbarui.";
} else {
    echo "Tidak ada data yang diperbarui atau INV tidak ditemukan.";
}

// Tutup statement
$stmt2->close();

// Ambil nilai debet dari POST
$debet_value = is_array($_POST['debet']) ? array_sum($_POST['debet']) : $_POST['debet'];

$sql3 = "INSERT INTO apby (tanggal, inv, kodebooking, cust_id, bayar1) VALUES (?, ?, ?, ?, ?)";
$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("ssssd", $tanggal, $inv, $kode_booking, $cust_id, $kedit1);
$stmt3->execute();


// Periksa apakah data berhasil disimpan
if ($stmt3->affected_rows > 0) {
    echo "Data berhasil disimpan ke tabel arby.";
} else {
    echo "Gagal menyimpan data ke tabel arby.";
}

// Tutup statement
$stmt3->close();



$sql4 = "INSERT INTO cndn (tanggal, dn, description, id_cust, id_parent) VALUES (?, ?, ?, ?, ?)";
$stmt4 = $conn->prepare($sql4);
$stmt4->bind_param("sssss", $tanggal, $titipansisa, $description, $cust_id, $idn );
$stmt4->execute();
$sql5 = "UPDATE cndn SET inv = ?  WHERE idn = ?";
$stmt5 = $conn->prepare($sql5);
$stmt5->bind_param("ss", $inv, $idn);
$stmt5->execute();

// Tampilkan notifikasi berhasil dan arahkan ke cetak_jurnal.php dengan kode transaksi
echo "<script>
    alert('Data jurnal berhasil disimpan! Kode Transaksi: $kode_transaksi');
    window.location.href = 'cetak_jurnal.php?kode_transaksi=$kode_transaksi';
</script>";
$conn->close();
exit();
?>
