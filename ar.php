<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);





if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}


require_once 'config1.php';






$page_name = 'ARHO';
$current_user = $_SESSION['username'];






$filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
$filter_sql = '';

if ($filter !== '') {
    $escaped_filter = $conn->real_escape_string($filter);
    $escaped_filter2 = $conn->real_escape_string($filter);

    $customer_ids = [];
    $customer_query = $conn->query("SELECT id FROM customer WHERE customer LIKE '%$escaped_filter2%'");
    while ($cust = $customer_query->fetch_assoc()) {
        $customer_ids[] = intval($cust['id']);
    }
    $id_list = implode(",", $customer_ids);
    $filter_sql = "AND (
        kodebooking LIKE '%$escaped_filter%' 
        OR inv LIKE '%$escaped_filter%' 
        " . (!empty($id_list) ? " OR cust_id IN ($id_list)" : "") . "
    )";
}

$sql_pph23 = "
    SELECT 
        pph23.id,
        pph23.tanggal,
        pph23.inv,
        pph23.kodebooking,
        pph23.cust_id,
        pph23.bukpot,
        pph23.pph23,
        pph23.tagihan,
        pph23.fp,
        pph23.bayar,
        pph23.sisa,
        pph23.location,
        pph23.devisi,
        DATEDIFF(CURDATE(), pph23.tanggal) AS umur,
        -- Subquery untuk PPN
        (
            SELECT SUM(j.kredit)
            FROM jurnal j
            WHERE j.journal_number = pph23.inv
              AND j.coa = '21206'
        ) AS PPN,
        -- Subquery untuk DPP
        (
            SELECT SUM(j.kredit)
            FROM jurnal j
            WHERE j.journal_number = pph23.inv
              AND j.coa LIKE '41%'
        ) AS DPP
    FROM 
        pph23
    WHERE 
        pph23.sisa > 0
        $filter_sql
    ORDER BY umur DESC
";

$result_pph23 = $conn->query($sql_pph23);

// Handling Export to PDF or Excel
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once 'export_pdfar.php';
    exit();
} elseif (isset($_GET['export']) && $_GET['export'] == 'excel') {
    require_once 'export_excelar.php';
    exit();
}
?>
<script>
const pageName = 'ARHO'; // Ganti sesuai halaman

setInterval(() => {
    fetch('heartbeat.php', {
        method: 'POST',
        credentials: 'include',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `page_name=${encodeURIComponent(pageName)}`
    });
}, 30000);

window.addEventListener("beforeunload", function () {
    navigator.sendBeacon("unlock.php", new URLSearchParams({ page_name: pageName }));
});
</script>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>ARHO</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
       body {
    font-family: Arial, sans-serif;
    background-color: #f2f2f2;
    margin: 0;
    padding: 20px;
}

.table-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 0 8px rgba(0,0,0,0.1);
    max-height: 600px;
    overflow-y: auto;
}

h1 {
    text-align: center;
}

form {
    margin-bottom: 20px;
    text-align: center;
}

input[type="text"] {
    padding: 8px;
    width: 300px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

button {
    padding: 8px 16px;
    border: none;
    background-color: #4CAF50;
    color: white;
    border-radius: 4px;
    cursor: pointer;
}

button[type="submit"] {
    padding: 8px 14px;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

button[type="submit"]:hover {
    background-color: #45a049;
}

table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 20px;
}

th, td {
    text-align: right;
    padding: 10px;
    border: 1px solid #ddd;
    font-size: 14px;
}

/* ✅ Tambahkan ini untuk sticky header */
th {
    background-color: blue;
    position: sticky;
    top: 0;
    z-index: 2;
      color: white;
}

tr:hover {
    background-color: #f1f1f1;
}

.action-icon button {
    margin: 2px;
    padding: 5px 10px;
    background: #007BFF;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
}

.action-icon button:hover {
    background-color: #0056b3;
}

.home-icon i {
    color: maroon;
    font-size: 36px;
    float: left;
}

.left-icon i {
    color: maroon;
    font-size: 36px;
    float: right;
}
    </style>
</head>
<body>

    <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
    <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>
    <h1>Customer Belum Bayar</h1>

    <!-- Export Buttons -->
    <form method="GET">
        <input type="text" name="filter" placeholder="Cari invoice / kode booking / customer" value="<?= htmlspecialchars($filter ?? '') ?>">
        <button type="submit">Cari</button>
        <button type="submit" name="export" value="excel">
    <i class="fas fa-file-excel"></i> Export to Excel
</button>
 
        <button type="button" onclick="window.location.href='arsales.php'">Persales</button>
 <button type="button" onclick="window.location.href='ardone.php'">sudah bayar</button>
    </form>

   <?php
if ($result_pph23->num_rows > 0) {
    $total_pph23 = 0;
    $total_bayar = 0;
    $total_tagihan = 0;
    $total_sisa = 0;

    echo "<table border='1' cellpadding='5' cellspacing='0' width='100%'>
        <tr style='background-color:#f2f2f2; font-weight:bold;'>
            <th><input type='checkbox' id='select-all'></th>
            <th>ID</th>
            <th>Tanggal</th>
            <th>Invoice</th>
            <th>Kode Booking</th>
            <th>Customer</th>
              <th>Transaksi</th>
                <th>ppn</th>
                        <th> - PPH23</th>
                          <th> - Titipan awal</th>
            <th>Tagihan</th>
            <th>USD</th>
    
            <th>Bayar</th>
            <th>Sisa</th>
            <th>Loc Dev</th>
            <th>Umur (Hari)</th>
            <th>Pembayaran</th>
        </tr>";

    while ($row = $result_pph23->fetch_assoc()) {
        // Get customer name
        $customer_name = 'Tidak Ditemukan';
        $sql_customer = "SELECT customer FROM customer WHERE id = " . intval($row['cust_id']);
        $result_customer = $conn->query($sql_customer);
        if ($result_customer && $result_customer->num_rows > 0) {
            $customer_name = $result_customer->fetch_assoc()['customer'];
        }
$ttp_value = max(0, floatval($row['DPP']) + floatval($row['PPN']) - floatval($row['pph23']) - floatval($row['tagihan']));

        $total_DPP += floatval($row['DPP']);
             $total_PPN += floatval($row['PPN']);
                   $total_ttp += floatval($ttp_value);
        $total_tagihan += floatval($row['tagihan']);
        $total_bayar += floatval($row['bayar']);
        $total_sisa += floatval($row['sisa']);
           $total_pph += floatval($row['pph23']);
             $total_usd += floatval($usd_value);

        // Get USD value
        $usd_value = '-';
        $kodebooking = $conn->real_escape_string($row['kodebooking']);
        $query_crus = $conn->query("SELECT curs_sell FROM booking_request WHERE kode_booking = '$kodebooking' LIMIT 1");

        if ($query_crus && $query_crus->num_rows > 0) {
            $curs = $query_crus->fetch_assoc();
            $curs_sell = isset($curs['curs_sell']) && !is_null($curs['curs_sell']) ? floatval($curs['curs_sell']) : 0;

            if ($curs_sell > 0) {
                $usd_value = number_format($row['tagihan'] / $curs_sell, 2);
            } else {
                $usd_value = number_format(0, 2);
            }
        } else {
            $usd_value = number_format(0, 2);
        }

        echo "<tr>
            <td><input type='checkbox' name='selected_ids[]' value='" . htmlspecialchars($row['id'] ?? '') . "'></td>
            <td>" . htmlspecialchars($row['id'] ?? '') . "</td>
            <td>" . htmlspecialchars($row['tanggal'] ?? '') . "</td>
            <td>" . htmlspecialchars($row['inv'] ?? '') . "</td>
            <td>" . htmlspecialchars($row['kodebooking'] ?? '') . "</td>
            <td>" . htmlspecialchars($customer_name ?? '') . "</td>
                <td>" . number_format($row['DPP'], 2) . "</td>
                    <td>" . number_format($row['PPN'], 2) . "</td>
                    <td style='color: #cc0000;'>" . number_format($row['pph23'], 2) . "</td>
      <td style='color: #cc0000;'>" . number_format ($ttp_value, 2) . "</td>

            <td>" . number_format($row['tagihan'], 2) . "</td>
            <td> " . $usd_value . "</td>
   
            <td>" . number_format($row['bayar'], 2) . "</td>
            <td>" . number_format($row['sisa'], 2) . "</td>
            <td>" . htmlspecialchars($row['location'] . " - " . $row['devisi'] ?? '') . "</td>
            <td>" . intval($row['umur']) . " Hari</td>
            <td class='action-icon'>
                <button type='button' onclick='openPopup(\"arc(" . urlencode($row['devisi']) . ").php?J=" . urlencode($row['id']) . "\")'>cash</button>
                <button type='button' onclick='openPopup(\"arb(" . urlencode($row['devisi']) . ").php?J=" . urlencode($row['id']) . "\")'>bank</button>
                <button onclick=\"openPopup('art(" . urlencode($row['devisi']) . ").php?J={$row['id']}')\">titipan</button>
                
                <button onclick=\"openPopup('arcn(" . urlencode($row['devisi']) . ").php?J={$row['id']}')\">cn</button>
                                                                                    <button onclick=\"openPopup('ardn(" . urlencode($row['devisi']) . ").php?J={$row['id']}')\">dn</button>

            </td>
        </tr>";
    }

    echo "<tr style='font-weight: bold; background-color: #dff0d8'>
        <td colspan='6' style='text-align:center'>Total</td>
                 <td>" . number_format($total_DPP, 2) . "</td>
                          <td>" . number_format($total_PPN, 2) . "</td>
                               <td>" . number_format($total_pph, 2) . "</td>
                                    <td>" . number_format($total_ttp, 2) . "</td>
   
        <td>" . number_format($total_tagihan, 2) . "</td>
           <td>" . number_format($total_usd, 2) . "</td>
          
        <td>" . number_format($total_bayar, 2) . "</td>
        <td>" . number_format($total_sisa, 2) . "</td>
        <td colspan='3'></td> <!-- Kosong untuk lokasi/dev/umur/aksi -->
    </tr>
    </table>";

    echo "<div style='margin-top: 10px; text-align: center;'>
        <button type='button' onclick=\"processSelected('cash')\">Cash</button>
        <button type='button' onclick=\"processSelected('bank')\">Bank</button>
        <button type='button' onclick=\"processSelected('titipan')\">Titipan</button>
    </div>";

} else {
    echo "<p>Tidak ada data invoice.</p>";
}

$conn->close();
?>

    <!-- Form tersembunyi untuk pengiriman POST -->
    <form id="bulkActionForm" method="POST" target="_blank"></form>
</div>

<!-- Script -->
<script>
document.addEventListener('contextmenu', e => e.preventDefault());
document.addEventListener('keydown', e => {
    if (
        e.keyCode == 123 ||
        (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) ||
        (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) ||
        (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))
    ) e.preventDefault();
});

function openPopup(url) {
    const width = 800, height = 900;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    window.open(url, 'PopupWindow', `width=${width},height=${height},top=${top},left=${left},resizable=yes,scrollbars=yes`);
}

document.getElementById('select-all').addEventListener('change', function () {
    const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
    for (const checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});

function processSelected(action) {
    const selectedCheckboxes = document.querySelectorAll('input[name="selected_ids[]"]:checked');

    if (selectedCheckboxes.length === 0) {
        alert("Pilih minimal satu data terlebih dahulu.");
        return;
    }

    let firstCustomer = null;
    let firstDevisi = null;
    let isValid = true;

    selectedCheckboxes.forEach(cb => {
        const row = cb.closest("tr");
        const customer = row.querySelector("td:nth-child(6)").textContent.trim();
        const devisiText = row.querySelector("td:nth-child(15)").textContent.trim();
        const devisi = devisiText.includes(' - ') ? devisiText.split(" - ")[1].trim() : devisiText;

        if (firstCustomer === null && firstDevisi === null) {
            firstCustomer = customer;
            firstDevisi = devisi;
        } else {
            if (customer !== firstCustomer || devisi !== firstDevisi) {
                isValid = false;
            }
        }
    });

    if (!isValid) {
        alert("Semua baris yang dipilih harus memiliki Customer dan Devisi yang sama.");
        return;
    }

    const form = document.getElementById("bulkActionForm");
    form.innerHTML = ""; // Kosongkan isi form
    let actionUrl = "";

    if (action === "cash") {
        actionUrl = `arc${firstDevisi}.php`;
    } else if (action === "bank") {
        actionUrl = `arb${firstDevisi}.php`;
    } else if (action === "titipan") {
        actionUrl = `art${firstDevisi}.php`;
    }

    form.action = actionUrl;

    selectedCheckboxes.forEach(cb => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = "selected_ids[]";
        input.value = cb.value;
        form.appendChild(input);
    });

    form.submit();
}
</script>



</body>
</html>
