<?php













require_once 'config1.php';

$start_date = $_POST['start_date'] ?? date('Y-m-d');
$end_date   = $_POST['end_date'] ?? date('Y-m-d');
?>
<script>
function openPopup(url) {
    window.open(url,'_blank','width=800,height=850,scrollbars=yes,resizable=yes');
}
</script>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Laporan Penjualan Harian</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body{font-family:Arial;background:#f9f9f9;padding:20px}
table{width:100%;border-collapse:collapse;margin-top:15px}
th,td{border:1px solid #ccc;padding:8px;text-align:right}
th{background:#4CAF50;color:#fff;text-align:center}
tr:nth-child(even){background:#f2f2f2}
.total-row{background:#e0e0e0;font-weight:bold}
</style>
</head>
<body>

<h1 style="text-align:center">Laporan Penjualan Harian</h1>

<form method="post" style="text-align:center">
    <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
    <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
    <button type="submit">Tampilkan</button>
</form>

<?php
$userid = $_SESSION['userid'] ?? 0;
$jabatan = $_SESSION['jabatan'] ?? 0;
$username = $_SESSION['username'] ?? '';
$is_sales = false;

if ($_SESSION['bagian'] === 'sales') {
    $stmt_cek = $conn->prepare("SELECT COUNT(*) FROM master_sales WHERE userid = ?");
    $stmt_cek->bind_param("i", $userid);
    $stmt_cek->execute();
    $stmt_cek->bind_result($sales_count);
    $stmt_cek->fetch();
    $stmt_cek->close();
    if ($sales_count > 0) $is_sales = true;
}

if ($is_sales) {
    $sql = "
    SELECT tanggal_transaksi,J,cust,diskon,harga,ppn,jumlah,bank,bayar,sisa,fp_k,userinv,userbayar
    FROM penjualanHO1
    WHERE DATE(tanggal_transaksi) BETWEEN ? AND ?
    AND userinv = ?
    AND J IS NOT NULL
    ORDER BY J
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $start_date, $end_date, $username);
    $stmt->execute();
} else {
    $sql = "
    SELECT tanggal_transaksi,J,cust,diskon,harga,ppn,jumlah,bank,bayar,sisa,fp_k,userinv,userbayar
    FROM penjualanHO1
    WHERE DATE(tanggal_transaksi) BETWEEN ? AND ?
    AND J IS NOT NULL
    ORDER BY J
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
}
$stmt->bind_result(
    $tanggal,$J,$cust,$diskon,$harga,$ppn,$jumlah,
    $bank,$bayar,$sisa,$fp_k,$userinv,$userbayar
);

/* ===== INIT ===== */
$userinv_summary = [];
$bank_list = [];
$total_jumlah = 0;

echo "<table>
<tr>
<th>Tanggal</th><th>No Inv</th><th>Customer</th>
<th>DPP</th><th>PPN</th><th>Jumlah</th>
<th>Bayar</th><th>Sisa</th><th>Bank</th><th>Action</th>
</tr>";

while ($stmt->fetch()) {

    $total_jumlah += $jumlah;

    if (!isset($userinv_summary[$userinv])) {
        $userinv_summary[$userinv] = [
            'banks' => [],
            'total' => 0
        ];
    }

    $userinv_summary[$userinv]['banks'][$bank] =
        ($userinv_summary[$userinv]['banks'][$bank] ?? 0) + $jumlah;

    $userinv_summary[$userinv]['total'] += $jumlah;
    $bank_list[$bank] = true;

    echo "<tr>
        <td>".htmlspecialchars($tanggal)."</td>
        <td>".htmlspecialchars($J)."<br>".htmlspecialchars($userinv)."</td>
        <td>".htmlspecialchars($cust)."</td>
        <td>".number_format($harga,2)."</td>
        <td>".number_format($ppn,2)."</td>
        <td>".number_format($jumlah,2)."</td>
        <td>".number_format($bayar,2)."</td>
        <td>".number_format($sisa,2)."</td>
        <td>".htmlspecialchars($bank)."</td>
        <td>
            <a onclick=\"openPopup('cetak_sj.php?J=$J')\" style=\"cursor:pointer; color:blue; margin-right:10px;\">SJ</a>
            <a onclick=\"openPopup('nota.php?J=$J')\" style=\"cursor:pointer; color:green;\">Nota</a>
        </td>
    </tr>";
}

echo "<tr class='total-row'>
<td colspan='5'>TOTAL</td>
<td>".number_format($total_jumlah,2)."</td>
<td colspan='4'></td>
</tr>";
echo "</table>";

/* ===== BANK LIST (MAX 10) ===== */
$bank_list = array_slice(array_keys($bank_list), 0, 10);

/* ===== INIT TOTAL KOLOM ===== */
$bank_totals = [];
$grand_total = 0;
foreach ($bank_list as $b) {
    $bank_totals[$b] = 0;
}
?>

<h3 style="text-align:center">Summary User Inv</h3>

<table style="width:70%;margin:auto">
<tr>
<th>User Inv</th>
<?php foreach ($bank_list as $b): ?>
    <th><?= htmlspecialchars($b) ?></th>
<?php endforeach; ?>
<th>Total</th>
</tr>

<?php foreach ($userinv_summary as $u => $data): ?>
<tr>
    <td style="text-align:center"><?= htmlspecialchars($u) ?></td>
    <?php foreach ($bank_list as $b):
        $val = $data['banks'][$b] ?? 0;
        $bank_totals[$b] += $val;
    ?>
        <td><?= number_format($val,2) ?></td>
    <?php endforeach; ?>
    <?php $grand_total += $data['total']; ?>
    <td><b><?= number_format($data['total'],2) ?></b></td>
</tr>
<?php endforeach; ?>

<tr class="total-row">
    <td style="text-align:center">TOTAL</td>
    <?php foreach ($bank_list as $b): ?>
        <td><?= number_format($bank_totals[$b],2) ?></td>
    <?php endforeach; ?>
    <td><?= number_format($grand_total,2) ?></td>
</tr>
</table>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
