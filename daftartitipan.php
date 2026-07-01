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


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>daftar titipan www.symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #4CAF50;
        }
        form {
            margin-bottom: 20px;
            text-align: center;
        }
        label {
            font-weight: bold;
        }
        input[type="date"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-right: 10px;
        }
        button {
            padding: 8px 16px;
            background-color: blue;
            color: #f2f2f2;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .home-icon1 {
            position: absolute;
            left: 0;
            top: 0;
            padding-left: 10px;
            color: maroon;
            font-size: 24px;
        }

        .left-icon {
            position: absolute;
            right: 0;
            top: 0;
            padding-right: 10px;
            color: maroon;
            font-size: 24px;
        }
        button:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow-x: auto; /* Tambahkan scroll horizontal untuk tabel */
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: right; /* Rata kanan untuk semua kolom */
        }
        th {
            text-align: center; /* Rata tengah untuk header */
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #ddd;
        }
        h2, h3 {
            color: #333;
            text-align: center;
        }
        .action-icon {
            text-align: center; /* Rata tengah untuk kolom action */
        }
        
        /* Media Query untuk perangkat mobile */
        @media (max-width: 768px) {
            th, td {
                padding: 8px; /* Mengurangi padding untuk kolom pada layar kecil */
            }
            th {
                font-size: 14px; /* Mengurangi ukuran font pada header */
            }
            td {
                font-size: 12px; /* Mengurangi ukuran font pada sel */
            }
        }
        
         .add-button {
            display: block;
            width: fit-content;
            margin-bottom: 10px;
            padding: 10px 15px;
            background: blue;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .add-button:hover {
            background: #4cae4c;
            
                
         .i-button {
            display: block;
            width: fit-content;
            margin-bottom: 10px;
            padding: 10px 15px;
            background: blue;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .i-button:hover {
            background: #4cae4c;
            
        }
            .home-icon1 {
            position: absolute;
            left: 0;
            top: 0;
            padding-left: 10px;
            color: maroon;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1">
            <i class="fas fa-home"></i>
        </a>
        <a href="home.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
        <h1>Titipan yang belum memiliki No Booking Job</h1>
        <div style="display: flex; gap: 10px;">
  <a href="dp.php" class="add-button">Tambah Titipan</a>
  <a href="alldp.php" class="add-button">All Titipan Yang Belum Digunakan</a>
  <a href="alldpdone.php" class="add-button">All Titipan Yang Sudah Digunakan</a>
</div>

<?php
        


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




        // Query untuk mendapatkan data dari tabel `pph23`
        $sql_pph23 = "
            SELECT 
                id, 
                kode_booking, 
                tanggal, 
                nominal, 
                description,
                cust_id
        
            
            FROM 
                titipan 
            WHERE 
                kode_booking = '' AND nominal > 0
            ORDER BY 
                 id, cust_id";

        $result_pph23 = $conn->query($sql_pph23);

        if ($result_pph23->num_rows > 0) {
            echo "<table>
                    <tr>
                        <th>ID</th>
                     
                        <th>Kode Booking</th>
                        <th>Tanggal</th>
                        <th>Nominal</th>
                          <th>Keterangan</th>
                        <th>Customer</th>
                         <th>Action</th>
                    
                    </tr>";

            while ($row = $result_pph23->fetch_assoc()) {
                $customer_name = 'Tidak Ditemukan';

                // Query untuk mendapatkan nama customer dari database kedua
                $sql_customer = "
                    SELECT 
                        customer 
                    FROM 
                        customer 
                    WHERE 
                        id = " . intval($row['cust_id']);
                
                $result_customer = $conn->query($sql_customer);

                if ($result_customer && $result_customer->num_rows > 0) {
                    $customer_row = $result_customer->fetch_assoc();
                    $customer_name = $customer_row['nama'];
                }

                echo "<tr>
                        <td>" . htmlspecialchars($row['id'] ?? '') . "</td>
           
                        <td>" . htmlspecialchars($row['kode_booking'] ?? '') . "</td>
                             <td>" . htmlspecialchars($row['tanggal'] ?? '') . "</td>
                          <td>" . number_format($row['nominal'], 2) . "</td>
                           <td>" . htmlspecialchars($row['description'], 2) . "</td>
                        <td>" . htmlspecialchars($customer_name ?? '') . "</td>
                                     <td class='action-icon'>
                            <button type='button' 
                                    onclick='openPopup(\"titipcode.php?J=" . urlencode($row['id']) . "\")'>
                                Input Kode Booking
                            </button>
                        </td>
                      </tr>";
            }

            echo "</table>";
        } else {
            echo "<p>Tidak ada data Titipan yang belum memiliki kode booking.</p>";
        }

        $conn->close();
       
        ?>
    </div>
</body>
<script>
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    document.addEventListener('keydown', function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
            e.preventDefault();
        }
    });
</script>
<script>
    function openPopup(url) {
        const popupWidth = 800; // Lebar popup
        const popupHeight = 900; // Tinggi popup
        const left = (screen.width - popupWidth) / 2; // Posisi horizontal tengah
        const top = (screen.height - popupHeight) / 2; // Posisi vertikal tengah

        // Membuka popup
        window.open(url, 'PopupWindow', `width=${popupWidth},height=${popupHeight},top=${top},left=${left},resizable=yes,scrollbars=yes`);
    }
</script>


</html>
