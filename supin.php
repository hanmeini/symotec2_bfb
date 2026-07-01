<?php



  
    



require_once 'config1.php';



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode = $conn->real_escape_string($_POST['kode']);
    $nama = $conn->real_escape_string($_POST['nama']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $npwp = !empty($_POST['npwp']) ? $conn->real_escape_string($_POST['npwp']) : NULL;
    

    $query = "INSERT INTO sup (kode, nama, alamat, npwp) VALUES ('$kode', '$nama', '$alamat', '$npwp' )";
    if ($conn->query($query)) {
        echo "<script>alert('Data berhasil disimpan!'); window.location.href = 'sup.php';</script>";
    } else {
        echo "<script>alert('Gagal menyimpan data: {$conn->error}');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Data Customer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f4f4f4;
        }
        .form-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background: #5cb85c;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
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

        .left-icon {
            position: absolute;
            right: 0;
            top: 0;
            padding-right: 10px;
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
    <a href="sup.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
        </div>
        
    <div class="form-container">
        <h1>Input Data supplier</h1>
        <form method="POST">
            <div class="form-group">
                <label for="kode">Kode</label>
                <input type="text" id="kode" name="kode" required>
            </div>
            <div class="form-group">
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="nama" required>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label for="npwp">NPWP</label>
                <input type="text" id="npwp" name="npwp" >
            </div>
            
            <div style="display:flex; gap:10px;">
                <button type="submit">Simpan</button>
                <a href="sup.php" style="width: 100%; padding: 10px; background: #d9534f; color: #fff; border: none; border-radius: 5px; cursor: pointer; text-align: center; text-decoration: none; font-size: 16px; box-sizing: border-box;">Batal</a>
            </div>
        </form>
    </div>
</body>
</html>
