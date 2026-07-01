<?php

  
    



require_once 'config1.php';



// Periksa apakah ada parameter ID yang dikirimkan melalui URL
if (isset($_GET['id'])) {
    // Jika ada, simpan ID ke dalam variabel
    $id = $_GET['id'];

    // Query untuk mengambil data COA berdasarkan ID
    $sql = "SELECT * FROM coa WHERE id = $id";

    // Lakukan eksekusi query
    $result = $conn->query($sql);

    // Periksa apakah ada hasil query
    if ($result->num_rows > 0) {
        // Jika ada, ambil baris data
        $row = $result->fetch_assoc();
        // Simpan data ke dalam variabel sesuai kebutuhan (misalnya $account_code, $account_name, dll.)
        $account_code = $row['account_code'];
        $account_name = $row['account_name'];
        $posisi = $row['posisi'];
        $dc = $row['dc'];
        $open = $row['open'];
        $parent_account = $row['parent_account'];
        $layer = $row['layer'];
    } else {
        // Jika tidak ada hasil query, tampilkan pesan error atau redirect ke halaman lain
        echo "Data tidak ditemukan.";
        exit(); // Hentikan eksekusi script
    }
} else {
    // Jika tidak ada parameter ID yang dikirimkan, tampilkan pesan error atau redirect ke halaman lain
    echo "ID tidak ditemukan.";
    exit(); // Hentikan eksekusi script
}
// Query untuk mengambil data dari tabel COA
$sql = "SELECT account_code FROM coa";

// Lakukan eksekusi query
$result = $conn->query($sql);

// Tutup koneksi database
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit COA www.symotech.id</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>

    body {
        color: maroon; /* Warna teks */
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 0;
        background-image: linear-gradient(to right, #ea90e6, #6c6cc9); /* Gradien dari atas ke bawah */
    }

    h2 {
        text-align: center; /* Pusatkan judul */
        margin-bottom: 20px; /* Beri jarak bawah */
        color: maroon;
    }

    .container {
        overflow: hidden; /* Atasi masalah clearfix */
    }

    .form-container {
        width: 200px; /* Lebar formulir */
        float: left; /* Letakkan formulir di sebelah kiri */
        background-color: #fff; /* Warna latar belakang formulir */
        padding: 20px; /* Ruang dalam formulir */
        border-radius: 10px; /* Sudut bulat */
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Bayangan formulir */
         margin: 10px;
    }

    label {
        font-weight: bold; /* Tebal kan label */
    }

    input[type="text"],
    select {
        width: 100%; /* Lebar input */
        padding: 8px; /* Ruang dalam input */
        margin-bottom: 15px; /* Beri jarak bawah */
        border: 1px solid #ccc; /* Garis tepi */
        border-radius: 5px; /* Sudut bulat */
        box-sizing: border-box; /* Hitung lebar dengan tepi dan padding */
    }

    input[type="submit"] {
        width: 100%; /* Lebar tombol submit */
        padding: 10px; /* Ruang dalam tombol */
        background-color: maroon; /* Warna latar belakang tombol */
        color: #fff; /* Warna teks tombol */
        border: none; /* Hilangkan garis tepi */
        border-radius: 5px; /* Sudut bulat */
        cursor: pointer; /* Tampilkan kursor tangan saat diarahkan */
        transition: background-color 0.3s; /* Efek transisi */
    }

    input[type="submit"]:hover {
        background-color: #8b0000; /* Warna latar belakang tombol saat dihover */
    }

    /* Gaya untuk tabel */
    .table-container {
        width: 50%; /* Lebar tabel */
        float: right; /* Letakkan tabel di sebelah kanan */
        margin-top: 20px; /* Beri jarak atas */
        FONT COLOR: WHITE;
    }

    table {
        width: 100%; /* Gunakan lebar penuh */
        border-collapse: collapse; /* Gabungkan batas sel */
        color: white
    }

    table, th, td {
        border: 1px solid #ddd; /* Garis tepi */
        padding: 8px; /* Ruang dalam sel */
        text-align: left; /* Rata kiri teks dalam sel */
        FONT COLOR: WHITE;
    }

    th {
        background-color: mAROON; /* Warna latar belakang header */
    }
        .home-icon i {
        color: maroon;
        font-size: 24px;
         float: left;
    }

    .left-icon i {
        color: maroon;
        font-size: 24px;
         float: right;
    }
.logo {
    width: 200px; /* Lebar gambar */
    height: auto; /* Biarkan tinggi otomatis sesuai rasio aspek */
    display: block; /* Set gambar menjadi blok agar dapat menggunakan margin auto */
    margin: 0 auto; /* Atur margin otomatis secara horizontal untuk menengahkan gambar */
}
th, td {
    padding: 2px; /* Ubah sesuai kebutuhan */
}
tr {
    height: 5px; /* Ubah sesuai kebutuhan */
}
</style>
</head>
<body>
           
    <div class="form-group" style="display: flex; overflow: hidden;">
  

    <div class="container">
        <div class="form-container">
    <!-- Form untuk mengedit COA -->
    <form action="save_editcoa.php" method="post">
        <img src="logo.png" alt="Logo" class="logo"> 
         <h2>EDIT COA</h2>
        <!-- Sertakan input hidden untuk menyimpan ID COA -->
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <label for="account_code">Account Code:</label><br>
<input type="text" id="account_code" name="account_code" value="<?php echo $account_code; ?>" readonly><br><br>


        <label for="account_name">Account Name:</label><br>
        <input type="text" id="account_name" name="account_name" value="<?php echo $account_name; ?>"><br><br>
        
<label for="layer">Layer</label><br>
<select id="layer" name="layer">
    <?php
    // Loop melalui opsi layer dan atur opsi yang dipilih
    for ($i = 1; $i <= 4; $i++) {
        // Jika layer dari data sama dengan nilai saat ini dalam iterasi, tandai sebagai opsi terpilih
        $selected = ($layer == $i) ? "selected" : "";
        echo "<option value='$i' $selected>$i</option>";
    }
    ?>
</select><br><br>


        <label for="posisi">Neraca/P&amp;L:</label><br>
        <select id="posisi" name="posisi">
            <option value="neraca" <?php if ($posisi == 'neraca') echo 'selected'; ?>>Neraca</option>
            <option value="P&L" <?php if ($posisi == 'P&L') echo 'selected'; ?>>P&amp;L</option>
        </select><br><br>

        <label for="dc">Posisi:</label><br>
        <select id="dc" name="dc">
            <option value="debet" <?php if ($dc == 'debet') echo 'selected'; ?>>Debet</option>
            <option value="credit" <?php if ($dc == 'credit') echo 'selected'; ?>>Credit</option>
        </select><br><br>

        <label for="open">Opening Balance:</label><br>
        <input type="text" id="open" name="open" value="<?php echo $open; ?>"><br><br>
        
<label for="parent_account">Parent Account:</label><br>
<select id="parent_account" name="parent_account">
    <option value="">- No Parent -</option>
    <?php
    // Periksa apakah query menghasilkan baris-baris data
    if ($result->num_rows > 0) {
        // Loop melalui setiap baris hasil query
        while ($row = $result->fetch_assoc()) {
            // Tentukan apakah baris saat ini adalah parent account yang dipilih dari database
            $selected = ($row['account_code'] == $parent_account) ? "selected" : "";
            // Buat opsi untuk setiap baris data, dengan opsi yang dipilih jika sesuai dengan parent account dari data
            echo '<option value="' . $row['account_code'] . '" ' . $selected . '>' . $row['account_code'] . '</option>';
        }
    } else {
        // Jika tidak ada data yang ditemukan, tampilkan pesan
        echo '<option value="">Data tidak tersedia</option>';
    }
    ?>
</select><br><br>

        <input type="submit" value="Submit">
    </form>
    
     </div>
          </div>
          
             <div class="table-container">
            <a href="home.php" class="home-icon">
            <i class="fas fa-home"></i>
              <a href="home.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
             <h2>DAFTAR COA</h2>
        
     <?php
$conn = new mysqli($servername, $db_username, $db_password, $database);


if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$sql = "SELECT * FROM coa ORDER BY account_code";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Output data ke dalam tabel HTML
    echo "<table id='coaTable'>";
  echo "<thead><tr><th>Report Account</th><th>Transaction Account</th><th>Layer</th><th>Account Name</th><th>Neraca/P&L</th><th>Posisi</th><th>Opening Balance</th><th>Parent Account</th><th>Action</th></tr></thead>";
    echo "<tbody>";
    while($row = $result->fetch_assoc()) {
        // Output setiap baris data COA ke dalam tabel
        echo "<tr>";
        echo "<input type='hidden' name='account_code' value='" . $row["account_code"] . "'>";
        if ($row["layer"]!= 4) {
            echo "<td>" . htmlspecialchars($row["account_code"], ENT_QUOTES, 'UTF-8') . "</td>";
        } else {
            echo "<td></td>"; // Jika tidak layer 1, biarkan kolom kosong
     
        }
         if ($row["layer"] == 4) {
            echo "<td>" . $row["account_code"] . "</td>";
        } else {
            echo "<td></td>"; // Jika tidak layer 3, biarkan kolom kosong
        }
        echo "<td>" . $row["layer"] . "</td>";
        echo "<td>" . $row["account_name"] . "</td>";
        echo "<td>" . $row["posisi"] . "</td>";
        echo "<td>" . $row["dc"] . "</td>";
        echo "<td>" . $row["open"] . "</td>";
        echo "<td>" . $row["parent_account"] . "</td>";
        echo "<td style='max-width: 70px; white-space: nowrap;'>";

        // Tautan edit dengan menambahkan id dari setiap baris COA sebagai parameter
        echo "<a href='editcoa.php?id=" . $row["id"] . "' style='margin-top: 0px; float: left;'><i class='fa-solid fa-screwdriver-wrench' style='font-size: 20px;margin-right: 20px;  white-space: nowrap; color: blue;'></i></a>";
        
        // Tautan hapus dengan menambahkan id dari setiap baris COA sebagai parameter
        echo "<a href='deletecoa.php?id=" . $row["id"] . "' style='margin-top: 0px; float: right;'><i class='fa-solid fa-trash-can' style='font-size: 20px; margin-left: 10px;  white-space: nowrap; color: red;'></i></a>";
        
        echo "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
} else {
    echo "Tidak ada data COA.";
}
$conn->close();
?>

    

  
    </div>
    </div>
</body>
</html>
