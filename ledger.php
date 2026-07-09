<?php
session_start([
    'cookie_lifetime' => 86400, // Cookie berlaku selama 1 hari (86400 detik)
    'cookie_httponly' => true, // Cookie hanya dapat diakses melalui HTTP (JavaScript tidak bisa membaca)
    'cookie_secure' => isset($_SERVER['HTTPS']), // Cookie hanya dikirim melalui HTTPS jika tersedia
    'use_only_cookies' => true, // Hanya gunakan cookie untuk session (tanpa URL session ID)
    'use_strict_mode' => true, // Cegah sesi yang dicuri digunakan kembali
]);
$allowed_referer_domain = "https://bfb.symotech.my.id/";

// Periksa apakah HTTP_REFERER ada dan berasal dari domain yang diizinkan
if (!isset($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $allowed_referer_domain) !== 0) {
    header("Location: https://bfb.symotech.my.id");
    exit();
}

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config.php';

// koneksi
$conn = new mysqli(
    getenv('DB_HOST'),
    getenv('DB_USER'),
    getenv('DB_PASS'),
    getenv('DB_NAME')
);

// HANDLE ACTION DI SINI (SEBELUM HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action     = $_POST['action'] ?? 'view';
    $coa        = $_POST['coa'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date   = $_POST['end_date'] ?? '';
    $location   = $_POST['location'] ?? '';
    $devisi     = $_POST['devisi'] ?? '';

    if ($action === 'excel') {

        $query = http_build_query([
            'coa' => $coa,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'location' => $location,
            'devisi' => $devisi
        ]);

        header("Location: download_ledger.php?$query");
        exit(); // WAJIB
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Ledger</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .container {
            max-width: 1900px;
            margin: 10px auto;
            padding: 10px;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .home-icon1, .left-icon {
            position: absolute;
            top: 20px;
            font-size: 24px;
            color: maroon;
        }

        .home-icon1 {
            left: 20px;
        }

        .left-icon {
            right: 20px;
        }

        form {
            margin-bottom: 20px;
        }

        label {
            font-weight: bold;
            margin-right: 10px;
        }

        input[type="text"], input[type="date"], input[type="select"] {
            width: calc(100% - 110px);
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        @media screen and (max-width: 600px) {
            input[type="text"], input[type="date"] {
                width: 100%;
            }

            button {
                width: 100%;
                padding: 12px;
            }
        }
        .form-layout {
   
    align-items: center;
}

.form-group {
    display: flex;
    flex-direction: column;
}

label {
    font-weight: bold;
    margin-bottom: 5px;
}

input[type="text"], input[type="date"], input[type="select"] {
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
}

select {
    font-weight: bold;
    margin-bottom: 5px;
    height: 30px;
    padding: 0 5px;
    line-height: 20px;
    box-sizing: border-box;
     font-size: 14px;
}

button {
    max-width: 300px;
    padding: 10px 20px;
    font-size: 16px;
    background-color: #4CAF50;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s;
}

button:hover {
    background-color: #45a049;
}

.form-group.full-width {
    grid-column: span 2; /* Membuat tombol penuh pada dua kolom */
    text-align: center;
}

.form-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap; /* Biar responsif */
}

.form-layout, .form-download {
    background-color: #f9f9f9;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    flex: 1;
    min-width: 280px; /* Supaya nggak kependekan */
}

.form-download {
    max-width: 300px;
}

.form-download input[type="text"], 
.form-download input[type="date"] {
    width: 100%;
    padding: 8px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-sizing: border-box;
}

.form-download button {
    width: 100%;
    padding: 10px;
    background-color: maroon;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.form-download button:hover {
    background-color: darkred;
}

/* Responsive Mode */
@media screen and (max-width: 768px) {
    .form-wrapper {
        flex-direction: column;
    }

    .form-download, .form-layout {
        width: 100%;
    }
}


@media screen and (max-width: 600px) {
    .form-layout {
        grid-template-columns: 1fr; /* Satu kolom untuk layar kecil */
    }

    .form-group.full-width {
        grid-column: span 1;
    }
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

<?php
$coa        = $_POST['coa'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date   = $_POST['end_date'] ?? '';
$location   = $_POST['location'] ?? '';
$devisi     = $_POST['devisi'] ?? '';
?>

<div class="container">
    <h1>Laporan Ledger</h1>

    <div class="form-wrapper">

        <form method="POST" class="form-layout">

            <div class="form-group">
                <label>COA:</label>
                <select name="coa" required>
                    <option value="">Pilih COA</option>

                    <?php
                    $sql_coa = "SELECT account_code, account_name 
                                FROM coa 
                                WHERE layer = 4 
                                ORDER BY account_code ASC";

                    $result_coa = $conn->query($sql_coa);

                    while ($row = $result_coa->fetch_assoc()) {
                        $selected = ($coa == $row['account_code']) ? 'selected' : '';
                        echo "<option value='".htmlspecialchars($row['account_code'])."' $selected>
                                ".htmlspecialchars($row['account_code'])." - ".htmlspecialchars($row['account_name'])."
                              </option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Tanggal Mulai:</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
            </div>

            <input type="hidden" name="location" value="<?= htmlspecialchars($location) ?>">
            <input type="hidden" name="devisi" value="<?= htmlspecialchars($devisi) ?>">

            <div class="form-group">
                <label>Tanggal Selesai:</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
            </div>

            <div class="form-group full-width" style="display:flex;gap:10px;justify-content:center;">
                
                <button type="submit" name="action" value="view">
                    🔍 Cari
                </button>

                <button type="submit" name="action" value="excel" style="background:maroon;">
                    <i class="fa-solid fa-file-excel"></i> Download Excel
                </button>

            </div>

        </form>
    </div>


<?php
// ================= TAMPILKAN DATA (VIEW SAJA) =================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_POST['action'] ?? '') !== 'excel') {

    // ================= AMBIL NAMA AKUN =================
    $stmt_coa = $conn->prepare("SELECT account_name FROM coa WHERE account_code = ?");
    $stmt_coa->bind_param("s", $coa);
    $stmt_coa->execute();
    $stmt_coa->bind_result($account_name1);
    $stmt_coa->fetch();
    $stmt_coa->close();

    // ================= SALDO AWAL =================
    $sql_saldo_awal = "
        SELECT COALESCE(SUM(debet),0) - COALESCE(SUM(kredit),0)
        FROM jurnal
        WHERE coa = ? 
        AND tanggal < ? 
        AND journal_number IS NOT NULL
    ";

    $stmt_saldo = $conn->prepare($sql_saldo_awal);
    $stmt_saldo->bind_param("ss", $coa, $start_date);
    $stmt_saldo->execute();
    $stmt_saldo->bind_result($saldo_awal);
    $stmt_saldo->fetch();
    $stmt_saldo->close();

    $saldo_awal = (float)$saldo_awal;

    if (!in_array(substr($coa,0,1), ['1','5','7','9'])) {
        $saldo_awal = -$saldo_awal;
    }

    // ================= QUERY =================
    $sql = "
        SELECT tanggal, journal_number, debet, kredit, keterangan, posting
        FROM jurnal
        WHERE coa = ? 
        AND tanggal >= ?
        AND journal_number IS NOT NULL
    ";

    if ($end_date) {
        $sql .= " AND tanggal <= ?";
    }

    $sql .= " ORDER BY tanggal";

    $stmt = $conn->prepare($sql);

    if ($end_date) {
        $stmt->bind_param("sss", $coa, $start_date, $end_date);
    } else {
        $stmt->bind_param("ss", $coa, $start_date);
    }

    $stmt->execute();
    $stmt->bind_result($tanggal,$journal_number,$debet,$kredit,$keterangan,$posting);

    echo "<h2>Ledger COA: ".htmlspecialchars($coa)." - ".htmlspecialchars($account_name1)."</h2>";

    echo "<table>
        <tr>
            <th>Tanggal</th>
            <th>No Jurnal</th>
            <th>Debet</th>
            <th>Kredit</th>
            <th>Saldo</th>
            <th>Keterangan</th>
        </tr>";

    echo "<tr>
        <td colspan='4'><b>Saldo Awal</b></td>
        <td><b>".number_format($saldo_awal,0,',','.')."</b></td>
        <td></td>
    </tr>";

    $saldo = $saldo_awal;

    while ($stmt->fetch()) {

        if (in_array(substr($coa,0,1), ['1','5','7','9'])) {
            $saldo += $debet - $kredit;
        } else {
            $saldo += $kredit - $debet;
        }

        echo "<tr>
            <td>$tanggal</td>
            <td>$journal_number</td>
            <td align='right'>".number_format($debet,0,',','.')."</td>
            <td align='right'>".number_format($kredit,0,',','.')."</td>
            <td align='right'><b>".number_format($saldo,0,',','.')."</b></td>
            <td>".htmlspecialchars($keterangan)."</td>
        </tr>";
    }

    echo "</table>";

    $stmt->close();
}
?>

</div>
</div>

</body>
</html>
