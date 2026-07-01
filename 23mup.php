<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';
require 'vendor/autoload.php'; // pastikan PHPSpreadsheet sudah di-install

use PhpOffice\PhpSpreadsheet\IOFactory;









$rows_valid = [];
$rows_invalid = [];
$berhasil = 0;
$show_process_button = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file_tmp = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($file_tmp);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    foreach ($rows as $index => $row) {
        if ($index === 0) continue; // Lewati header

        $inv = trim($row[0]);
        $bukpot = trim($row[1]);

        if (empty($inv) || empty($bukpot)) {
            $rows_invalid[] = [
                'baris' => $index + 1,
                'inv' => $inv,
                'alasan' => 'Kolom INV atau Bukpot kosong'
            ];
            continue;
        }

        $inv_escaped = $conn->real_escape_string($inv);

        // Cek apakah INV ada di tabel BELI
        $cek_inv = $conn->query("SELECT bukpot23 FROM BELI WHERE inv = '$inv_escaped'");

        if ($cek_inv && $cek_inv->num_rows === 0) {
            $rows_invalid[] = [
                'baris' => $index + 1,
                'inv' => $inv,
                'alasan' => 'INV tidak ditemukan'
            ];
        } else {
            $data_inv = $cek_inv->fetch_assoc();
            if (!empty($data_inv['bukpot23'])) {
                $rows_invalid[] = [
                    'baris' => $index + 1,
                    'inv' => $inv,
                    'alasan' => 'Sudah memiliki bukpot23'
                ];
            } else {
                $rows_valid[] = [
                    'baris' => $index + 1,
                    'inv' => $inv,
                    'bukpot' => $bukpot
                ];
            }
        }
    }

    if (empty($rows_invalid)) {
        $show_process_button = true;
        $_SESSION['rows_valid'] = $rows_valid; // simpan sementara
    }
}

if (isset($_POST['proses']) && isset($_SESSION['rows_valid'])) {
    $rows_valid = $_SESSION['rows_valid'];
    foreach ($rows_valid as $row) {
        $inv_escaped = $conn->real_escape_string($row['inv']);
        $bukpot_escaped = $conn->real_escape_string($row['bukpot']);
        $update = $conn->query("UPDATE BELI SET bukpot23 = '$bukpot_escaped' WHERE inv = '$inv_escaped' AND bukpot23 IS NULL");
        if ($update) $berhasil++;
    }
    unset($_SESSION['rows_valid']); // hapus setelah selesai
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Upload Bukpot PPH23</title>
    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #eef2f3;
            margin: 0;
            padding: 0;
        }
        header {
            background: #4CAF50;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        header h2 {
            margin: 0;
            font-size: 1.4em;
        }
        header a {
            background: white;
            color: #4CAF50;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        header a:hover {
            background: #e9e9e9;
        }
        main {
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
        }
        form {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        input[type="file"] {
            padding: 10px;
            background: #f8f8f8;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin-bottom: 15px;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 10px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        button:hover {
            background: #45a049;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background: #4CAF50;
            color: white;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .fail {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <header>
        <h2>📄 Upload Bukpot PPH23</h2>
        <a href="home.php">🏠 Home</a>
    </header>

    <main>
        <?php if (!isset($_POST['proses'])): ?>
        <form method="post" enctype="multipart/form-data">
            <p><b>Format Excel:</b><br>
            Kolom A: INV<br>
            Kolom B: Bukpot23</p>
            <input type="file" name="excel_file" accept=".xlsx" required><br>
            <button type="submit">Validasi Data</button>
        </form>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['proses'])): ?>
            <?php if (!empty($rows_valid)): ?>
                <div class="success">✅ Data valid: <?php echo count($rows_valid); ?> baris</div>
            <?php endif; ?>

            <?php if (!empty($rows_invalid)): ?>
                <div class="fail">❌ Data tidak valid: <?php echo count($rows_invalid); ?> baris</div>
                <table>
                    <tr>
                        <th>Baris</th>
                        <th>INV</th>
                        <th>Alasan</th>
                    </tr>
                    <?php foreach ($rows_invalid as $r): ?>
                    <tr>
                        <td><?php echo $r['baris']; ?></td>
                        <td><?php echo htmlspecialchars($r['inv']); ?></td>
                        <td><?php echo htmlspecialchars($r['alasan']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>

            <?php if ($show_process_button): ?>
                <form method="post">
                    <button type="submit" name="proses">Proses Update ke Database</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (isset($_POST['proses'])): ?>
            <div class="success">
                ✅ Selesai! Berhasil update <strong><?php echo $berhasil; ?></strong> baris ke database.
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
