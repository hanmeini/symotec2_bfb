<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config1.php';

// Auto-migrate / create data_karyawan table if it does not exist
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'data_karyawan'");
    if ($table_check->num_rows == 0) {
        $conn->query("CREATE TABLE `data_karyawan` (
          `no_staff` int NOT NULL PRIMARY KEY,
          `nama` varchar(100) DEFAULT NULL,
          `LP` enum('L','P') NOT NULL DEFAULT 'L',
          `dept` varchar(50) DEFAULT NULL,
          `jabatan` varchar(50) DEFAULT NULL,
          `tgl_masuk` date DEFAULT NULL,
          `tgl_lahir` date DEFAULT NULL,
          `alamat` text,
          `foto` varchar(255) DEFAULT NULL,
          `nik` varchar(20) DEFAULT NULL,
          `foto_ktp` varchar(255) DEFAULT NULL,
          `kk` varchar(20) DEFAULT NULL,
          `foto_kk` varchar(255) DEFAULT NULL,
          `status_menikah` enum('Menikah','Belum Menikah') DEFAULT 'Belum Menikah',
          `jumlah_tanggungan` int DEFAULT 0,
          `no_telp` varchar(20) DEFAULT NULL,
          `pendidikan` varchar(50) DEFAULT NULL,
          `nama_darurat` varchar(100) DEFAULT NULL,
          `no_darurat` varchar(20) DEFAULT NULL,
          `bpjs_kes` decimal(60,2) DEFAULT 0.00,
          `bpjs_tk` decimal(60,2) DEFAULT 0.00,
          `gaji_pokok` decimal(15,2) DEFAULT 0.00,
          `upah_lembur` decimal(15,2) DEFAULT 0.00,
          `aktive` enum('aktive','nonaktive') NOT NULL DEFAULT 'aktive',
          `jenis_gaji` enum('bulanan','mingguan') NOT NULL DEFAULT 'bulanan'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // No dummy data seeded initially. Actual employees will be auto-imported when uploading XLS.
    }
} catch (Exception $e) {
    error_log("Error in auto-migration for data_karyawan: " . $e->getMessage());
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Fetch unique departments, positions, and rates for dropdowns
$dept_jabatan_map = [];
$depts = [];
$rates_map = [];
$r_map = $conn->query("SELECT dept, jabatan, MAX(gaji_harian) as gaji_harian, MAX(upah_lembur_jam) as upah_lembur_jam FROM rate_gaji_harian GROUP BY dept, jabatan ORDER BY dept ASC, jabatan ASC");
if ($r_map) {
    while($row = $r_map->fetch_assoc()){
        $d = $row['dept'];
        $j = $row['jabatan'];
        if (!in_array($d, $depts)) {
            $depts[] = $d;
        }
        if (!isset($dept_jabatan_map[$d])) {
            $dept_jabatan_map[$d] = [];
        }
        $dept_jabatan_map[$d][] = $j;
        $rates_map[$d . '|' . $j] = [
            'gaji' => (float)$row['gaji_harian'],
            'lembur' => (float)$row['upah_lembur_jam']
        ];
    }
}
$dept_jabatan_json = json_encode($dept_jabatan_map);
$rates_json = json_encode($rates_map);

// POST Handler (Add / Edit / Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'CSRF token tidak valid.']);
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $no_staff = (int)($_POST['no_staff'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $lp = ($_POST['LP'] ?? 'L') === 'P' ? 'P' : 'L';
        $dept = trim($_POST['dept'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');
        
        $gaji_pokok = (float)($_POST['gaji_pokok'] ?? 0.00);
        $upah_lembur = (float)($_POST['upah_lembur'] ?? 0.00);
        $jenis_gaji = ($_POST['jenis_gaji'] ?? 'bulanan') === 'mingguan' ? 'mingguan' : 'bulanan';
        $aktive = ($_POST['aktive'] ?? 'aktive') === 'nonaktive' ? 'nonaktive' : 'aktive';

        if ($no_staff <= 0 || $nama === '' || $dept === '' || $jabatan === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Semua kolom wajib diisi dengan benar.']);
            exit();
        }

        // Cek duplikasi no_staff untuk mode 'add'
        if ($action === 'add') {
            $cek = $conn->prepare("SELECT no_staff FROM data_karyawan WHERE no_staff = ?");
            $cek->bind_param("i", $no_staff);
            $cek->execute();
            $cek->store_result();
            if ($cek->num_rows > 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No. Staff sudah terdaftar. Gunakan nomor lain.']);
                $cek->close();
                exit();
            }
            $cek->close();
        }

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO data_karyawan (no_staff, nama, LP, dept, jabatan, gaji_pokok, upah_lembur, jenis_gaji, aktive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssddss", $no_staff, $nama, $lp, $dept, $jabatan, $gaji_pokok, $upah_lembur, $jenis_gaji, $aktive);
        } else {
            $stmt = $conn->prepare("UPDATE data_karyawan SET nama=?, LP=?, dept=?, jabatan=?, gaji_pokok=?, upah_lembur=?, jenis_gaji=?, aktive=? WHERE no_staff=?");
            $stmt->bind_param("ssssddssi", $nama, $lp, $dept, $jabatan, $gaji_pokok, $upah_lembur, $jenis_gaji, $aktive, $no_staff);
        }

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => $action === 'add' ? 'Karyawan berhasil ditambahkan.' : 'Data karyawan berhasil diperbarui.',
                'data' => [
                    'no_staff' => $no_staff,
                    'nama' => $nama,
                    'LP' => $lp,
                    'dept' => $dept,
                    'jabatan' => $jabatan,
                    'gaji_pokok' => $gaji_pokok,
                    'upah_lembur' => $upah_lembur,
                    'jenis_gaji' => $jenis_gaji,
                    'aktive' => $aktive
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data karyawan.']);
        }
        $stmt->close();
        exit();
    }

    if ($action === 'delete') {
        $no_staff = (int)($_POST['no_staff'] ?? 0);
        if ($no_staff <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No. Staff tidak valid.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM data_karyawan WHERE no_staff = ?");
        $stmt->bind_param("i", $no_staff);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Data karyawan berhasil dihapus.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus data karyawan.']);
        }
        $stmt->close();
        exit();
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
    exit();
}

// Fetch all employees
$result = $conn->query("SELECT * FROM data_karyawan ORDER BY no_staff ASC");
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$total_employees = count($employees);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Karyawan - JANICO</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #256f5a;
            --primary-dark: #1b5343;
            --primary-soft: #eaf6f2;
            --accent: #b7791f;
            --accent-soft: #fff4dd;
            --danger: #d84c4c;
            --ink: #1f2d37;
            --muted: #687782;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --surface: #fff;
            --radius: 8px;
            --shadow: 0 16px 42px rgba(37, 111, 90, .08);
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, rgba(37, 111, 90, .08), transparent 330px), var(--bg);
        }

        .app-nav { background: var(--primary-dark); }
        .navbar-brand { font-weight: 800; letter-spacing: .02em; }
        .brand-mark {
            width: 38px; height: 38px; border-radius: var(--radius);
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,.14); color: #fff;
        }

        .page-shell { width: min(1540px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 46px; }
        .page-header {
            display: grid; grid-template-columns: minmax(0,1fr) auto;
            gap: 16px; align-items: end; margin-bottom: 22px;
        }
        .eyebrow { color: var(--primary); font-size: .78rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .page-title { margin: 6px 0 0; color: var(--primary-dark); font-size: clamp(1.7rem,3vw,2.35rem); font-weight: 800; }
        .page-subtitle { max-width: 760px; margin: 8px 0 0; color: var(--muted); line-height: 1.55; }

        .stat-grid { display: grid; grid-template-columns: repeat(2,minmax(140px,1fr)); gap: 12px; margin-bottom: 22px; }
        .stat-card {
            border: 1px solid var(--line); border-radius: var(--radius);
            background: rgba(255,255,255,.82); padding: 16px;
            box-shadow: 0 8px 24px rgba(37,111,90,.04);
        }
        .stat-card span { display: block; color: var(--muted); font-size: .82rem; font-weight: 700; }
        .stat-card strong { display: block; margin-top: 6px; color: var(--primary-dark); font-size: 1.65rem; line-height: 1; }

        .panel { border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); overflow: hidden; }
        .panel-head {
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            padding: 18px 20px; border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, var(--primary-soft), #fff);
        }
        .title-wrap { display: flex; align-items: center; gap: 12px; }
        .title-icon {
            width: 42px; height: 42px; border-radius: var(--radius);
            display: inline-flex; align-items: center; justify-content: center;
            flex: 0 0 auto; background: var(--primary); color: #fff;
        }
        .panel-title { margin: 0; color: var(--primary-dark); font-size: 1.06rem; font-weight: 800; }
        .panel-subtitle { display: block; margin-top: 2px; color: var(--muted); font-size: .83rem; }
        .form-body, .table-wrap { padding: 20px; }
        .section-label {
            display: flex; align-items: center; gap: 8px; margin: 6px 0 14px;
            color: var(--primary); font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
        }
        .section-label:after { content: ""; height: 1px; flex: 1; background: var(--line); }
        .form-label { color: #3f4f59; font-size: .82rem; font-weight: 700; margin-bottom: 7px; }
        .form-control, .form-select {
            min-height: 43px; border: 1px solid var(--line); border-radius: var(--radius);
            color: var(--ink); font-size: .92rem; box-shadow: none;
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(37,111,90,.15); }
        .btn { border-radius: var(--radius); font-weight: 700; }
        .btn-success { background: var(--primary); border-color: var(--primary); }
        .btn-success:hover { background: var(--primary-dark); border-color: var(--primary-dark); }
        .btn-icon { width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }

        .table thead th {
            padding: 13px 12px; border: 0; background: var(--primary-dark) !important;
            color: #fff !important; font-size: .78rem; font-weight: 800; text-transform: uppercase; white-space: nowrap;
        }
        .table tbody td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: .9rem; }
        .table-hover tbody tr:hover td { background: #f8fafc; }
        .emp-name { color: var(--ink); font-weight: 800; }
        .pill {
            display: inline-flex; align-items: center; gap: 6px; border-radius: 999px;
            padding: 5px 9px; font-weight: 800; font-size: .78rem; white-space: nowrap;
        }
        .pill-code { color: var(--primary-dark); background: var(--primary-soft); }
        .badge-soft { color: var(--primary); background: var(--primary-soft); border-radius: 999px; padding: 6px 10px; font-weight: 800; }
        .table-success-subtle td { background: #eaf6f2 !important; transition: background-color 1.8s ease; }
        .table-info-subtle td { background: #f0fdf4 !important; transition: background-color 1.8s ease; }

        @media (max-width: 768px) {
            .page-shell { width: min(100% - 22px,1540px); padding-top: 18px; }
            .page-header, .stat-grid { grid-template-columns: 1fr; }
            .panel-head, .form-body, .table-wrap { padding-left: 14px; padding-right: 14px; }
        }
    </style>
</head>
<body>
        <nav class="navbar navbar-expand-lg navbar-dark app-nav mb-4" style="background: var(--primary-dark); padding: 10px 0;">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center gap-2" href="home.php" style="font-weight: 800; letter-spacing: .02em; color: #fff !important; text-decoration:none;">
                <span class="brand-mark" style="width: 38px; height: 38px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,.14); color: #fff;"><i class="fa-solid fa-cart-shopping"></i></span>
                <span>BFB</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="absen.php" class="btn btn-outline-light btn-sm" style="border: 1px solid rgba(255,255,255,.55); color: #fff; text-decoration: none; padding: 6px 12px; border-radius:4px;"><i class="fa-solid fa-calendar-check me-1"></i> Absensi</a>
                <a href="karyawan.php" class="btn btn-outline-light btn-sm" style="border: 1px solid rgba(255,255,255,.55); color: #fff; text-decoration: none; padding: 6px 12px; border-radius:4px;"><i class="fa-solid fa-users me-1"></i> Karyawan</a>
                <a href="gaji_harian.php" class="btn btn-outline-light btn-sm" style="border: 1px solid rgba(255,255,255,.55); color: #fff; text-decoration: none; padding: 6px 12px; border-radius:4px;"><i class="fa-solid fa-money-bill-wave me-1"></i> Gaji Harian</a>
                <a href="mutasi_harian.php" class="btn btn-outline-light btn-sm" style="border: 1px solid rgba(255,255,255,.55); color: #fff; text-decoration: none; padding: 6px 12px; border-radius:4px;"><i class="fa-solid fa-arrows-spin me-1"></i> Mutasi Harian</a>
                <a href="riwayat_gaji.php" class="btn btn-outline-light btn-sm" style="border: 1px solid rgba(255,255,255,.55); color: #fff; text-decoration: none; padding: 6px 12px; border-radius:4px;"><i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat</a>
                <a href="home.php" class="btn btn-outline-light btn-sm" style="border: 1px solid rgba(255,255,255,.55); color: #fff; text-decoration: none; padding: 6px 12px; border-radius:4px;"><i class="fa-solid fa-arrow-left me-1"></i> Dashboard</a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <header class="page-header">
            <div>
                <div class="eyebrow">Master Data</div>
                <h1 class="page-title">Master Karyawan</h1>
                <p class="page-subtitle">Kelola master data karyawan PT. JANICO untuk absensi dan perhitungan payroll.</p>
            </div>
            <button type="button" class="btn btn-success px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#karyawanModal">
                <i class="fa-solid fa-plus me-2"></i> Tambah Karyawan Baru
            </button>
        </header>

        <section class="stat-grid">
            <div class="stat-card"><span>Total Karyawan</span><strong id="statTotal"><?= e($total_employees) ?></strong></div>
            <div class="stat-card"><span>Status Aktif</span><strong style="color: #256f5a;">Semua Aktif</strong></div>
        </section>

        <div class="row">
            <div class="col-12">
                <section class="panel">
                    <div class="panel-head">
                        <div class="title-wrap">
                            <span class="title-icon"><i class="fa-solid fa-users"></i></span>
                            <div>
                                <h2 class="panel-title">Daftar Karyawan</h2>
                                <span class="panel-subtitle">Daftar staf, penugasan departemen, dan gaji pokok.</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table id="karyawanTable" class="table table-hover align-middle" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>No. Staff</th>
                                        <th>Nama Karyawan</th>
                                        <th>L/P</th>
                                        <th>Departemen</th>
                                        <th>Jabatan</th>
                                        <th>Jenis Gaji</th>
                                        <th>Gaji Pokok / Rate</th>
                                        <th>Upah Lembur / Jam</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $row): ?>
                                        <tr>
                                            <td><span class="pill pill-code"><i class="fa-solid fa-id-card"></i><?= e($row['no_staff']) ?></span></td>
                                            <td><div class="emp-name"><?= e($row['nama']) ?></div></td>
                                            <td><?= e($row['LP']) ?></td>
                                            <td><?= e($row['dept'] ?: '-') ?></td>
                                            <td><?= e($row['jabatan'] ?: '-') ?></td>
                                            <td>
                                                <span class="badge rounded text-uppercase <?= $row['jenis_gaji'] === 'mingguan' ? 'bg-warning text-dark' : 'bg-primary text-white' ?>">
                                                    <?= e($row['jenis_gaji']) ?>
                                                </span>
                                            </td>
                                            <td>Rp <?= number_format((float)$row['gaji_pokok'], 2, ',', '.') ?></td>
                                            <td>Rp <?= number_format((float)$row['upah_lembur'], 2, ',', '.') ?></td>
                                            <td>
                                                <span class="badge rounded-pill <?= $row['aktive'] === 'aktive' ? 'bg-success text-white' : 'bg-secondary text-white' ?>">
                                                    <?= e($row['aktive']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-icon btn-edit"
                                                        title="Edit karyawan"
                                                        data-no_staff="<?= e($row['no_staff']) ?>"
                                                        data-nama="<?= e($row['nama']) ?>"
                                                        data-lp="<?= e($row['LP']) ?>"
                                                        data-dept="<?= e($row['dept']) ?>"
                                                        data-jabatan="<?= e($row['jabatan']) ?>"
                                                        data-gaji_pokok="<?= e($row['gaji_pokok']) ?>"
                                                        data-upah_lembur="<?= e($row['upah_lembur']) ?>"
                                                        data-jenis_gaji="<?= e($row['jenis_gaji']) ?>"
                                                        data-aktive="<?= e($row['aktive']) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-delete"
                                                        title="Hapus karyawan"
                                                        data-no_staff="<?= e($row['no_staff']) ?>"
                                                        data-nama="<?= e($row['nama']) ?>">
                                                        <i class="fa-solid fa-trash-can"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- Hidden Data Storage for JS -->
    <div id="json-data" style="display:none;" 
         data-dept="<?= htmlspecialchars(empty($dept_jabatan_json) || $dept_jabatan_json === '[]' ? '{}' : $dept_jabatan_json, ENT_QUOTES, 'UTF-8') ?>" 
         data-rates="<?= htmlspecialchars(empty($rates_json) || $rates_json === '[]' ? '{}' : $rates_json, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <!-- Modal Form (Tambah / Edit) -->
    <div class="modal fade" id="karyawanModal" tabindex="-1" aria-labelledby="karyawanModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content panel shadow-lg">
                <div class="panel-head">
                    <div class="title-wrap">
                        <span class="title-icon"><i class="fa-solid fa-user-plus" id="formIcon"></i></span>
                        <div>
                            <h3 class="panel-title" id="formTitle">Tambah Karyawan</h3>
                            <span class="panel-subtitle" id="formSubtitle">Masukkan detail profil karyawan baru</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="karyawanForm" autocomplete="off">
                    <div class="modal-body form-body">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" id="action" value="add">

                        <div class="section-label">Info Utama</div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="no_staff" class="form-label">No. Staff (ID)</label>
                                <input type="number" class="form-control" id="no_staff" name="no_staff" required placeholder="Contoh: 156">
                            </div>
                            <div class="col-md-6">
                                <label for="LP" class="form-label">Jenis Kelamin</label>
                                <select class="form-select" id="LP" name="LP" required>
                                    <option value="L">Laki-laki</option>
                                    <option value="P">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Karyawan</label>
                            <input type="text" class="form-control" id="nama" name="nama" placeholder="Nama Lengkap Karyawan" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dept" class="form-label">Departemen</label>
                                <select class="form-select text-uppercase" id="dept" name="dept" required>
                                    <option value="">-- Pilih Departemen --</option>
                                    <?php foreach($depts as $dept): ?>
                                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                    <?php endforeach; ?>
                                    <option value="_LAINNYA_" class="text-primary fw-bold">+ Tambah Baru</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="jabatan" class="form-label">Jabatan</label>
                                <select class="form-select text-uppercase" id="jabatan" name="jabatan" required>
                                    <option value="">-- Pilih Jabatan --</option>
                                </select>
                            </div>
                        </div>

                        <div class="section-label">Gaji & Pembayaran</div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="jenis_gaji" class="form-label">Jenis Gaji</label>
                                <select class="form-select" id="jenis_gaji" name="jenis_gaji" required>
                                    <option value="bulanan">Bulanan (Fixed Pokok)</option>
                                    <option value="mingguan">Mingguan / Harian</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="aktive" class="form-label">Status</label>
                                <select class="form-select" id="aktive" name="aktive" required>
                                    <option value="aktive">Aktif</option>
                                    <option value="nonaktive">Non-Aktif</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label for="gaji_pokok" class="form-label">Gaji Pokok / Rate Harian</label>
                                <input type="number" step="any" min="0" class="form-control" id="gaji_pokok" name="gaji_pokok" placeholder="Nilai Gaji" required>
                            </div>
                            <div class="col-md-6">
                                <label for="upah_lembur" class="form-label">Upah Lembur / Jam</label>
                                <input type="number" step="any" min="0" class="form-control" id="upah_lembur" name="upah_lembur" placeholder="Rate Lembur" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end gap-2" style="border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4" id="btnSubmit">
                            <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Karyawan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/jquery.min.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
    <script src="assets/jquery.dataTables.min.js"></script>
    <script src="assets/dataTables.bootstrap5.min.js"></script>
    <script src="assets/sweetalert2.all.min.js"></script>

    <script>
        $(document).ready(function() {
            let salaryRates = {};
            try {
                let rawRates = $('#json-data').attr('data-rates');
                if (rawRates) salaryRates = JSON.parse(rawRates);
            } catch(e) {
                console.error("JSON parse error:", e);
            }

            // Update salary inputs when dept or jabatan changes
            $('#dept, #jabatan').on('change', function() {
                let d = $('#dept').val();
                let j = $('#jabatan').val();
                if (d && j) {
                    let key = d + '|' + j;
                    if (salaryRates[key]) {
                        $('#gaji_pokok').val(salaryRates[key].gaji);
                        $('#upah_lembur').val(salaryRates[key].lembur);
                    }
                }
            });
            let table = $('#karyawanTable').DataTable({
                pageLength: 10,
                columnDefs: [
                    { orderable: false, targets: [9] },
                    { className: 'text-center', targets: [0, 2, 5, 8, 9] }
                ],
                order: [[0, 'asc']],
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)',
                    zeroRecords: 'Data tidak ditemukan',
                    paginate: { first: 'Pertama', last: 'Terakhir', next: 'Berikutnya', previous: 'Sebelumnya' }
                }
            });

            function resetForm() {
                $('#karyawanForm')[0].reset();
                $('#dept').val('').trigger('change');
                $('#action').val('add');
                $('#no_staff').prop('readonly', false);
                $('#formTitle').text('Tambah Karyawan');
                $('#formSubtitle').text('Masukkan detail profil karyawan baru');
                $('#btnSubmit').html('<i class="fa-solid fa-floppy-disk me-2"></i> Simpan Karyawan');
                $('#formIcon').removeClass('fa-pen-to-square').addClass('fa-user-plus');
            }

            document.getElementById('karyawanModal').addEventListener('hidden.bs.modal', function () {
                resetForm();
            });

            let currentRow = null;

            $('#karyawanForm').on('submit', function(e) {
                e.preventDefault();
                const action = $('#action').val();
                const submitHtml = $('#btnSubmit').html();

                $('#btnSubmit').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Memproses...');

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status !== 'success') {
                            Swal.fire({ icon: 'error', title: 'Gagal', text: response.message, confirmButtonColor: '#d84c4c' });
                            return;
                        }

                        const item = response.data;
                        const formattedGaji = 'Rp ' + parseFloat(item.gaji_pokok).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const formattedLembur = 'Rp ' + parseFloat(item.upah_lembur).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        
                        const staffCol = `<span class="pill pill-code"><i class="fa-solid fa-id-card"></i>${item.no_staff}</span>`;
                        const namaCol = `<div class="emp-name">${item.nama}</div>`;
                        const typeBadge = `<span class="badge rounded text-uppercase ${item.jenis_gaji === 'mingguan' ? 'bg-warning text-dark' : 'bg-primary text-white'}">${item.jenis_gaji}</span>`;
                        const statusBadge = `<span class="badge rounded-pill ${item.aktive === 'aktive' ? 'bg-success text-white' : 'bg-secondary text-white'}">${item.aktive}</span>`;
                        
                        const actionCol = `
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-icon btn-edit"
                                    title="Edit karyawan"
                                    data-no_staff="${item.no_staff}"
                                    data-nama="${item.nama}"
                                    data-lp="${item.LP}"
                                    data-dept="${item.dept}"
                                    data-jabatan="${item.jabatan}"
                                    data-gaji_pokok="${item.gaji_pokok}"
                                    data-upah_lembur="${item.upah_lembur}"
                                    data-jenis_gaji="${item.jenis_gaji}"
                                    data-aktive="${item.aktive}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-delete"
                                    title="Hapus karyawan"
                                    data-no_staff="${item.no_staff}"
                                    data-nama="${item.nama}">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        `;

                        const rowData = [
                            staffCol,
                            namaCol,
                            item.LP,
                            item.dept,
                            item.jabatan,
                            typeBadge,
                            formattedGaji,
                            formattedLembur,
                            statusBadge,
                            actionCol
                        ];

                        if (action === 'add') {
                            const addedRow = table.row.add(rowData).draw(false);
                            $(addedRow.node()).addClass('table-success-subtle');
                            setTimeout(function() { $(addedRow.node()).removeClass('table-success-subtle'); }, 2000);
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Karyawan berhasil ditambahkan.', confirmButtonColor: '#256f5a' });
                            
                            let total = parseInt($('#statTotal').text()) + 1;
                            $('#statTotal').text(total);
                        } else {
                            table.row(currentRow).data(rowData).draw(false);
                            $(currentRow).addClass('table-info-subtle');
                            setTimeout(function() { $(currentRow).removeClass('table-info-subtle'); }, 2000);
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Data karyawan berhasil diperbarui.', confirmButtonColor: '#256f5a' });
                        }

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('karyawanModal')).hide();
                        resetForm();
                    },
                    error: function(xhr) {
                        const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Kesalahan komunikasi server.';
                        Swal.fire({ icon: 'error', title: 'Error', text: errMsg, confirmButtonColor: '#d84c4c' });
                    },
                    complete: function() {
                        $('#btnSubmit').prop('disabled', false).html(submitHtml);
                    }
                });
            });

            $('#karyawanTable').on('click', '.btn-edit', function() {
                currentRow = $(this).closest('tr');
                $('#no_staff').val($(this).data('no_staff')).prop('readonly', true);
                $('#nama').val($(this).data('nama'));
                $('#LP').val($(this).data('lp'));
                let deptVal = $(this).data('dept');
                let jabatanVal = $(this).data('jabatan');
                
                // Cek apakah dept ini ada di options, jika tidak tambahkan secara dinamis
                if ($('#dept option[value="'+deptVal+'"]').length === 0) {
                    $('#dept').append($('<option>', {value: deptVal, text: deptVal}));
                }
                $('#dept').val(deptVal).trigger('change');
                
                // Cek apakah jabatan ini ada di options
                setTimeout(function() {
                    if ($('#jabatan option[value="'+jabatanVal+'"]').length === 0) {
                        $('#jabatan').append($('<option>', {value: jabatanVal, text: jabatanVal}));
                    }
                    $('#jabatan').val(jabatanVal).trigger('change');
                }, 50);

                $('#gaji_pokok').val($(this).data('gaji_pokok'));
                $('#upah_lembur').val($(this).data('upah_lembur'));
                $('#jenis_gaji').val($(this).data('jenis_gaji'));
                $('#aktive').val($(this).data('aktive'));
                
                $('#action').val('edit');
                $('#formTitle').text('Edit Data Karyawan');
                $('#formSubtitle').text('Perbarui detail karyawan: ' + $(this).data('nama'));
                $('#btnSubmit').html('<i class="fa-solid fa-floppy-disk me-2"></i> Perbarui Karyawan');
                $('#formIcon').removeClass('fa-user-plus').addClass('fa-pen-to-square');
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('karyawanModal')).show();
            });

            $('#karyawanTable').on('click', '.btn-delete', function() {
                const row = $(this).closest('tr');
                const no_staff = $(this).data('no_staff');
                const nama = $(this).data('nama');

                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: `Anda akan menghapus data karyawan "${nama}" (No. Staff: ${no_staff}).`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d84c4c',
                    cancelButtonColor: '#687782',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: window.location.href,
                        type: 'POST',
                        data: {
                            csrf_token: $('input[name="csrf_token"]').val(),
                            action: 'delete',
                            no_staff: no_staff
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                table.row(row).remove().draw(false);
                                
                                const total = parseInt($('#statTotal').text()) - 1;
                                $('#statTotal').text(total);

                                Swal.fire({ icon: 'success', title: 'Dihapus!', text: `Karyawan "${nama}" berhasil dihapus.`, confirmButtonColor: '#256f5a' });
                            } else {
                                Swal.fire({ icon: 'error', title: 'Gagal', text: response.message, confirmButtonColor: '#d84c4c' });
                            }
                        },
                        error: function(xhr) {
                            const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Kesalahan komunikasi server.';
                            Swal.fire({ icon: 'error', title: 'Error', text: errMsg, confirmButtonColor: '#d84c4c' });
                        }
                    });
                });
            });

            // Cascading Dropdown for Dept -> Jabatan
            let deptJabatanMap = {};
            try {
                deptJabatanMap = JSON.parse($('#json-data').attr('data-dept') || '{}');
            } catch(e) { console.error(e); }
            
            $('#dept').on('change', function() {
                const selectedDept = this.value;
                const $jabatanSelect = $('#jabatan');
                
                if (selectedDept === '_LAINNYA_') {
                    $(this).val(''); // Reset temporarily
                    Swal.fire({
                        title: 'Departemen Baru',
                        input: 'text',
                        inputPlaceholder: 'Ketik nama departemen...',
                        showCancelButton: true,
                        confirmButtonText: 'Simpan',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed && result.value.trim() !== '') {
                            let newDept = result.value.trim().toUpperCase();
                            if ($('#dept option[value="'+newDept+'"]').length === 0) {
                                $('<option>').val(newDept).text(newDept).insertBefore($('#dept option[value="_LAINNYA_"]'));
                            }
                            $('#dept').val(newDept).trigger('change');
                        }
                    });
                    return;
                }
                
                $jabatanSelect.empty().append('<option value="">-- Pilih Jabatan --</option>');
                if (selectedDept && deptJabatanMap[selectedDept]) {
                    deptJabatanMap[selectedDept].forEach(jabatan => {
                        $jabatanSelect.append($('<option></option>').val(jabatan).text(jabatan));
                    });
                }
                $jabatanSelect.append('<option value="_LAINNYA_" class="text-primary fw-bold">+ Tambah Baru</option>');
            });

            $('#jabatan').on('change', function() {
                if (this.value === '_LAINNYA_') {
                    $(this).val(''); // Reset
                    Swal.fire({
                        title: 'Jabatan Baru',
                        input: 'text',
                        inputPlaceholder: 'Ketik nama jabatan...',
                        showCancelButton: true,
                        confirmButtonText: 'Simpan',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed && result.value.trim() !== '') {
                            let newJab = result.value.trim().toUpperCase();
                            if ($('#jabatan option[value="'+newJab+'"]').length === 0) {
                                $('<option>').val(newJab).text(newJab).insertBefore($('#jabatan option[value="_LAINNYA_"]'));
                            }
                            $('#jabatan').val(newJab).trigger('change');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>

