<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once 'config1.php';

/* ================= PARAMETER ================= */
$bulan_akhir = isset($_GET['bulan_akhir']) ? (int)$_GET['bulan_akhir'] : (int)date('m');
$tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');

$selected_location = $_GET['location'] ?? '';
$selected_devisi = $_GET['devisi'] ?? '';

if ($selected_location === 'ALL') $selected_location = '';
if ($selected_devisi === 'ALL') $selected_devisi = '';

$tanggal_cutoff_awal = "2000-01-01";
$tanggal_cutoff_akhir = date("Y-m-t", strtotime("$tahun-" . str_pad($bulan_akhir, 2, '0', STR_PAD_LEFT) . "-01"));

/* ================= KONEKSI ================= */

if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

/* ================= QUERY ================= */
$filter_location = !empty($selected_location) ? "AND j.location = ?" : "";
$filter_devisi = !empty($selected_devisi) ? "AND j.devisi = ?" : "";

$query = "
SELECT
    c.account_code,
    c.account_name,
    c.layer,
    c.parent_account,

    COALESCE(
        SUM(
            CASE
                WHEN j.tanggal < ?
                THEN j.debet - j.kredit
                ELSE 0
            END
        ),0
    ) AS saldo_awal,

    COALESCE(
        SUM(
            CASE
                WHEN j.tanggal BETWEEN ? AND ?
                THEN j.debet
                ELSE 0
            END
        ),0
    ) AS total_debet,

    COALESCE(
        SUM(
            CASE
                WHEN j.tanggal BETWEEN ? AND ?
                THEN j.kredit
                ELSE 0
            END
        ),0
    ) AS total_kredit,

    COALESCE(
        SUM(
            CASE
                WHEN j.tanggal <= ?
                THEN j.debet - j.kredit
                ELSE 0
            END
        ),0
    ) AS saldo_akhir

FROM coa c

LEFT JOIN jurnal j
    ON c.account_code = j.coa
   AND j.journal_number IS NOT NULL

WHERE (
       c.account_code LIKE '1%'
    OR c.account_code LIKE '2%'
    OR c.account_code LIKE '3%'
)

$filter_location
$filter_devisi

GROUP BY
    c.account_code,
    c.account_name,
    c.layer,
    c.parent_account

HAVING NOT (
    c.layer = 4
    AND saldo_akhir BETWEEN -0.0001 AND 0.0001
    AND c.account_code NOT IN (
        '31.02.01.001',
        '31.03.01.001'
    )
)

ORDER BY c.account_code ASC
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die('Prepare gagal : ' . $conn->error);
}

$param_types = 'ssssss';

$params = [
    $tanggal_cutoff_awal,
    $tanggal_cutoff_awal,
    $tanggal_cutoff_akhir,
    $tanggal_cutoff_awal,
    $tanggal_cutoff_akhir,
    $tanggal_cutoff_akhir
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
    $account_code,
    $account_name,
    $layer,
    $parent_account,
    $saldo_awal,
    $total_debet,
    $total_kredit,
    $saldo_akhir
);

/* ================= BUILD DATA ================= */
$data = [];

while ($stmt->fetch()) {

    $first = substr($account_code, 0, 1);

    $saldo_awal_debet = 0;
    $saldo_awal_kredit = 0;

    if ($first == '1') $saldo_awal_debet = max(0, $saldo_awal);
    if ($first == '2' || $first == '3') $saldo_awal_kredit = max(0, $saldo_awal);

    if ($first == '1') {
        $saldo_akhir = $saldo_awal_debet + $total_debet - $total_kredit;
    } else {
        $saldo_akhir = $saldo_awal_kredit - $total_debet + $total_kredit;
    }

    $data[$account_code] = [
        'account_code' => $account_code,
        'account_name' => $account_name,
        'layer' => $layer,
        'saldo_awal_debet' => $saldo_awal_debet,
        'saldo_awal_kredit' => $saldo_awal_kredit,
        'total_debet' => $total_debet,
        'total_kredit' => $total_kredit,
        'saldo_akhir' => $saldo_akhir,
        'debet_neraca' => $first == '1' ? $saldo_akhir : 0,
        'kredit_neraca' => ($first == '2' || $first == '3') ? $saldo_akhir : 0
    ];
}

ksort($data);
/* ======================================================
   HITUNG SELISIH NERACA TAHUN SEBELUMNYA
   -> COA 31.02.01.000
====================================================== */

$tahun_awal = $tahun . '-01-01';

$filter_sql_prev = '';
$param_prev = [$tahun_awal];
$type_prev = 's';

if (!empty($selected_location)) {
    $filter_sql_prev .= " AND j.location = ? ";
    $param_prev[] = $selected_location;
    $type_prev .= 's';
}

if (!empty($selected_devisi)) {
    $filter_sql_prev .= " AND j.devisi = ? ";
    $param_prev[] = $selected_devisi;
    $type_prev .= 's';
}

$sql_prev = "
SELECT
    COALESCE(SUM(
        CASE
            WHEN c.account_code LIKE '1%'
            THEN (j.debet - j.kredit)
            ELSE 0
        END
    ),0) AS aset,

    COALESCE(SUM(
        CASE
            WHEN c.account_code LIKE '2%'
              OR c.account_code LIKE '3%'
            THEN (j.kredit - j.debet)
            ELSE 0
        END
    ),0) AS modal

FROM jurnal j
INNER JOIN coa c ON c.account_code=j.coa
WHERE j.journal_number IS NOT NULL
  AND j.tanggal < ?
  $filter_sql_prev
";

$stmt_prev = $conn->prepare($sql_prev);
$stmt_prev->bind_param($type_prev, ...$param_prev);
$stmt_prev->execute();

$res_prev = $stmt_prev->get_result()->fetch_assoc();

$selisih_tahun_sebelumnya =
    (float)$res_prev['aset']
    - (float)$res_prev['modal'];

$stmt_prev->close();


/* ======================================================
   HITUNG SELISIH NERACA TAHUN BERJALAN
   -> COA 31.03.01.000
====================================================== */

$filter_sql_curr = '';
$param_curr = [
    $tahun_awal,
    $tanggal_cutoff_akhir
];
$type_curr = 'ss';

if (!empty($selected_location)) {
    $filter_sql_curr .= " AND j.location = ? ";
    $param_curr[] = $selected_location;
    $type_curr .= 's';
}

if (!empty($selected_devisi)) {
    $filter_sql_curr .= " AND j.devisi = ? ";
    $param_curr[] = $selected_devisi;
    $type_curr .= 's';
}

$sql_curr = "
SELECT
    COALESCE(SUM(
        CASE
            WHEN c.account_code LIKE '1%'
            THEN (j.debet - j.kredit)
            ELSE 0
        END
    ),0) AS aset,

    COALESCE(SUM(
        CASE
            WHEN c.account_code LIKE '2%'
              OR c.account_code LIKE '3%'
            THEN (j.kredit - j.debet)
            ELSE 0
        END
    ),0) AS modal

FROM jurnal j
INNER JOIN coa c ON c.account_code=j.coa
WHERE j.journal_number IS NOT NULL
  AND j.tanggal >= ?
  AND j.tanggal <= ?
  $filter_sql_curr
";

$stmt_curr = $conn->prepare($sql_curr);
$stmt_curr->bind_param($type_curr, ...$param_curr);
$stmt_curr->execute();

$res_curr = $stmt_curr->get_result()->fetch_assoc();

$selisih_tahun_berjalan =
    (float)$res_curr['aset']
    - (float)$res_curr['modal'];

$stmt_curr->close();


/* ======================================================
   MASUKKAN KE COA KHUSUS
====================================================== */

if (isset($data['31.02.01.001'])) {

    $data['31.02.01.001']['debet_neraca'] = 0;

    // Tetap di kredit meskipun negatif
    $data['31.02.01.001']['kredit_neraca']
        = $selisih_tahun_sebelumnya;

    $data['31.02.01.001']['saldo_akhir']
        = $selisih_tahun_sebelumnya;
}

if (isset($data['31.03.01.001'])) {

    $data['31.03.01.001']['debet_neraca'] = 0;

    // Tetap di kredit meskipun negatif
    $data['31.03.01.001']['kredit_neraca']
        = $selisih_tahun_berjalan;

    $data['31.03.01.001']['saldo_akhir']
        = $selisih_tahun_berjalan;
}
/* ================= AKUMULASI PARENT (FIX) ================= */
/* ================= AKUMULASI PARENT ================= */

foreach ($data as $key => &$values) {

    $values['debet_neraca'] =
        (float)$values['debet_neraca'];

    $values['kredit_neraca'] =
        (float)$values['kredit_neraca'];
}

unset($values);

foreach ($data as $key => &$values) {

    if ($values['layer'] == 4) {
        continue;
    }

    $values['debet_neraca'] = 0;
    $values['kredit_neraca'] = 0;

    foreach ($data as $child_key => $child) {

        if ($child['layer'] <= $values['layer']) {
            continue;
        }

        $match = false;

        if (
            $values['layer'] == 1 &&
            substr($child['account_code'],0,3)
            == substr($values['account_code'],0,3)
        ) {
            $match = true;
        }

        if (
            $values['layer'] == 2 &&
            substr($child['account_code'],0,6)
            == substr($values['account_code'],0,6)
        ) {
            $match = true;
        }

        if (
            $values['layer'] == 3 &&
            substr($child['account_code'],0,9)
            == substr($values['account_code'],0,9)
        ) {
            $match = true;
        }

        if ($match) {
            $values['debet_neraca'] += $child['debet_neraca'];
            $values['kredit_neraca'] += $child['kredit_neraca'];
        }
    }
}

unset($values);

/* ================= TOTAL ================= */
$total_debet = 0;
$total_kredit = 0;

foreach ($data as $row) {
    if ($row['layer'] == 4) {
        $total_debet += $row['debet_neraca'];
        $total_kredit += $row['kredit_neraca'];
    }
}

/* ================= EXCEL ================= */
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$sheet->fromArray([
    ['Layer 1','Layer 2','Layer 3','Layer 4','Nama Akun','Debet','Kredit']
], NULL, 'A1');

$rowNum = 2;

foreach ($data as $row) {
    $sheet->setCellValue("A$rowNum", $row['layer']==1?$row['account_code']:'');
    $sheet->setCellValue("B$rowNum", $row['layer']==2?$row['account_code']:'');
    $sheet->setCellValue("C$rowNum", $row['layer']==3?$row['account_code']:'');
    $sheet->setCellValue("D$rowNum", $row['layer']==4?$row['account_code']:'');
    $sheet->setCellValue("E$rowNum", $row['account_name']);
    $sheet->setCellValue("F$rowNum", $row['debet_neraca']);
    $sheet->setCellValue("G$rowNum", $row['kredit_neraca']);
    $rowNum++;
}

/* TOTAL */
$sheet->setCellValue("E$rowNum", "TOTAL");
$sheet->setCellValue("F$rowNum", $total_debet);
$sheet->setCellValue("G$rowNum", $total_kredit);
$rowNum++;

/* SELISIH */
$sheet->setCellValue("E$rowNum", "SELISIH");
$sheet->setCellValue("G$rowNum", $total_debet - $total_kredit);

/* FORMAT */
$sheet->getStyle("F2:G$rowNum")
      ->getNumberFormat()
      ->setFormatCode('#,##0');

/* OUTPUT */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=neraca_$bulan_akhir"."_$tahun.xlsx");

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>