<?php
require_once 'config1.php';

$id_gudang = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$userid = $_SESSION['userid'] ?? 0;
$jabatan = $_SESSION['jabatan'] ?? 0;

$user_sales_ids = [];
if ($userid && $_SESSION['bagian'] === 'sales') {
    $stmt_sales = $conn->prepare("SELECT id_gudang FROM master_sales WHERE userid = ?");
    $stmt_sales->bind_param("i", $userid);
    $stmt_sales->execute();
    $res_sales = $stmt_sales->get_result();
    while ($row = $res_sales->fetch_assoc()) {
        $user_sales_ids[] = $row['id_gudang'];
    }
    $stmt_sales->close();
}

// Validasi id_gudang jika user merupakan sales
if ($_SESSION['bagian'] === 'sales' && count($user_sales_ids) > 0) {
    // Jika id_gudang yang diminta tidak ada dalam daftar milik sales ini, paksa ke id_gudang pertamanya
    if (!in_array($id_gudang, $user_sales_ids)) {
        $id_gudang = $user_sales_ids[0];
    }
}

// ================= PAGINATION SETTINGS =================
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1) * $limit;
$total_data = 0;

if ($id_gudang == 0) {
    // Pusat (HO)
    $judul_halaman = "Laporan Stok Barang - Pusat (HO)";
    
    // Hitung total data untuk HO (menggabungkan status 'Terjual' ke 'Tersedia')
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM (
        SELECT 1 FROM stock 
        GROUP BY kodeb, id_gudang
    ) t");
    $count_stmt->execute();
    $total_data = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $count_stmt->close();
    
    // Baca dari tabel stock (id_gudang = 0) dengan LIMIT & OFFSET
    $sql = "SELECT s.kodeb AS kode_b, b.nama_b AS nama_b, s.id_gudang,
                   'Tersedia' AS status_gudang,
                   SUM(s.jumlah_m) AS total_masuk, 
                   SUM(s.jumlah_k) AS total_keluar, 
                   (SUM(s.jumlah_m) - SUM(s.jumlah_k)) AS stok_akhir,
                   b.hargat_b AS harga_modal
            FROM stock s
            LEFT JOIN b ON TRIM(s.kodeb) = TRIM(b.kode_b)
            GROUP BY s.kodeb, b.nama_b, s.id_gudang, b.hargat_b
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
} else {
    // Gudang Cabang (1-5)
    $judul_halaman = "Laporan Stok Barang - Gudang " . $id_gudang;
    
    // Hitung total data untuk Cabang
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM (
        SELECT 1 FROM stock 
        WHERE id_gudang = ? 
        GROUP BY kodeb
    ) t");
    $count_stmt->bind_param("i", $id_gudang);
    $count_stmt->execute();
    $total_data = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $count_stmt->close();
    
    $sql = "SELECT s.kodeb AS kode_b, b.nama_b AS nama_b, 
                   SUM(s.jumlah_m) AS total_masuk, 
                   SUM(s.jumlah_k) AS total_keluar, 
                   (SUM(s.jumlah_m) - SUM(s.jumlah_k)) AS stok_akhir,
                   b.hargat_b AS harga_modal
            FROM stock s
            LEFT JOIN b ON TRIM(s.kodeb) = TRIM(b.kode_b)
            WHERE s.id_gudang = ?
            GROUP BY s.kodeb, b.nama_b, b.hargat_b
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $id_gudang, $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

$total_pages = max(1, ceil($total_data / $limit));

$back_url = ($id_gudang > 0) ? "gudang/home.php?id=" . $id_gudang : "home.php";

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Stok Barang</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
        }
        .top-bar{display:flex;justify-content:space-between;padding:10px;background:#7a0000;}
        .top-bar a{color:white;font-size:20px;text-decoration:none;}
        .container{padding:15px;max-width:1200px;margin:auto;}
        h2{text-align:center;color:#7a0000;}
        .search-box{display:flex;gap:10px;margin-bottom:15px;}
        .search-box input{flex:1;padding:8px;border-radius:6px;border:1px solid #ccc;}
        .table-container{overflow-x:auto;background:white;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,0.1);}
        table{width:100%;border-collapse:collapse;}
        th{background:#7a0000;color:white;padding:8px;}
        td{padding:6px;border-bottom:1px solid #ddd; text-align: center;}
        tr:hover{background:#f1f1f1;}
        tbody tr:nth-child(even){background:#fafafa;}
        .negative{color:red;font-weight:bold;}
        .btn{padding:5px 10px;background:green;color:white;border-radius:5px;text-decoration:none;display:inline-block;}
        .pagination{margin-top:15px;text-align:center;}
        .pagination a{padding:6px 10px;background:#7a0000;color:white;margin:2px;text-decoration:none;border-radius:5px;}
        .btn-green { padding: 8px 16px; border: none; border-radius: 4px; background: #28a745; color: white; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="home.php"><i class="fas fa-home"></i></a>
    <a href="<?= $back_url ?>"><i class="fa-solid fa-arrow-left"></i></a>
</div>

<div class="container">
    <h2><?= htmlspecialchars($judul_halaman) ?></h2>

    <div class="search-box">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari kode barang atau nama...">
        <button type="button" class="btn-green" onclick="openScanner()" title="Scan Barcode">
            <i class="fa-solid fa-barcode"></i> Scan
        </button>
    </div>

    <!-- Area Scanner -->
    <div id="scannerBox" style="display:none; text-align:center; margin-bottom:20px;">
        <video id="preview" style="width:100%; max-width:320px; border:1px solid #ccc; border-radius:8px;"></video>
        <br>
        <button class="btn-green" style="margin-top:10px; background:#dc3545;" onclick="closeScanner()">Tutup Kamera</button>
    </div>
    <script src="https://unpkg.com/@zxing/library@latest"></script>

    <?php
    if ($result->num_rows > 0) {
        echo "<div class='table-container'>
                <table id='stokTable'>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Masuk</th>
                        <th>Keluar</th>
                        <th>Stok</th>
                        <th>Harga</th>";
        if ($id_gudang == 0) {
            echo "<th>Gudang</th><th>Status</th>";
        }
        echo "          <th>Action</th>
                    </tr>";

        while ($row = $result->fetch_assoc()) {
            $stok = (float)$row["stok_akhir"];
            $neg = $stok < 0 ? 'negative' : '';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row["kode_b"] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row["nama_b"] ?? '') . "</td>";
            echo "<td>" . number_format((float)$row["total_masuk"]) . "</td>";
            echo "<td>" . number_format((float)$row["total_keluar"]) . "</td>";
            echo "<td class='$neg'>" . number_format($stok) . "</td>";
            echo "<td>" . number_format((float)$row["harga_modal"]) . "</td>";
            
            if ($id_gudang == 0) {
                $status = htmlspecialchars($row["status_gudang"] ?? 'Tersedia');
                $nama_gdg = ((int)$row["id_gudang"] == 0) ? "Pusat (HO)" : "Gudang " . (int)$row["id_gudang"];
                echo "<td>" . $nama_gdg . "</td>";
                echo "<td>" . $status . "</td>";
            }
            
            echo "<td>";
            echo "<a href='kartu.php?kode_b=" . urlencode($row["kode_b"] ?? '') . "' class='btn'><i class='fa fa-eye'></i> Kartu</a>";
            echo "</td>";
            echo "</tr>";
        }
        echo "</table></div>";

        // Tampilkan pagination links
        echo "<div class='pagination'>";
        if ($page > 1) {
            echo "<a href='?id=" . $id_gudang . "&page=" . ($page - 1) . "'>&larr; Prev</a> ";
        }
        echo "<span>Halaman " . $page . " / " . $total_pages . "</span>";
        if ($page < $total_pages) {
            echo " <a href='?id=" . $id_gudang . "&page=" . ($page + 1) . "'>Next &rarr;</a>";
        }
        echo "</div>";

    } else {
        echo "<div class='empty-data'>Tidak ada data ditemukan.</div>";
    }
    $stmt->close();
    $conn->close();
    ?>
</div>

<script>
    function filterTable() {
        var input, filter, table, tr, td, i;
        input = document.getElementById("searchInput");
        filter = input.value.toLowerCase();
        table = document.getElementById("stokTable");
        if (!table) return;
        tr = table.getElementsByTagName("tr");

        for (i = 1; i < tr.length; i++) {
            tr[i].style.display = "none"; 
            tdKode = tr[i].getElementsByTagName("td")[0]; // Kode Barang
            tdNama = tr[i].getElementsByTagName("td")[1]; // Nama Barang
            if (tdKode || tdNama) {
                if ((tdKode && tdKode.innerHTML.toLowerCase().indexOf(filter) > -1) || 
                    (tdNama && tdNama.innerHTML.toLowerCase().indexOf(filter) > -1)) {
                    tr[i].style.display = "";
                }
            }
        }
    }

    let codeReader;
    let scanned = false;

    function openScanner() {
        document.getElementById('scannerBox').style.display = 'block';

        if (!codeReader) {
            codeReader = new ZXing.BrowserMultiFormatReader();
        }

        scanned = false;

        codeReader.decodeFromVideoDevice(null, 'preview', (result, err) => {
            if (result && !scanned) {
                scanned = true;
                const barcode = result.text;
                const input = document.getElementById('searchInput');
                input.value = barcode;
                filterTable();
                closeScanner();
            }
        });
    }

    function closeScanner() {
        if (codeReader) {
            codeReader.reset();
        }
        document.getElementById('scannerBox').style.display = 'none';
    }
</script>

</body>
</html>
