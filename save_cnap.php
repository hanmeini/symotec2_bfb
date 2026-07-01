<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'use_only_cookies'=> true,
    'use_strict_mode' => true,
]);

/* ================= VALIDASI LOGIN ================= */
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

/* ================= LOAD CONFIG ================= */
require_once 'config1.php';

/* ================= KONEKSI DATABASE ================= */






/* ================= HANYA METHOD POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Akses tidak valid.");
}

/* ================= AMBIL DATA ================= */
$tanggal    = trim($_POST['tanggal'] ?? '');
$sup        = trim($_POST['kode'] ?? '');
$description= trim($_POST['description'] ?? '');
$cn         = trim($_POST['cn'] ?? '0');

/* ================= VALIDASI ================= */
if (
    empty($tanggal) ||
    empty($sup) ||
    empty($description)
) {
    die("Data belum lengkap.");
}

/* ================= FORMAT CN ================= */
$cn = str_replace(',', '', $cn);

if (!is_numeric($cn)) {
    die("Nilai CN tidak valid.");
}

$cn = (float)$cn;

if ($cn <= 0) {
    die("Nilai CN harus lebih besar dari 0.");
}

/* ================= VALIDASI CLOSE ================= */
$bulanInput = (int)date('m', strtotime($tanggal));
$tahunInput = (int)date('Y', strtotime($tanggal));

$queryClose = "
    SELECT bulan, tahun
    FROM close
    ORDER BY tahun DESC, bulan DESC
    LIMIT 1
";

$resultClose = $conn->query($queryClose);

if ($resultClose && $resultClose->num_rows > 0) {

    $close = $resultClose->fetch_assoc();

    $latestMonth = (int)$close['bulan'];
    $latestYear  = (int)$close['tahun'];

    if (
        $tahunInput < $latestYear ||
        (
            $tahunInput == $latestYear &&
            $bulanInput <= $latestMonth
        )
    ) {
        die("Periode sudah di-close.");
    }
}

/* ================= GENERATE NOMOR CN ================= */
/*
Format:
CN-20260515-0001
*/

$tanggalKode = date('Ymd', strtotime($tanggal));

$sqlLast = "
    SELECT no_cn_dn
    FROM cndn
    WHERE no_cn_dn LIKE 'CN-$tanggalKode-%'
    ORDER BY idn DESC
    LIMIT 1
";

$resultLast = $conn->query($sqlLast);

$urut = 1;

if ($resultLast && $resultLast->num_rows > 0) {

    $rowLast = $resultLast->fetch_assoc();

    $lastNo = $rowLast['no_cn_dn'];

    $explode = explode('-', $lastNo);

    if (isset($explode[2])) {
        $urut = (int)$explode[2] + 1;
    }
}

$nomorCN = 'CN-' . $tanggalKode . '-' . str_pad($urut, 4, '0', STR_PAD_LEFT);

/* ================= SIMPAN DATA ================= */
$sqlInsert = "
    INSERT INTO cndn
    (
        no_cn_dn,
        kode_booking,
        cn,
        dn,
        sup,
        tanggal,
        inv,
        id_parent,
        description
    )
    VALUES
    (
        ?,
        NULL,
        ?,
        0,
        ?,
        ?,
        NULL,
        NULL,
        ?
    )
";

$stmt = $conn->prepare($sqlInsert);

if (!$stmt) {
    die("Prepare gagal: " . $conn->error);
}

$stmt->bind_param(
    "sdiss",
    $nomorCN,
    $cn,
    $sup,
    $tanggal,
    $description
);

if (!$stmt->execute()) {
    die("Gagal menyimpan data: " . $stmt->error);
}

$stmt->close();

/* ================= REDIRECT ================= */
echo "
<script>
alert('CN AP berhasil disimpan');

window.location.href='allcnap.php';
</script>
";
exit;
?>