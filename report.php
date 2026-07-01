<?php








require_once 'config1.php';

// Query utama join dengan tabel location
$sql = "
    SELECT j.*, l.nama_cabang 
    FROM jurnal j
    LEFT JOIN location l ON j.location = l.idl
    ORDER BY j.journal_number
";
$result = $conn->query($sql);

$data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    $message = "Tidak ada data di tabel jurnal.";
}

// Ambil data lokasi untuk filter (distinct berdasarkan idl)
$locationQuery = "
    SELECT DISTINCT l.idl, l.nama_cabang 
    FROM jurnal j 
    LEFT JOIN location l ON j.location = l.idl
    WHERE l.nama_cabang IS NOT NULL
";
$locationResult = $conn->query($locationQuery);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Journal Data</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0; padding: 0;
            background-color: #f4f4f4;
            color: black;
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
            color: maroon;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        th {
            background-color: #e8f0ff;
        }
        tr:hover { background-color: #f1f1f1; }
        h2 { text-align: center; color: #333; }
        .search-box {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-box input, .search-box select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .home-icon i, .left-icon i {
            color: maroon;
            font-size: 24px;
        }
        .home-icon i { float: left; }
        .left-icon i { float: right; }
    </style>
</head>
<body>
    <div class="table-container">
        <a href="home.php" class="home-icon"><i class="fas fa-home"></i></a>
        <a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

        <div class="container">
            <h2>View Journal Data</h2>

            <div class="search-box">
                <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari nomor jurnal...">
                <select id="locationFilter" onchange="filterTable()">
                    <option value="">Filter Lokasi</option>
                    <?php while ($location = $locationResult->fetch_assoc()) { ?>
                        <option value="<?= htmlspecialchars($location['nama_cabang']); ?>">
                            <?= htmlspecialchars($location['nama_cabang']); ?>
                        </option>
                    <?php } ?>
                </select>
                <input type="date" id="startDate" onchange="filterTable()">
                <input type="date" id="endDate" onchange="filterTable()">
            </div>

            <?php if (!empty($data)) { ?>
                <form method="post" action="process_checked.php">
                    <table id="journalTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="checkAll" onclick="toggleAllChecks(this)"></th>
                                <th>ID</th>
                                <th>Nomor Jurnal</th>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th>Lokasi</th>
                                <th>COA</th>
                                <th>Nama Akun</th>
                                <th>Debet</th>
                                <th>Kredit</th>
                                <th>Posting</th>
                                <th>Print</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row) { ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_ids[]" value="<?= htmlspecialchars($row['id']); ?>"></td>
                                    <td><?= htmlspecialchars($row['id']); ?></td>
                                    <td><?= htmlspecialchars($row['journal_number']); ?></td>
                                    <td><?= htmlspecialchars($row['tanggal']); ?></td>
                                    <td><?= htmlspecialchars($row['keterangan']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_cabang'] ?: ''); ?></td>
                                    <td><?= htmlspecialchars($row['coa']); ?></td>
                                    <td><?= htmlspecialchars($row['account_name']); ?></td>
                                    <td style="text-align:right;"><?= number_format($row['debet'], 2); ?></td>
                                    <td style="text-align:right;"><?= number_format($row['kredit'], 2); ?></td>
                                    <td>
                                        <?php if (!empty($row['posting'])) {
                                            echo htmlspecialchars($row['posting']);
                                        } else { ?>
                                            <a href="posting.php?kode_transaksi=<?= urlencode($row['journal_number']); ?>" target="_blank">
                                                <i class="fas fa-upload"></i>
                                            </a>
                                        <?php } ?>
                                    </td>
                                    <td class="print-icon">
                                        <a href="cetak_jurnal.php?kode_transaksi=<?= urlencode($row['journal_number']); ?>" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <div style="text-align:center;">
                        <button type="submit">Proses Terpilih</button>
                    </div>
                </form>
            <?php } elseif (isset($message)) { ?>
                <p><?= htmlspecialchars($message); ?></p>
            <?php } ?>
        </div>
    </div>

    <script>
    // Set default tanggal hari ini
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('startDate').value = today;
        document.getElementById('endDate').value = today;
        filterTable();
    });

    function filterTable() {
        const input = document.getElementById("searchInput").value.toLowerCase();
        const locationFilter = document.getElementById("locationFilter").value;
        const startDate = new Date(document.getElementById("startDate").value);
        const endDate = new Date(document.getElementById("endDate").value);
        const table = document.getElementById("journalTable");
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            const tds = tr[i].getElementsByTagName("td");
            if (tds.length < 10) continue;

            const journalNum = tds[2].innerText.toLowerCase();
            const lokasi = tds[5].innerText;
            const tanggal = new Date(tds[3].innerText);

            let show = true;

            if (input && !journalNum.includes(input)) show = false;
            if (locationFilter && lokasi !== locationFilter) show = false;
            if (tanggal < startDate || tanggal > endDate) show = false;

            tr[i].style.display = show ? "" : "none";
        }
    }

    function toggleAllChecks(source) {
        const checkboxes = document.getElementsByName('selected_ids[]');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = source.checked;
        }
    }
    </script>
</body>
</html>
