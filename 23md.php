<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

require_once 'config1.php';

// Ambil variabel dari environment





// Koneksi ke database pertama




// Ambil filter dari URL (jika ada)
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

// Mulai query
$filter_sql = "";
if (!empty($filter)) {
    // Cek nama customer di database kedua
    $filter_escaped = $conn->real_escape_string($filter);
    $sql_cust = "SELECT id FROM cust WHERE nama LIKE '%$filter_escaped%'";
    $result_cust = $conn->query($sql_cust);

    $cust_ids = [];
    if ($result_cust && $result_cust->num_rows > 0) {
        while ($row_cust = $result_cust->fetch_assoc()) {
            $cust_ids[] = $row_cust['id'];
        }
    }

    // Susun filter berdasarkan invoice, kode booking, dan customer id
    $cust_filter = '';
    if (!empty($cust_ids)) {
        $cust_ids_str = implode(",", $cust_ids);
        $cust_filter = " OR beli.cust_id IN ($cust_ids_str)";
    }

    $filter_escaped = $conn->real_escape_string($filter); // escape untuk database pertama
    $filter_sql = " AND (beli.inv LIKE '%$filter_escaped%' OR beli.kodebooking LIKE '%$filter_escaped%' $cust_filter)";
}

$sql_pph23 = "
    SELECT 
        beli.id,
        beli.inv,
        beli.kodebooking,
        beli.cust_id,
        beli.pph23,
          beli.jenispph,
        beli.bukpot23,
           beli.23dibayar,
        (
            SELECT SUM(jurnal.debet)
            FROM jurnal
            WHERE jurnal.journal_number = beli.inv
              AND jurnal.coa = '13103'
        ) AS PPN,
        (
            SELECT SUM(jurnal.debet)
            FROM jurnal
            WHERE jurnal.journal_number = beli.inv
              AND jurnal.coa LIKE '51%'
        ) AS DPP
    FROM 
        BELI beli
    WHERE 
      (beli.bukpot23 IS NOT NULL AND beli.bukpot23 <> '')
    AND (beli.pph23 IS NOT NULL AND beli.pph23 > 0)

        $filter_sql
    ORDER BY 
        beli.id
";

$result_pph23 = $conn->query($sql_pph23);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Faktur Pajak Belum dibuat - www.symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; color: #333; padding: 20px; }
        h1 { text-align: center; color: #4CAF50; }
        form { text-align: center; margin-bottom: 20px; }
        input[type="text"], button { padding: 8px; border-radius: 4px; }
        input[type="text"] { border: 1px solid #ccc; margin-right: 10px; }
        button { background: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background: #45a049; }
        .home-icon1, .left-icon { position: absolute; top: 0; font-size: 24px; color: maroon; }
        .home-icon1 { left: 0; padding-left: 10px; }
        .left-icon { right: 0; padding-right: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: right; }
        th { background: #4CAF50; color: white; text-align: center; }
        tr:nth-child(even) { background: #f2f2f2; }
        tr:hover { background: #ddd; }
        .action-icon { text-align: center; }
        @media (max-width: 768px) {
            th, td { padding: 8px; font-size: 12px; }
        }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
        <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
        <h1>PPH  Sudah Dibuat</h1>

        <form method="get">
            <input type="text" name="filter" placeholder="Cari Invoice, Kode Booking, atau Customer..." value="<?php echo htmlspecialchars($filter ?? ''); ?>">
            <button type="submit">Cari</button>
        </form>
<form method="get" action="export_23msudah.php" style="text-align: center; margin-bottom: 20px;">
    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter ?? ''); ?>">
    <button type="submit">Export Excel</button>
    <button type="button" onclick="window.location.href='23m.php'">BELUM DIBUAT</button>
 <button type="button" onclick="window.location.href='23upby.php'" style="position: fixed; background:blue ;  right: 20px;">Upload Daftar Setor 23</button>

</form>

<?php
if ($result_pph23 && $result_pph23->num_rows > 0) {
    echo "<form method='post' action='proses_pph.php'>"; // Tambahkan form jika ingin submit
    echo "<table>
            <tr>
                <th><input type='checkbox' id='checkAll' onclick=\"toggleAll(this)\"></th>
                <th>ID</th>
                <th>Invoice</th>
                <th>Kode Booking</th>
                <th>Supplier</th>
                <th>Transaksi</th>
                <th>pph</th>
                <th>Jenispph</th>
                <th>Tarif (%)</th>
                <th>No Bukpot</th>
                <th>PPh Distor</th>
            </tr>";

    $total_dpp = 0;
    $total_pph23 = 0;

    while ($row = $result_pph23->fetch_assoc()) {
        $cust_id = intval($row['cust_id']);
        $customer_name = 'Tidak Ditemukan';
        $sql_customer = "SELECT nama AS customer FROM cust WHERE id = $cust_id";
        $result_customer = $conn->query($sql_customer);
        if ($result_customer && $result_customer->num_rows > 0) {
            $customer_row = $result_customer->fetch_assoc();
            $customer_name = $customer_row['customer'];
        }

        $dpp = floatval($row['DPP']);
        $pph23 = floatval($row['pph23']);
        $tarif = ($dpp > 0) ? ($pph23 / $dpp * 100) : 0;

        $total_dpp += $dpp;
        $total_pph23 += $pph23;

        echo "<tr>";

        // Kolom checkbox hanya jika belum dibayar
        echo "<td>";
        if (is_null($row['23dibayar'])) {
            echo "<input type='checkbox' name='cek[]' value='" . htmlspecialchars($row['id'] ?? '') . "' class='checkItem'>";
        }
        echo "</td>";

        echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>
              <td>" . htmlspecialchars($row['inv'] ?? '') . "</td>
              <td>" . htmlspecialchars($row['kodebooking'] ?? '') . "</td>
              <td>" . htmlspecialchars($customer_name ?? '') . "</td>
              <td>" . number_format($dpp, 2) . "</td>
              <td>" . number_format($pph23, 2) . "</td>
              <td>" . htmlspecialchars($row['jenispph'] ?? '') . "</td>
              <td style='text-align:center;'>" . number_format($tarif, 2) . "%</td>
              <td>" . htmlspecialchars($row['bukpot23'] ?? '') . "</td>
              <td>";

        if (is_null($row['23dibayar'])) {
            echo "belum";
        } elseif ((string)$row['23dibayar'] === '1') {
            echo "sudah";
        } else {
            echo htmlspecialchars($row['23dibayar'] ?? '');
        }

        echo "</td></tr>";
    }

    echo "<tr style='font-weight:bold; background:#f2f2f2;'>
            <td colspan='4' style='text-align:right;'>TOTAL</td>
            <td>" . number_format($total_dpp, 2) . "</td>
            <td>" . number_format($total_pph23, 2) . "</td>
            <td colspan='4'></td>
          </tr>";

    echo "</table>
          <button type='submit'>Proses Terpilih</button>
          </form>";

    // Script untuk toggle semua checkbox
    echo "<script>
        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.checkItem');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }
    </script>";
} else {
    echo "<p style='text-align:center;'>Tidak ada data INV yang belum dicetak faktur pajaknya.</p>";
}
?>
    </div>

    <script>
        function openPopup(url) {
            window.open(url, "Popup", "width=800,height=600");
        }
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && ['I', 'C'].includes(e.key.toUpperCase())) || (e.ctrlKey && e.key.toUpperCase() == 'U')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
