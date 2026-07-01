<?php
require_once 'config1.php';

$sj = isset($_GET['sj']) ? $_GET['sj'] : '';
$tgl = isset($_GET['tgl']) ? $_GET['tgl'] : '';
$asal_id = isset($_GET['asal']) ? (int)$_GET['asal'] : 0;
$tujuan_id = isset($_GET['tujuan']) ? (int)$_GET['tujuan'] : 0;

if (empty($sj)) {
    die("Data SJ tidak valid.");
}

$conn->set_charset("utf8mb4");

// 1. UPDATE STATUS PENGIRIMAN (LOGIKA CERDAS)
// Jika surat jalan ini dibuka untuk dicetak, otomatis ubah statusnya dari 'Draft' menjadi kosong ('' = Dalam Pengiriman)
$stmt_update = $conn->prepare("UPDATE antar SET notrim = '' WHERE sj = ? AND notrim = 'Draft'");
$stmt_update->bind_param("s", $sj);
$stmt_update->execute();
$stmt_update->close();

// 2. Ambil Nama Gudang
$nama_asal = '';
$nama_tujuan = '';
$q = $conn->query("SELECT id_gudang, nama_gudang FROM master_gudang");
while ($r = $q->fetch_assoc()) {
    if ($r['id_gudang'] == $asal_id) $nama_asal = $r['nama_gudang'];
    if ($r['id_gudang'] == $tujuan_id) $nama_tujuan = $r['nama_gudang'];
}

// 3. Ambil Item Barang dari tabel stock (yang dikirim = jumlah_k)
$stmt_items = $conn->prepare("SELECT stock.kodeb, b.nama_b AS nama, b.satuan, stock.jumlah_k FROM stock LEFT JOIN b ON stock.kodeb = b.kode_b WHERE stock.sj = ? AND stock.jumlah_k > 0");
$stmt_items->bind_param("s", $sj);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

$items = [];
while ($row = $result_items->fetch_assoc()) {
    $items[] = $row;
}
$stmt_items->close();

function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Surat Jalan - <?= h($sj) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff; margin: 0; padding: 20px; font-size: 14px; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .logo-area h1 { margin: 0; font-size: 24px; color: #2e7d32; }
        .logo-area p { margin: 5px 0 0 0; font-size: 14px; }
        .title-area { text-align: right; }
        .title-area h2 { margin: 0 0 5px 0; font-size: 22px; text-transform: uppercase; letter-spacing: 1px; }
        .title-area p { margin: 0; font-weight: bold; }
        
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-table .col-1 { width: 120px; font-weight: bold; }
        .info-table .col-2 { width: 10px; }
        
        .item-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .item-table th, .item-table td { border: 1px solid #000; padding: 8px; text-align: center; }
        .item-table th { background: #f2f2f2; font-weight: bold; }
        .item-table .left { text-align: left; }
        
        .ttd-table { width: 100%; margin-top: 40px; text-align: center; }
        .ttd-table td { width: 33.33%; padding-top: 80px; vertical-align: bottom; }
        .ttd-table span { display: inline-block; border-top: 1px solid #000; width: 80%; padding-top: 5px; }

        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        
        .btn-print { background: #28a745; color: white; border: none; padding: 10px 20px; font-weight: bold; border-radius: 4px; cursor: pointer; margin-bottom: 20px; display: inline-block; text-decoration: none; }
        .btn-print:hover { background: #218838; }
    </style>
</head>
<body>

<div class="container">
    <div class="no-print" style="text-align: right;">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Surat Jalan</button>
    </div>

    <div class="header">
        <div class="logo-area">
            <h1>PT BFB</h1>
            <p>Sistem Manajemen Gudang BFB</p>
        </div>
        <div class="title-area">
            <h2>SURAT JALAN</h2>
            <p>No: <?= h($sj) ?></p>
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td class="col-1">Tanggal</td>
            <td class="col-2">:</td>
            <td><?= h($tgl) ?></td>
            <td class="col-1" style="width: 80px;">Gudang Asal</td>
            <td class="col-2">:</td>
            <td><?= h($nama_asal) ?></td>
        </tr>
        <tr>
            <td class="col-1"></td>
            <td class="col-2"></td>
            <td></td>
            <td class="col-1" style="width: 80px;">Gudang Tujuan</td>
            <td class="col-2">:</td>
            <td><?= h($nama_tujuan) ?></td>
        </tr>
    </table>

    <table class="item-table">
        <thead>
            <tr>
                <th style="width: 50px;">No</th>
                <th style="width: 150px;">Kode Barang</th>
                <th class="left">Nama Barang</th>
                <th style="width: 100px;">Satuan</th>
                <th style="width: 100px;">Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_qty = 0;
            foreach ($items as $item): 
                $total_qty += $item['jumlah_k'];
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= h($item['kodeb']) ?></td>
                <td class="left"><?= h($item['nama']) ?></td>
                <td><?= h($item['satuan']) ?></td>
                <td><?= h($item['jumlah_k']) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php if (empty($items)): ?>
            <tr>
                <td colspan="5">Data barang tidak ditemukan.</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: right;">Total Qty:</th>
                <th><?= $total_qty ?></th>
            </tr>
        </tfoot>
    </table>

    <table class="ttd-table">
        <tr>
            <td>
                <span>Penerima</span>
            </td>
            <td>
                <span>Supir / Ekspedisi</span>
            </td>
            <td>
                <span>Hormat Kami (Pengirim)</span>
            </td>
        </tr>
    </table>
</div>

<script>
    // Otomatis mereload halaman Rekap di background agar status langsung berubah
    window.onload = function() { 
        if (window.opener && !window.opener.closed) {
            window.opener.location.reload();
        }
        // Otomatis memicu jendela print
        window.print(); 
    }
</script>

</body>
</html>
