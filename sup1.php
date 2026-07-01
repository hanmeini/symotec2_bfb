<?php



  
    



require_once 'config1.php';



// Proses pencarian
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$query = "SELECT * FROM location";
if (!empty($search)) {
    $query .= " WHERE kodec LIKE '%$search%' OR location LIKE '%$search%' OR alamat LIKE '%$search%'";
}
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Location</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f4f4f4;
        }
        .search-container {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        .search-container input[type="text"] {
            width: calc(100% - 120px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .search-container button {
            padding: 10px 20px;
            background: #5cb85c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .search-container button:hover {
            background: #4cae4c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background: #5cb85c;
            color: #fff;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        tr:hover {
            background: #f1f1f1;
        }
        .add-button {
            display: block;
            width: fit-content;
            margin-bottom: 10px;
            padding: 10px 15px;
            background: #5cb85c;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
        }
        .add-button:hover {
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
    <script>
        function selectsup(kode, lokasi) {
            if (window.opener) {
                window.opener.setsSupCode(kode, lokasi); // Panggil fungsi di halaman utama
                window.close(); // Tutup popup
            } else {
                alert('Tidak dapat mengirim data ke halaman utama.');
            }
        }
    </script>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1">
            <i class="fas fa-home"></i>
        </a>
        <a href="home.php" class="left-icon">
            <i class="fa-solid fa-circle-left"></i>
        </a>
    </div>

    <h1>Cabang</h1>

    <div class="search-container">
        <form method="GET">
            <input type="text" name="search" placeholder="Cari lokasi..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Cari</button>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kode Cabang</th>
                <th>Lokasi</th>
                <th>Alamat</th>
                <th>Telepon</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['kodec']) ?></td>
                        <td><?= htmlspecialchars($row['location']) ?></td>
                        <td><?= htmlspecialchars($row['alamat']) ?></td>
                        <td><?= htmlspecialchars($row['telp']) ?></td>
                        <td>
                            <button 
                                class="select-button" 
                                onclick="selectsup('<?= htmlspecialchars($row['kodec']) ?>', '<?= htmlspecialchars($row['location']) ?>')">
                                Pilih
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">Tidak ada data ditemukan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
