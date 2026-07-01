<?php











require 'config1.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID tidak valid.");

// Update data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = $conn->real_escape_string($_POST['jenis']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi']);
    $sql = "UPDATE jenis_b SET jenis='$jenis', deskripsi='$deskripsi', updated_at=NOW() WHERE id_jenis=$id";
    if ($conn->query($sql) === TRUE) {
        $message = "Data berhasil diperbarui.";
    } else {
        $message = "Error: " . $conn->error;
    }
}

// Ambil data
$sql = "SELECT * FROM jenis_b WHERE id_jenis=$id";
$result = $conn->query($sql);
if ($result->num_rows === 0) die("Data tidak ditemukan.");
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Data Jenis</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body { font-family: Arial, sans-serif; margin: 20px; background-color: #f7f9fc; }
h1 { text-align: center; color: #333; }
.form-container { max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
input[type="text"], textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
button { background-color: #007bff; color: white; padding: 10px; border: none; border-radius: 5px; cursor: pointer; }
button:hover { background-color: #0056b3; }
.message { text-align: center; color: green; margin-top: 10px; }
.home-icon, .left-icon { font-size: 24px; color: maroon; position: absolute; top: 0; padding: 10px; }
.home-icon { left: 0; }
.left-icon { right: 0; }
</style>
</head>
<body>

<div class="form-container">
<a href="jenis.php" class="home-icon"><i class="fas fa-home"></i></a>
<a href="jenis.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<h1>Edit Data Jenis</h1>

<form method="POST">
    <input type="text" name="jenis" value="<?= htmlspecialchars($row['jenis']) ?>" required>
    <textarea name="deskripsi" rows="4"><?= htmlspecialchars($row['deskripsi']) ?></textarea>
    <button type="submit">Update</button>
</form>

<?php if (isset($message)) : ?>
    <p class="message"><?= $message ?></p>
<?php endif; ?>
</div>

</body>
</html>

<?php $conn->close(); ?>
