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



$allowed_referer_domain = "https://symotech.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $allowed_referer_domain) !== 0) {
    header("Location: https://symotech.id");
    exit();
}


// Periksa apakah pengguna sudah login
if (!isset($_SESSION['userid'])) {
    // Jika belum login, redirect ke halaman login
    header("Location: index.html");
    exit();
}

// Periksa apakah session location adalah 'HO' atau 'HO1'
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    // Jika lokasi bukan 'HO' atau 'HO1', redirect ke halaman login
    header("Location: index.html");
    exit();
}
require_once 'config.php';

// Konfigurasi koneksi database
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("Kesalahan: DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");

$conn = new mysqli($servername, $db_username, $db_password, $database);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
  die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil nilai 'J' dari URL
$J = isset($_GET['J']) ? $_GET['J'] : null;

// Query pertama: transaksiHO1
$sql = "SELECT tanggal_transaksi, J, cus, kode_b, nama_b, jumlah_k, harga_k, ppn_k, hargat_k 
        FROM transaksiHO1
        WHERE J = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $J);
$stmt->execute();
$stmt->bind_result($tanggal_transaksi, $j_value, $cus, $kode_b, $nama_b, $jumlah_k, $harga_k, $ppn_k, $hargat_k);

$total_harga_k = 0;
$total_ppn_k = 0;
$total_hargat_k = 0;

// Proses hasil query pertama
$transaksi_data = [];
if ($stmt->store_result() && $stmt->num_rows > 0) {
    while ($stmt->fetch()) {
        $transaksi_data[] = [
            'kode_b' => $kode_b,
            'nama_b' => $nama_b,
            'jumlah_k' => $jumlah_k,
            'harga_k' => $harga_k,
            'ppn_k' => $ppn_k,
            'hargat_k' => $hargat_k,
        ];

        $total_harga_k += $harga_k;
        $total_ppn_k += $ppn_k;
        $total_hargat_k += $hargat_k;
    }
}
$stmt->close();

// Query kedua: penjualanHO1
$sql2 = "SELECT J, cust, diskon, harga, ppn, jumlah , po
         FROM penjualanHO1
         WHERE J = ?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("s", $J);
$stmt2->execute();
$stmt2->bind_result($j_value, $cust, $diskon, $harga2, $ppn2, $jumlah, $po);

// Proses hasil query kedua
$penjualan_data = [];
if ($stmt2->store_result() && $stmt2->num_rows > 0) {
    $stmt2->fetch();
    $penjualan_data = [
        'cust' => $cust,
        'diskon' => $diskon,
        'harga2' => $harga2,
        'ppn2' => $ppn2,
        'jumlah' => $jumlah,
          'po' => $po,
    ];
}

$stmt2->close();

// Query untuk mengambil data customer
$cust_sql = "SELECT nama, alamat, npwp FROM cust WHERE kode = ?";
$cust_stmt = $conn->prepare($cust_sql);
$cust_stmt->bind_param('s', $cust);
$cust_stmt->execute();
$cust_stmt->store_result();
$cust_stmt->bind_result($nama, $alamat, $npwp);

$cust_data = [];
if ($cust_stmt->num_rows > 0) {
    while ($cust_stmt->fetch()) {
        $cust_data = [
            'nama' => $nama,
            'alamat' => $alamat,
            'npwp' => $npwp,
        ];
    }
}
$cust_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
            font-size: 12px; /* Ukuran font */
        }

        .table-container {
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            max-width: 800px;
        }

        h1 {
            color: #333;
             font-size: 30px; /* Ukuran font */
                       margin: 0;
        }
            h2 {
            color: #333;
            text-align: center; /* Menyelaraskan teks ke tengah */
             font-size: 30px; /* Ukuran font */
        }

           h3 {
            color: #333;
            font-size: 14px; /* Ukuran font */
                      margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        table th, table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        table th {
            background-color: #f9f9f9;
            color: #555;
        }

        .total {
            margin: 20px 0;
            font-size: 0.8em;
        }

        .total h3 {
            color: #555;
        }

        .home-icon1, .left-icon {
            text-decoration: none;
            font-size: 1.5em;
            color: #555;
        }

        .home-icon1 {
            margin-right: 10px;
        }

        .no-data {
            color: red;
            text-align: center;
        }
.button-container {
    display: flex; /* Gunakan flexbox */
    justify-content: center; /* Tengahkan tombol secara horizontal */
    gap: 10px; /* Atur jarak antar tombol */
    margin-top: 10px; /* Beri jarak ke elemen atas */
}
       .button {
            padding: 10px 20px;
            font-size: 12px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .button:hover {
            background: #45a049;
        }
        .abc {
    display: flex;
    justify-content: space-between; /* Membuat elemen menyebar di kiri dan kanan */
    align-items: center; /* Menjaga posisi elemen secara vertikal terpusat */
    width: 95%; /* Membuat div mengambil seluruh lebar */
    padding: 10px; /* Optional, untuk menambahkan jarak antar elemen dan tepi */

            
        }


.home-icon1 {
    font-size: 24px; /* Sesuaikan ukuran ikon */
    color: #333; /* Sesuaikan warna ikon */
}

.left-icon {
    font-size: 24px; /* Sesuaikan ukuran ikon */
    color: #333; /* Sesuaikan warna ikon */
}
.logo {
    width: 50px; /* Atur ukuran gambar sesuai kebutuhan */
    height: auto;
    margin-left: auto; /* Menempatkan logo di sisi paling kanan */
}
.aa {
    display: inline-block;
    width: 70%; /* Atur lebar sesuai kebutuhan */
}

.logo {
    display: inline-block;
    width: 30%; /* Atur lebar sesuai kebutuhan */
    text-align: right; /* Memposisikan gambar di kanan */
}

.logo img {
    max-width: 100%; /* Agar gambar responsif */
    height: auto;
}
.aa {
    width: 60%; /* Menentukan lebar untuk kolom kiri */
}

.logo {
    width: 30%; /* Menentukan lebar untuk kolom kanan */
    text-align: right; /* Memposisikan gambar di sebelah kanan */
}

.logo img {
    max-width: 100%; /* Agar gambar responsif */
    height: auto;
}
@media print {
    /* Sembunyikan semua elemen selain yang ada dalam div.table-container */
    body * {
        visibility: hidden;
    }

    .table-container, .table-container * {
        visibility: visible;
    }

    .table-container {
        position: absolute;
        top: 0;
        left: 0;
    }
}
.table-container {
    margin: 20px auto; /* Tengah secara horizontal dan jarak 20px atas/bawah */
    max-width: 1000px; /* Tentukan lebar maksimum elemen */
    padding: 15px; /* Opsional: Tambahkan ruang di dalam elemen */
    background-color: #f9f9f9; /* Opsional: Latar belakang elemen */
    border: 1px solid #ddd; /* Opsional: Border untuk visualisasi */
    border-radius: 10px; /* Opsional: Membuat sudut melengkung */
}
.a1 {
    border: 1px solid #ddd; /* Border abu-abu tipis */
    border-radius: 10px; /* Membuat sudut kotak melengkung */
    padding: 15px; /* Ruang di dalam kotak */
    background-color: #f9f9f9; /* Warna latar belakang kotak */
    margin: 0px auto; /* Tambahkan margin atas/bawah dan tengah secara horizontal */
    max-width: 1000px; /* Batas lebar maksimum */
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Efek bayangan untuk estetika */
}
.a1 p {
    display: flex;
    margin: 5px 0; /* Jarak antar baris */
    font-size: 16px;
}

.a1 p strong {
    width: 120px; /* Lebar tetap untuk label */
    text-align: left; /* Rata kiri untuk label */
    padding-right: 20px; /* Jarak 20px setelah label */
}

.a1 p span {
    flex: 1; /* Memastikan data menyesuaikan lebar */
    text-align: left; /* Rata kiri untuk data */
}


}


    </style>
       <script>
function openPopup(url) {
    const popupWidth = 800; // Lebar popup
    const popupHeight = 850; // Tinggi popup
    const left = (screen.width - popupWidth) / 2;
    const top = (screen.height - popupHeight) / 2;

    window.open(
        url,
        '_blank',
        `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=yes,scrollbars=yes`
    );
}
</script>
</head>
<body>
    
      <div class="button-container">
             <div class="abc">
  <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="pos.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>

    </div>    </div>
        <div class="button-container">

        <button class="button" onclick="window.print();">Print</button>
        
 <button class="button" onclick="openPopup('pelunasan.php?J=<?= urlencode($J) ?>')" title="Lihat Nota">
    Pembayaran
</button>
<button class="button" onclick="openPopup('sjHO1.php?J=<?= urlencode($J) ?>')" title="Lihat SJ Sederhana">
    Surat Jalan
</button>
    </div>
 
    
<div class="table-container">
       <div class="aa">
    <h1>Symotech Retail </h1>  
    <h3>Jl. Pangeran sukarma Rt/Rw: 14/04 no.10 kel. Padang, kec. Sukamara kab. Sukamara <br>
    Telp. (0532) 27333 Hunting <BR>
        Fax. (0532) 21680
    </h3>
    
         </div>
         <div class="logo">
    <img src="logogm.png" alt="Logo Symotech Retail">
</div>
<hr>
    <h2>INVOICE</h2>
      <div class="abc">
  <p><strong>Nomor: </strong><?php echo htmlspecialchars($J); ?></p>
  <p><strong>Nota: ASLI </strong></p>
    </a>

    </div>



<div class="a1">
    <p><strong>Nama</strong><span>:<?php echo htmlspecialchars($cust_data['nama']); ?></span></p>
    <p><strong>Alamat</strong><span>:<?php echo htmlspecialchars($cust_data['alamat']); ?></span></p>
    <p><strong>PO/Kontrak</strong><span>:<?php echo htmlspecialchars($penjualan_data['po']); ?></span></p>
</div>

    <table>
    <thead>
        <tr>
            <th>No</th>
            <th>Deskripsi Barang</th>
            <th>Quantity</th>
            <th>Harga</th>
            <th>PPn</th>
            <th>Sub Total</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $counter = 1; // Inisialisasi nomor urut
        foreach ($transaksi_data as $data): 
        ?>
            <tr>
                <td><?php echo $counter++; ?></td> <!-- Menampilkan nomor urut -->
                <td><?php echo htmlspecialchars($data['nama_b']); ?></td>
                <td><?php echo htmlspecialchars($data['jumlah_k']); ?></td>
                <td style="text-align: right;"><?php echo number_format($data['harga_k'], 2); ?></td>
        <td style="text-align: right;"><?php echo number_format($data['ppn_k'], 2); ?></td>
        <td style="text-align: right;"><?php echo number_format($data['hargat_k'], 2); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6">
              <div class="a2" style="display: flex; flex-direction: column; align-items: flex-end; margin: 0px;">
    <div style="display: flex; justify-content: flex-end; width: 100%;">
        <div style="text-align: right; padding-right: 20px; width: 180%;">Total :</div>
        <div style="text-align: right; width: 40%;"><?php echo number_format($total_harga_k, 2); ?></div>
    </div>
    <div style="display: flex; justify-content: flex-end; width: 100%;">
        <div style="text-align: right; padding-right: 20px; width: 180%;">Diskon:</div>
        <div style="text-align: right; width: 40%;"><?php echo number_format($penjualan_data['diskon'], 2); ?></div>
    </div>
    <div style="display: flex; justify-content: flex-end; width: 100%;">
        <div style="text-align: right; padding-right: 20px; width: 180%;">Total Setelah Diskon:</div>
        <div style="text-align: right; width: 40%;"><?php echo number_format($penjualan_data['harga2'], 2); ?></div>
    </div>
    <div style="display: flex; justify-content: flex-end; width: 100%;margin: 0px;">
        <div style="text-align: right; padding-right: 20px; width: 180%;">PPN:</div>
        <div style="text-align: right; width: 40%;"><?php echo number_format($penjualan_data['ppn2'], 2); ?></div>
    </div>
</div>
<hr>
<div class="a3" style="display: flex; justify-content: flex-end; align-items: center; width: 100%; margin: 0px;">
    <div style="text-align: right; padding-right: 20px; width: 180%;">Grand total:</div>
    <div style="text-align: right; width: 40%;"><?php echo number_format($penjualan_data['jumlah'], 2); ?></div>
</div>

</div>


            </td>
        </tr>
    </tfoot>
</table>



<div style="display: flex; justify-content: space-between; align-items: flex-start; font-size: 10px;">
    <!-- Kolom Kiri: Rekening -->
    <div class="rekening" style="width: 50%; line-height: 1;">
        <p style="margin: 5;"><em>Rekening Pembayaran:</em></p>
        <p style="margin: 5;">Bank Danamon (Cab. Pangkalan Bun) - A/C: <strong>456 45604</strong></p>
        <p style="margin: 5;">________________________________A/N: CV. Symotech Retail</p>
        
        <p style="margin: 5;">Bank BRI (Cab. Pangkalan Bun) --------- A/C: <strong>0287-01-000448-56-1</strong></p>
        <p style="margin: 5;">________________________________A/N: CV. Symotech Retail</p>
        
        <p style="margin: 5;">Bank BNI (Cab. Pangkalan Bun) --------- A/C: <strong>0418 181 581</strong></p>
        <p style="margin: 5;">________________________________A/N: CV. Symotech Retail</p>
    </div>

    <!-- Kolom Kanan: Tanggal -->
    <div class="tanggal" style="width: 40%; text-align: center; line-height: 1;">
<p style="margin: 5; text-align: center;">
    Pangkalan Bun, 
    <?php
    $formatter = new IntlDateFormatter(
        'id_ID', // Locale untuk Bahasa Indonesia
        IntlDateFormatter::LONG, // Format panjang (contoh: 26 Desember 2024)
        IntlDateFormatter::NONE
    );
    echo htmlspecialchars($formatter->format(new DateTime($tanggal_transaksi)));
    ?>
</p>




<div class="meterai" style="margin-top: 0px; border: 1px solid black; padding: 10px;">
    <p style="margin: 5;">
        <em>
            Pembeli<span style="display: inline-block; width: 80px;"></span>CV. Symotech Retail
        </em>
    </p>
    <br><br><br><br><br>
    <p style="margin: 5;">
        <em>
            ................................<span style="display: inline-block; width: 40px;"></span>................................
        </em>
    </p>
</div>
</div>
</div>


   
</div>
  



</body>
</html>
