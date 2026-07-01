<?php
require_once 'config1.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID tidak valid");
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kode = $conn->real_escape_string($_POST['kode']);
    $nama = $conn->real_escape_string($_POST['nama']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $npwp = !empty($_POST['npwp']) ? $conn->real_escape_string($_POST['npwp']) : NULL;
   
    $query = "UPDATE sup SET kode='$kode', nama='$nama', alamat='$alamat', npwp='$npwp' WHERE id=$id";
    if ($conn->query($query)) {
        echo "<script>alert('Data Supplier berhasil diupdate!'); window.location.href = 'sup.php';</script>";
    } else {
        $msg = "Gagal mengupdate data: " . $conn->error;
    }
}

$result = $conn->query("SELECT * FROM sup WHERE id=$id");
$data = $result->fetch_assoc();
if (!$data) {
    die("Data tidak ditemukan");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Data Supplier</title>
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
            margin: 60px auto;
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
            box-sizing: border-box;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        button, .btn-cancel {
            width: 100%;
            padding: 10px;
            background: #5cb85c;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            font-size: 16px;
        }
        button:hover { background: #4cae4c; }
        .btn-cancel { background: #d9534f; }
        .btn-cancel:hover { background: #c9302c; }

        .home-icon1 {
            position: absolute;
            left: 0;
            top: 0;
            padding-left: 10px;
            color: maroon;
            font-size: 24px;
            margin-top: 15px;
        }
        .left-icon {
            position: absolute;
            right: 0;
            top: 0;
            padding-right: 10px;
            color: maroon;
            font-size: 24px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
        <a href="sup.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    </div>
        
    <div class="form-container">
        <h1>Edit Data Supplier</h1>
        <?php if($msg): ?><p style="color:red; text-align:center;"><?= $msg ?></p><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="kode">Kode</label>
                <input type="text" id="kode" name="kode" value="<?= htmlspecialchars($data['kode']) ?>" required>
            </div>
            <div class="form-group">
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="nama" value="<?= htmlspecialchars($data['nama']) ?>" required>
            </div>
            <div class="form-group">
                <label for="alamat">Alamat</label>
                <textarea id="alamat" name="alamat" rows="3" required><?= htmlspecialchars($data['alamat']) ?></textarea>
            </div>
            <div class="form-group">
                <label for="npwp">NPWP</label>
                <input type="text" id="npwp" name="npwp" value="<?= htmlspecialchars($data['npwp']) ?>" required>
            </div>
            
            <div class="btn-group">
                <button type="submit">Update</button>
                <a href="sup.php" class="btn-cancel">Batal</a>
            </div>
        </form>
    </div>
</body>
</html>
