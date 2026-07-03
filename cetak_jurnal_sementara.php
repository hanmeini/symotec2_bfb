<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

// ================= VALIDASI REFERER =================




// ================= VALIDASI LOGIN =================
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// ================= LOAD CONFIG =================
require_once 'config1.php';

// KONEKSI DB sudah di-handle oleh config1.php

// ================= AMBIL PARAMETER =================
$jurnal_sementara = $_GET['kode_transaksi'] ?? '';

// ================= QUERY JOIN (JURNAL + COA + SUPP) =================
$sql = "
    SELECT 
        j.id,
        j.journal_number,
        j.jurnal_sementara,
        j.tanggal,
        j.keterangan,
        j.lampiran,
        j.coa,
        c.account_name AS nama_akun,
        j.debet,
        j.kredit,
        j.posting,
        j.kode_booking,
        j.supcust,
        s.nama AS nama_supcust
    FROM jurnal j
    LEFT JOIN coa c ON j.coa = c.account_code
    LEFT JOIN sup s ON j.supcust = s.kode
    WHERE j.jurnal_sementara LIKE ?
";

$stmt = $conn->prepare($sql);

$param = "%" . $jurnal_sementara . "%";
$stmt->bind_param("s", $param);

$stmt->execute();
$result = $stmt->get_result();

// ================= FETCH DATA =================
$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'jurnal_sementara' => $row['jurnal_sementara'],
        'tanggal' => $row['tanggal'],
        'keterangan' => $row['keterangan'],
        'lampiran' => $row['lampiran'],
        'supcust' => $row['supcust'] ?? '',
        'nama_supcust' => $row['nama_supcust'] ?? 'Tidak ditemukan',
        'coa' => $row['coa'],
        'account_name' => $row['nama_akun'] ?? 'Tidak ditemukan',
        'debet' => $row['debet'],
        'kredit' => $row['kredit'],
        'posting' => $row['posting'],
    ];
}

// ================= CEK DATA =================
if (empty($data)) {
    $message = "Tidak ada data untuk nomor jurnal tersebut.";
}

// ================= CLOSE =================
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Journal Data</title>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css'>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-size: 14px; /* Smaller font for body */
        }
        .container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 13px; /* Smaller font for table */
        }
        th, td {
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        h2 {
            text-align: center;
            color: #333;
            font-size: 18px; /* Adjusted font size */
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .signature-row {
            padding-top: 20px;
            font-size: 12px; /* Smaller font for signature section */
        }
        .signature-row label {
            display: inline-block;
            width: 200px;
        }
        .signature-row input {
            width: 250px;
            text-align: center;
            border: none;
            outline: none;
            background: transparent;
            font-size: 12px; /* Smaller font for input fields */
        }
        /* Styling for print */
        @media print {
            body * {
                visibility: hidden;
            }
            .container, .container * {
                visibility: visible;
            }
            .container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }

        /* Styling for Print Button */
        .print-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        .print-button:hover {
            background-color: #45a049;
        }
            .two-columns {
        display: grid;
        grid-template-columns: 1fr 1fr; /* Dua kolom dengan ukuran sama */
        gap: 20px; /* Jarak antar kolom */
        padding: 10px;
    }
    .two-columns div {
        background-color: #f9f9f9;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
    }
    .two-columns p {
        margin: 0;
        color: #333;
        font-size: 14px;
    
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
    <div class="container">
        <h2>Jurnal Pengajuan Aproval</h2>
<div class="two-columns">
    <?php if (!empty($data)) { ?>
        <div>
            <p>Nomor Pengajuan: <?php echo htmlspecialchars($data[0]['jurnal_sementara'] ?? ''); ?></p>
            <p>Tanggal: <?php echo htmlspecialchars($data[0]['tanggal'] ?? ''); ?></p>
        </div>
        <div>
            <p>Supplier: <?php echo htmlspecialchars($data[0]['nama_supcust'] ?? ''); ?></p>
         
        </div>
    <?php } ?>
</div>

<?php

if (!empty($data)) {
    echo '<p> Lampiran : ' . htmlspecialchars($data[0]['lampiran'] ?? '') . '</p>';
}
?>

        <?php if (!empty($data)) { ?>
            <table id="journalTable">
                <thead>
                    <tr>
                        <th>COA</th>
                        <th>Nama Akun</th>
                        <th>Debet</th>
                        <th>Kredit</th>
                        
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalDebet = 0;
                    $totalKredit = 0;
                    foreach ($data as $row) {
                        $totalDebet += $row['debet'];
                        $totalKredit += $row['kredit'];
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['coa'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['account_name'] ?? ''); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['debet'] ?? 0, 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format($row['kredit'] ?? 0, 2); ?></td>
                           
                        </tr>
                    <?php } ?>
                     <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Total</td>
                            <td style="text-align: right;"><?php echo number_format($totalDebet, 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format($totalKredit, 2); ?></td>
                            <td></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="1" style="text-align: right;">Selisih</td>
                            <td colspan="2" style="text-align: right;"><?php echo number_format($totalDebet - $totalKredit,2); ?></td>
                        </tr>
                        <tr>
    <td colspan="6">
        <strong>Keterangan :</strong>
        <?php echo htmlspecialchars($data[0]['keterangan'] ?? ''); ?>
    </td>
</tr>
                </tbody>
            </table>
        <?php } elseif (isset($message)) { ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php } ?>
        
        <!-- Tanda Tangan di bawah tabel dan total -->
     <div class="signature-row">
    <label for="staff_sign">Staff Acc: (.........................)</label>
 
    &nbsp;&nbsp; <!-- Spasi antara kolom -->

    <label for="man_sign">Manager Acc: (.........................)</label>
        &nbsp;&nbsp; <!-- Spasi antara kolom -->

    <label for="man_sign">Direktur: (.........................)</label>
    
</div>
   </div>
        <!-- Tombol Print -->
        <div style="text-align: center; margin-top: 20px;">
            <button class="print-button" onclick="printPage()">Print</button>
            
           

    </div>

    <script>
        function printPage() {
            window.print();
        }
    </script>
</body>
</html>
