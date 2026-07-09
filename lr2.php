<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Load konfigurasi dari config.php
require_once 'config.php';



// Ambil variabel dari environment
$servername = getenv('DB_HOST') ?: die("Kesalahan: DB_HOST tidak ditemukan.");
$db_username = getenv('DB_USER') ?: die("Kesalahan: DB_USER tidak ditemukan.");
$db_password = getenv('DB_PASS') ?: die("Kesalahan: DB_PASS tidak ditemukan.");
$database = getenv('DB_NAME') ?: die("Kesalahan: DB_NAME tidak ditemukan.");


// Buat koneksi ke database pertama
$conn = new mysqli($servername, $db_username, $db_password, $database);

// Periksa koneksi pertama
if ($conn->connect_error) {
    die("Koneksi ke database pertama gagal: " . $conn->connect_error);
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
           <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
    <title>LR</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1800px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        select, button {
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #007bff;
            color: #fff;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #e6f7ff;
        }
          .home-icon1, .left-icon {
            position: absolute;
            top: 0;
            color: maroon;
            font-size: 24px;
        }
        .home-icon1 {
            left: 10px;
        }
        .left-icon {
            right: 10px;
        }

    </style>
</head>
<body>
<div class='table-container'>
    <a href='home.php' class='home-icon1'>
        <i class='fas fa-home'></i>
    </a>
    <a href='home.php' class='left-icon'>
        <i class='fa-solid fa-circle-left'></i>
    </a>
        </div>
<div class="container">
    <h2>LABA RUGI</h2>
<?php
// Handle GET parameter untuk filter
$bulan_awal = $_GET['bulan_awal'] ?? date('m');
$bulan_akhir = $_GET['bulan_akhir'] ?? $bulan_awal;
$tahun_terpilih = $_GET['tahun'] ?? date('Y');
$loc_terpilih = $_GET['location'] ?? '';
$dev_terpilih = $_GET['devisi'] ?? '';

// Array bulan
$bulan_arr = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];
?>

<form method="get">
    <!-- Pilihan Bulan -->
  <!-- Pilihan Bulan Awal -->
<label>Bulan Awal:</label>
<select name="bulan_awal">
    <?php
    foreach ($bulan_arr as $key => $value) {
        $selected = ($key == $bulan_awal) ? 'selected' : '';
        echo "<option value=\"$key\" $selected>$value</option>";
    }
    ?>
</select>

<!-- Pilihan Bulan Akhir -->
<label>Bulan Akhir:</label>
<select name="bulan_akhir">
    <?php
    foreach ($bulan_arr as $key => $value) {
        $selected = ($key == $bulan_akhir) ? 'selected' : '';
        echo "<option value=\"$key\" $selected>$value</option>";
    }
    ?>
</select>



    <!-- Pilihan Tahun -->
    <select name="tahun">
        <?php
        for ($i = 2024; $i <= date('Y'); $i++) {
            $selected = ($i == $tahun_terpilih) ? 'selected' : '';
            echo "<option value=\"$i\" $selected>$i</option>";
        }
        ?>
    </select>

    <!-- Pilihan Location -->
    <select name="location">
        <option value="">All Location</option>
        <?php
         $sql2 = "SELECT idl, nama_cabang FROM location";
                $result2 = $conn->query($sql2);
                if ($result2->num_rows > 0) {
                    while ($row = $result2->fetch_assoc()) {
                        echo "<option value='{$row["idl"]}'>{$row["nama_cabang"]}</option>";
                    }
                } else {
                    echo "<option value='' disabled>Tidak ada cabang tersedia</option>";
                }
        ?>
    </select>

   

    <button type="submit">Tampilkan</button>
</form>

<!-- Checkbox Kontrol Layer -->
<div style="margin-top: 15px;">
    <label><input type="checkbox" id="toggleLayer1" checked> Tampilkan Layer 1</label>
    <label><input type="checkbox" id="toggleLayer2" checked> Tampilkan Layer 2</label>
    <label><input type="checkbox" id="toggleLayer3" checked> Tampilkan Layer 3</label>
    <label><input type="checkbox" id="toggleLayer4" checked> Tampilkan Layer 4</label>
</div>

<!-- Link Export ke Excel -->
<div style="margin-top: 15px;">
   <a href="export_lr.php?bulan_awal=<?= urlencode($bulan_awal) ?>&bulan_akhir=<?= urlencode($bulan_akhir) ?>&tahun=<?= urlencode($tahun_terpilih) ?>&location=<?= urlencode($loc_terpilih) ?>&devisi=<?= urlencode($dev_terpilih) ?>" target="_blank">
    <button type="button" style="background-color: #28a745; color: white;">Export Excel</button>
</a>


</div>

     <?php

$bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
$tahunan = ($bulan === 0);

$selected_location = $_GET['location'] ?? '';
$selected_devisi = $_GET['devisi'] ?? '';

if ($selected_location === 'ALL') $selected_location = '';
if ($selected_devisi === 'ALL') $selected_devisi = '';

if ($tahunan) {
    $tanggal_cutoff_awal = ($tahun - 1) . "-12-31";
    $tanggal_cutoff_akhir = $tahun . "-12-31";
} else {
   $tanggal_cutoff_awal = "$tahun_terpilih-" . str_pad($bulan_awal, 2, '0', STR_PAD_LEFT) . "-01";
$tanggal_cutoff_akhir = date("Y-m-t", strtotime("$tahun_terpilih-" . str_pad($bulan_akhir, 2, '0', STR_PAD_LEFT) . "-01"));

}



echo "<p><strong>Periode:</strong> " . date("d M Y", strtotime($tanggal_cutoff_awal)) . " s/d " . date("d M Y", strtotime($tanggal_cutoff_akhir)) . "</p>";

$filter_location = !empty($selected_location) ? "AND j.location = ?" : "";
$filter_devisi = !empty($selected_devisi) ? "AND j.devisi = ?" : "";


    $query = "
    SELECT c.account_code, c.account_name, c.layer, c.parent_account,
        COALESCE(SUM(CASE WHEN j.tanggal < ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_awal,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS total_debet,
        COALESCE(SUM(CASE WHEN j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS total_kredit,
        COALESCE(SUM(CASE WHEN j.tanggal <= ? THEN j.debet - j.kredit ELSE 0 END), 0) AS saldo_akhir,
        COALESCE(SUM(CASE WHEN c.posisi = 'P&L' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS lr_debet,
        COALESCE(SUM(CASE WHEN c.posisi = 'P&L' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS lr_kredit,
        COALESCE(SUM(CASE WHEN c.posisi = 'neraca' AND j.tanggal BETWEEN ? AND ? THEN j.debet ELSE 0 END), 0) AS debet_neraca,
        COALESCE(SUM(CASE WHEN c.posisi = 'neraca' AND j.tanggal BETWEEN ? AND ? THEN j.kredit ELSE 0 END), 0) AS kredit_neraca
    FROM coa c
    LEFT JOIN jurnal j ON c.account_code = j.coa  AND j.journal_number IS NOT NULL 
    WHERE c.account_code NOT LIKE '1%' 
    AND c.account_code NOT LIKE '2%' 
    AND c.account_code NOT LIKE '3%'
        $filter_location
        $filter_devisi
    GROUP BY c.account_code, c.account_name, c.layer, c.parent_account
      HAVING NOT (c.layer = 4 AND lr_debet = 0 AND lr_kredit = 0)
    ORDER BY c.account_code ASC
";

$stmt = $conn->prepare($query);
if (!$stmt) die("Kesalahan pada prepare statement: " . $conn->error);

$param_types = 'ssssssssssssss';
$params = [
    $tanggal_cutoff_awal, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir, $tanggal_cutoff_awal,
    $tanggal_cutoff_akhir, $tanggal_cutoff_awal, $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal, $tanggal_cutoff_akhir
];

if (!empty($selected_location)) {
    $param_types .= 's';
    $params[] = $selected_location;
}
if (!empty($selected_devisi)) {
    $param_types .= 's';
    $params[] = $selected_devisi;
}

$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$stmt->bind_result(
    $account_code, $account_name, $layer, $parent_account, $saldo_awal,
    $total_debet, $total_kredit, $saldo_akhir,
    $lr_debet, $lr_kredit, $debet_neraca, $kredit_neraca
);

$data = [];
while ($stmt->fetch()) {
    $first_digit = substr($account_code, 0, 1);
    $saldo_awal_debet = 0;
    $saldo_awal_kredit = 0;

    if (in_array($first_digit, ['1', '2', '3'])) {
        if ($first_digit === '1') {
            $saldo_awal_debet = max(0, (float)$saldo_awal);
        } elseif (in_array($first_digit, ['2', '3'])) {
            $saldo_awal_kredit = max(0, (float)$saldo_awal);
        }
    }

    if (in_array($first_digit, ['1', '5', '7', '9'])) {
        $saldo_akhir = $saldo_awal_debet + (float)$total_debet - (float)$total_kredit;
    } elseif (in_array($first_digit, ['2', '4', '6', '8'])) {
        $saldo_akhir = $saldo_awal_kredit - (float)$total_debet + (float)$total_kredit;
    }

    if (in_array($first_digit, ['4', '6', '8'])) {
        $lr_kredit = $saldo_akhir;
    } else {
        $lr_kredit = 0;
    }

    if (in_array($first_digit, ['5',  '7', '9'])) {
        $lr_debet = $saldo_akhir;
    } else {
        $lr_debet = 0;
    }

    $debet_neraca = $first_digit === '1' ? $saldo_akhir : 0;
    $kredit_neraca = in_array($first_digit, ['2', '3']) ? $saldo_akhir : 0;

    $data[$account_code] = [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'layer' => $layer,
        'parent_account' => $parent_account,
        'saldo_awal_debet' => $saldo_awal_debet,
        'saldo_awal_kredit' => $saldo_awal_kredit,
        'total_debet' => (float)$total_debet,
        'total_kredit' => (float)$total_kredit,
        'saldo_akhir' => $saldo_akhir,
        'lr_debet' => $lr_debet,
        'lr_kredit' => $lr_kredit,
        'debet_neraca' => (float)$debet_neraca,
        'kredit_neraca' => (float)$kredit_neraca
        

    ];
}
ksort($data);

// Akumulasi Parent
foreach ($data as $key => &$values) {
    if ($values['layer'] == 4) continue;

    foreach ($data as $child_key => $child_values) {
        $match = false;
        if ($values['layer'] == 1 && substr($child_values['account_code'], 0, 3) === substr($values['account_code'], 0, 3)) $match = true;
        if ($values['layer'] == 2 && substr($child_values['account_code'], 0, 6) === substr($values['account_code'], 0, 6)) $match = true;
        if ($values['layer'] == 3 && substr($child_values['account_code'], 0, 9) === substr($values['account_code'], 0, 9)) $match = true;

        if ($match) {
            $values['saldo_awal_debet'] += $child_values['saldo_awal_debet'];
            $values['saldo_awal_kredit'] += $child_values['saldo_awal_kredit'];
            $values['total_debet'] += $child_values['total_debet'];
            $values['total_kredit'] += $child_values['total_kredit'];
            $values['saldo_akhir'] += $child_values['saldo_akhir'];
            $values['lr_debet'] += $child_values['lr_debet'];
            $values['lr_kredit'] += $child_values['lr_kredit'];
            $values['debet_neraca'] += $child_values['debet_neraca'];
            $values['kredit_neraca'] += $child_values['kredit_neraca'];
        }
    }
}
unset($values);



// Hitung total layer 4
$total = [
    'saldo_awal_debet' => 0,
    'saldo_awal_kredit' => 0,
    'total_debet' => 0,
    'total_kredit' => 0,
    'saldo_akhir' => 0,
    'lr_debet' => 0,
    'lr_kredit' => 0,
    'debet_neraca' => 0,
    'kredit_neraca' => 0
];

foreach ($data as $row) {
    if ($row['layer'] == 4) {
        $total['saldo_awal_debet'] += $row['saldo_awal_debet'];
        $total['saldo_awal_kredit'] += $row['saldo_awal_kredit'];
        $total['total_debet'] += $row['total_debet'];
        $total['total_kredit'] += $row['total_kredit'];
        $total['saldo_akhir'] += $row['saldo_akhir'];
        $total['lr_debet'] += $row['lr_debet'];
        $total['lr_kredit'] += $row['lr_kredit'];
        $total['debet_neraca'] += $row['debet_neraca'];
        $total['kredit_neraca'] += $row['kredit_neraca'];
    }
}


echo "<table border='1'>
<tr>
    <th>Layer 1</th>
    <th>Layer 2</th>
    <th>Layer 3</th>
    <th>Layer 4</th>
    <th>Nama Akun</th>
    <th>LR Debet</th>
    <th>LR Kredit</th>
</tr>";

foreach ($data as $row) {
    $kode_akun1 = $row['layer'] == 1 ? $row['account_code'] : '';
    $kode_akun2 = $row['layer'] == 2 ? $row['account_code'] : '';
    $kode_akun3 = $row['layer'] == 3 ? $row['account_code'] : '';
    $kode_akun4 = $row['layer'] == 4 ? $row['account_code'] : '';

    $highlight = '';
    if (!empty($kode_akun1)) {
        $highlight .= 'background-color: #d4edda; font-weight: bold;';
    }
    if (!empty($kode_akun2)) {
        $highlight .= 'background-color: #f8d7da; font-weight: bold;';
    }
    if (!empty($kode_akun3)) {
        $highlight .= 'background-color: #d1ecf1; font-weight: bold;';
    }
    $highlight = !empty($highlight) ? ' style="' . $highlight . '"' : '';

    // Tambahkan class layer
    $layer_class = "layer" . $row['layer'];

    echo "<tr class=\"$layer_class\"$highlight>
            <td>{$kode_akun1}</td>
            <td>{$kode_akun2}</td>
            <td>{$kode_akun3}</td>
            <td>{$kode_akun4}</td>
            <td>{$row['account_name']}</td>
           

            <td align='right'>" . number_format($row['lr_debet'], 0) . "</td>
            <td align='right'>" . number_format($row['lr_kredit'], 0) . "</td>
       
        </tr>";
}


echo "<tr style='font-weight:bold; background:#e0e0e0;'>
    <td colspan='5' align='center'>TOTAL</td>
    

    <td align='right'>" . number_format($total['lr_debet'], 0) . "</td>
    <td align='right'>" . number_format($total['lr_kredit'], 0) . "</td>

</tr>";

// Baris Laba Rugi Bulan Ini
$laba_rugi_bulan_ini = $total['lr_kredit'] - $total['lr_debet'];
echo "<tr style='font-weight:bold; background:#d0ffd0;'>
    <td colspan='6' align='center'>LABA RUGI BULAN INI</td>
    <td colspan='1' align='right'>" . number_format($laba_rugi_bulan_ini, 0) . "</td>
</tr>";


echo "</table>";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulan_post = $_POST['bulan'] ?? '';
    $tahun_post = $_POST['tahun'] ?? '';

    if (!empty($bulan_post) && !empty($tahun_post)) {
        // Cek apakah sudah pernah close
        $check_query = "
            SELECT COUNT(*) FROM close
            WHERE bulan = ? AND tahun = ?
        ";
        $check_stmt = $conn->prepare($check_query);
        if (!$check_stmt) die("Kesalahan pada prepare statement: " . $conn->error);

        $check_stmt->bind_param('ss', $bulan_post, $tahun_post);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            echo "<script>alert('Data untuk bulan $bulan_post dan tahun $tahun_post sudah pernah di-close. Proses gagal.');</script>";
        } else {
            // Insert sekali saja
            $insert_query = "
                INSERT INTO close (bulan, tahun)
                VALUES (?, ?)
            ";
            $insert_stmt = $conn->prepare($insert_query);
            if (!$insert_stmt) die("Kesalahan pada prepare statement: " . $conn->error);

            $insert_stmt->bind_param('ss', $bulan_post, $tahun_post);
            if ($insert_stmt->execute()) {
                echo "<script>alert('Data berhasil di-close untuk bulan $bulan_post dan tahun $tahun_post.');</script>";
            } else {
                echo "<script>alert('Terjadi kesalahan saat close: " . $insert_stmt->error . "');</script>";
            }
            $insert_stmt->close();
        }
    }
}



// Form submit untuk mengirim data bulan dan tahun ke tabel close


$stmt->close();
$conn->close();


?>
</div>
<script>
  function toggleLayer(layerClass, checkboxId) {
    const rows = document.querySelectorAll('.' + layerClass);
    const show = document.getElementById(checkboxId).checked;
    rows.forEach(row => {
      row.style.display = show ? '' : 'none';
    });
  }
 document.getElementById('toggleLayer1').addEventListener('change', () => {
    toggleLayer('layer1', 'toggleLayer1');
  });
  document.getElementById('toggleLayer2').addEventListener('change', () => {
    toggleLayer('layer2', 'toggleLayer2');
  });
  document.getElementById('toggleLayer3').addEventListener('change', () => {
    toggleLayer('layer3', 'toggleLayer3');
  });
  document.getElementById('toggleLayer4').addEventListener('change', () => {
    toggleLayer('layer4', 'toggleLayer4');
  });
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = ['#toggleLayer1', '#toggleLayer2', '#toggleLayer3', '#toggleLayer4'];
    checkboxes.forEach(selector => {
        document.querySelector(selector).addEventListener('change', function() {
            const layer = this.id.replace('toggleLayer', '');
            const rows = document.querySelectorAll('.layer' + layer);
            rows.forEach(row => {
                row.style.display = this.checked ? '' : 'none';
            });
        });
    });
});
</script>
</body>
</html>

