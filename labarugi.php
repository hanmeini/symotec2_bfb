<?php
// labarugi.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// -- auth sederhana (sesuaikan jika perlu) --
if (!isset($_SESSION['userid'])) {
    header('Location: index.html');
    exit();
}

require_once 'config1.php';
// $conn sudah dibuat di config1.php

// bangun daftar bulan-tahun untuk pilihan (mis. 24 bulan terakhir)
function build_month_options($months_back = 24) {
    $opts = [];
    $now = new DateTime();
    for ($i = 0; $i < $months_back; $i++) {
        $d = (clone $now)->modify("-$i month");
        $key = $d->format('Y-m');        // format yang dipakai untuk filter
        $label = $d->format('F Y');     // label tampil
        $opts[$key] = $label;
    }
    return $opts;
}

$month_options = build_month_options(36); // 3 tahun untuk aman

// default selected = bulan ini
$selected_months = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_months = $_POST['months'] ?? [];
    // sanitize: accept only keys present di $month_options
    $selected_months = array_values(array_intersect($selected_months, array_keys($month_options)));
    if (count($selected_months) === 0) {
        // jika user submit tapi kosong, default bulan ini
        $selected_months = [ (new DateTime())->format('Y-m') ];
    }
} else {
    $selected_months = [ (new DateTime())->format('Y-m') ];
}

// -- helper: buat IN clause string aman untuk bulan (format 'YYYY-MM') --
$escaped_months = array_map(function($m) use ($conn) { return "'" . $conn->real_escape_string($m) . "'"; }, $selected_months);
$in_clause = implode(',', $escaped_months);

// 1) PENDAPATAN: SUM(harga) dari penjualanHO1 untuk bulan terpilih
$pendapatan = 0.0;
$sql = "SELECT IFNULL(SUM(harga),0) AS total_pendapatan
        FROM penjualanHO1
        WHERE DATE_FORMAT(tanggal_transaksi, '%Y-%m') IN ($in_clause)";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    $pendapatan = (float)$row['total_pendapatan'];
}
if ($res) $res->free();

// 2) HPP: SUM(dpp) dari transaksiHO1 untuk bulan terpilih
$hpp = 0.0;
$sql = "SELECT IFNULL(SUM(dpp),0) AS total_hpp
        FROM transaksiHO1
        WHERE DATE_FORMAT(tanggal_transaksi, '%Y-%m') IN ($in_clause)";
$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    $hpp = (float)$row['total_hpp'];
}
if ($res) $res->free();

// 3) BIAYA: SUM(debet) dari jurnal untuk akun berawalan '6' (Biaya/Beban)
$biaya_items = [];
$total_biaya = 0.0;

$sql = "SELECT c.account_name AS jenis, IFNULL(SUM(j.debet),0) AS total_kredit
        FROM jurnal j
        JOIN coa c ON j.coa = c.account_code
        WHERE c.account_code LIKE '6%'
          AND DATE_FORMAT(j.tanggal, '%Y-%m') IN ($in_clause)
        GROUP BY c.account_name";
$res = $conn->query($sql);

if ($res) {
    while ($r = $res->fetch_assoc()) {
        $val = (float)$r['total_kredit'];
        $biaya_items[] = [
            'idby' => '',
            'namaby' => $r['jenis'],
            'value' => $val
        ];
        $total_biaya += $val;
    }
    $res->free();
}

// 4) LABA / RUGI
$laba_rugi = $pendapatan - $hpp - $total_biaya;

// format helper
function fmt($v) { return number_format((float)$v, 2, '.', ','); }

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Laba Rugi</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    body{font-family:Arial, sans-serif;margin:20px;background:#f7f7f7;color:#222}
    .card{background:#fff;padding:18px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06)}
    .home-btn {
    position: fixed;
    top: 10px;
    left: 10px;
    font-size: 22px;
    color: black;
    text-decoration: none;
    z-index: 999;
}
    h1{margin:0 0 12px;color:#2c6f2c}
    form{margin-bottom:16px}
    select[multiple]{min-height:120px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:8px;border:1px solid #e6e6e6;text-align:right}
    th{background:#2c6f2c;color:#fff}
    td.left{text-align:left}
    .summary{display:flex;gap:20px;flex-wrap:wrap;margin-top:12px}
    .box{flex:1;min-width:200px;padding:12px;border-radius:8px;background:#fafafa;border:1px solid #eee}
    .big{font-size:1.25rem;font-weight:700}
    .profit{color:green}
    .loss{color:red}
    .btn{display:inline-block;padding:8px 12px;background:#2c6f2c;color:#fff;border-radius:6px;text-decoration:none}
</style>
</head>
<body>
<a href="home.php" class="home-btn">
    <i class="fa-solid fa-house"></i>
</a>
<div class="card">
    <h1>Laporan Laba Rugi</h1>

    <form method="POST" action="">
        <label for="months"><strong>Pilih bulan (boleh beberapa):</strong></label><br>
        <select name="months[]" id="months" multiple size="8">
            <?php foreach ($month_options as $k => $lbl): ?>
                <option value="<?php echo $k; ?>" <?php echo in_array($k, $selected_months) ? 'selected' : ''; ?>>
                    <?php echo $lbl; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div style="margin-top:10px;">
            <button class="btn" type="submit"><i class="fa fa-filter"></i> Tampilkan</button>
            <a class="btn" href="labarugi.php" style="background:#666;margin-left:8px">Reset</a>
        </div>
    </form>

    <div class="summary">
        <div class="box">
            <div>Pendapatan (Total Harga)</div>
            <div class="big">Rp <?php echo fmt($pendapatan); ?></div>
        </div>
        <div class="box">
            <div>HPP (Total DPP)</div>
            <div class="big">Rp <?php echo fmt($hpp); ?></div>
        </div>
        <div class="box">
            <div>Total Biaya (kas kredit sesuai biaya aktif)</div>
            <div class="big">Rp <?php echo fmt($total_biaya); ?></div>
        </div>
        <div class="box">
            <div>Net (Laba / Rugi)</div>
            <div class="big <?php echo ($laba_rugi>=0)?'profit':'loss'; ?>">
                Rp <?php echo fmt($laba_rugi); ?>
            </div>
        </div>
    </div>

    <h3>Rincian Biaya</h3>
    <table>
        <thead>
            <tr>
                <th style="text-align:left">Nama Biaya</th>
                <th>Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($biaya_items) === 0): ?>
                <tr><td class="left">Tidak ada data biaya aktif</td><td>0.00</td></tr>
            <?php else: ?>
                <?php foreach ($biaya_items as $it): ?>
                    <tr>
                        <td class="left"><?php echo htmlspecialchars($it['namaby']); ?></td>
                        <td><?php echo fmt($it['value']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <tr style="font-weight:bold;background:#f0f0f0">
                <td class="left">TOTAL BIAYA</td>
                <td><?php echo fmt($total_biaya); ?></td>
            </tr>
        </tbody>
    </table>

    <h3>Periode terpilih</h3>
    <p><?php echo implode(', ', array_map(function($m) use ($month_options){ return $month_options[$m] ?? $m; }, $selected_months)); ?></p>
</div>
</body>
</html>
<?php
// tutup koneksi
$conn->close();
