<?php
require_once 'config1.php';

// Auto-migrate / create rate_gaji_harian table if it does not exist
try {
    $table_check = $conn->query("SHOW TABLES LIKE 'rate_gaji_harian'");
    if ($table_check->num_rows == 0) {
        $conn->query("CREATE TABLE `rate_gaji_harian` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `dept` varchar(50) NOT NULL,
          `jabatan` varchar(50) NOT NULL,
          `gaji_harian` decimal(15,2) DEFAULT 0.00,
          `upah_lembur_jam` decimal(15,2) DEFAULT 0.00,
          UNIQUE KEY `idx_dept_jabatan` (`dept`, `jabatan`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Insert default dummy rates
        $conn->query("INSERT IGNORE INTO `rate_gaji_harian` (`dept`, `jabatan`, `gaji_harian`, `upah_lembur_jam`) VALUES
            ('BOX', 'OPERATOR', 130000.00, 15000.00),
            ('BOX', 'STAFF', 140000.00, 16000.00),
            ('BOX', 'HARIAN', 120000.00, 15000.00),
            ('CANDLE', 'OPERATOR', 125000.00, 15000.00),
            ('CANDLE', 'STAFF', 135000.00, 16000.00)
        ");
    }
} catch (Exception $e) {
    error_log("Error in auto-migration for rate_gaji_harian: " . $e->getMessage());
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

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
        $id = (int)($_POST['id'] ?? 0);
        $dept = strtoupper(trim($_POST['dept'] ?? ''));
        $jabatan = strtoupper(trim($_POST['jabatan'] ?? ''));
        $gaji_harian = (float)($_POST['gaji_harian'] ?? 0.00);
        $upah_lembur_jam = (float)($_POST['upah_lembur_jam'] ?? 0.00);

        if ($dept === '' || $jabatan === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Departemen dan Jabatan wajib diisi.']);
            exit();
        }

        // Cek duplikasi untuk mode 'add' atau 'edit' jika berubah
        $cek = $conn->prepare($action === 'add'
            ? "SELECT id FROM rate_gaji_harian WHERE dept = ? AND jabatan = ?"
            : "SELECT id FROM rate_gaji_harian WHERE dept = ? AND jabatan = ? AND id <> ?"
        );
        if ($action === 'add') {
            $cek->bind_param("ss", $dept, $jabatan);
        } else {
            $cek->bind_param("ssi", $dept, $jabatan, $id);
        }
        $cek->execute();
        $cek->store_result();
        if ($cek->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Kombinasi Departemen dan Jabatan ini sudah ada.']);
            $cek->close();
            exit();
        }
        $cek->close();

        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO rate_gaji_harian (dept, jabatan, gaji_harian, upah_lembur_jam) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssdd", $dept, $jabatan, $gaji_harian, $upah_lembur_jam);
        } else {
            $stmt = $conn->prepare("UPDATE rate_gaji_harian SET dept=?, jabatan=?, gaji_harian=?, upah_lembur_jam=? WHERE id=?");
            $stmt->bind_param("ssddi", $dept, $jabatan, $gaji_harian, $upah_lembur_jam, $id);
        }

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => $action === 'add' ? 'Rate gaji berhasil ditambahkan.' : 'Rate gaji berhasil diperbarui.',
                'data' => [
                    'id' => $action === 'add' ? $conn->insert_id : $id,
                    'dept' => $dept,
                    'jabatan' => $jabatan,
                    'gaji_harian' => $gaji_harian,
                    'upah_lembur_jam' => $upah_lembur_jam
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan rate gaji harian.']);
        }
        $stmt->close();
        exit();
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM rate_gaji_harian WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Rate gaji berhasil dihapus.']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Gagal menghapus rate gaji.']);
        }
        $stmt->close();
        exit();
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Aksi tidak dikenal.']);
    exit();
}

// Fetch all rates
$result = $conn->query("SELECT * FROM rate_gaji_harian ORDER BY dept ASC, jabatan ASC");
$rates = [];
while ($row = $result->fetch_assoc()) {
    $rates[] = $row;
}
$total_rates = count($rates);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standar Gaji Harian - BFB</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2073a9;
            --primary-dark: #154e75;
            --primary-soft: #e8f1f7;
            --accent: #b7791f;
            --accent-soft: #fff4dd;
            --danger: #d84c4c;
            --ink: #1f2d37;
            --muted: #687782;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --surface: #fff;
            --radius: 8px;
            --shadow: 0 16px 42px rgba(32, 115, 169, .08);
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: Inter, Arial, sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, rgba(32, 115, 169, .08), transparent 330px), var(--bg);
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
            box-shadow: 0 8px 24px rgba(32, 115, 169, .04);
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
        .form-control:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(32, 115, 169, .15); }
        .btn { border-radius: var(--radius); font-weight: 700; }
        .btn-success { background: var(--primary); border-color: var(--primary); color: white; }
        .btn-success:hover { background: var(--primary-dark); border-color: var(--primary-dark); color: white; }
        .btn-icon { width: 34px; height: 34px; padding: 0; display: inline-flex; align-items: center; justify-content: center; }

        .table thead th {
            padding: 13px 12px; border: 0; background: var(--primary-dark) !important;
            color: #fff !important; font-size: .78rem; font-weight: 800; text-transform: uppercase; white-space: nowrap;
        }
        .table tbody td { padding: 14px 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: .9rem; }
        .table-hover tbody tr:hover td { background: #f8fafc; }
        .dept-name { color: var(--ink); font-weight: 800; }
        .pill {
            display: inline-flex; align-items: center; gap: 6px; border-radius: 999px;
            padding: 5px 9px; font-weight: 800; font-size: .78rem; white-space: nowrap;
        }
        .pill-code { color: var(--primary-dark); background: var(--primary-soft); }
        .table-success-subtle td { background: #e8f1f7 !important; transition: background-color 1.8s ease; }
        .table-info-subtle td { background: #e8f1f7 !important; transition: background-color 1.8s ease; }

        @media (max-width: 768px) {
            .page-shell { width: min(100% - 22px,1540px); padding-top: 18px; }
            .page-header, .stat-grid { grid-template-columns: 1fr; }
            .panel-head, .form-body, .table-wrap { padding-left: 14px; padding-right: 14px; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark app-nav">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="home.php">
                <span class="brand-mark"><i class="fa-solid fa-cart-shopping"></i></span>
                <span>BFB</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="absen.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-calendar-check me-1"></i> Absensi
                </a>
                <a href="karyawan.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-users me-1"></i> Karyawan
                </a>
                <a href="gaji_harian.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-money-bill-wave me-1"></i> Gaji Harian
                </a>
                <a href="mutasi_harian.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-arrows-spin me-1"></i> Mutasi Harian
                </a>
                <a href="riwayat_gaji.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Riwayat
                </a>
                <a href="home.php" class="btn btn-outline-light btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <main class="page-shell">
        <header class="page-header">
            <div>
                <div class="eyebrow">Pengaturan Kehadiran</div>
                <h1 class="page-title">Standar Gaji Harian</h1>
                <p class="page-subtitle">Kelola standardisasi upah harian dan lembur per jam berdasarkan kombinasi Departemen & Jabatan untuk karyawan harian/lepas.</p>
            </div>
            <button type="button" class="btn btn-success px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#rateModal">
                <i class="fa-solid fa-plus me-2"></i> Tambah Standar Rate Baru
            </button>
        </header>

        <section class="stat-grid">
            <div class="stat-card"><span>Total Standar Rate</span><strong id="statTotal"><?= e($total_rates) ?></strong></div>
            <div class="stat-card"><span>Tipe Kerja</span><strong style="color: #2073a9;">Harian & Lepas</strong></div>
        </section>

        <div class="row">
            <div class="col-12">
                <section class="panel">
                    <div class="panel-head">
                        <div class="title-wrap">
                            <span class="title-icon"><i class="fa-solid fa-money-bill-wave"></i></span>
                            <div>
                                <h2 class="panel-title">Standardisasi Tarif Upah</h2>
                                <span class="panel-subtitle">Daftar rate gaji dasar dan lembur per jam per departemen.</span>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <div class="table-responsive">
                            <table id="ratesTable" class="table table-hover align-middle" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Departemen</th>
                                        <th>Jabatan</th>
                                        <th>Rate Gaji Harian (Rp)</th>
                                        <th>Upah Lembur / Jam (Rp)</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rates as $row): ?>
                                        <tr>
                                            <td><span class="pill pill-code"><i class="fa-solid fa-hashtag"></i><?= e($row['id']) ?></span></td>
                                            <td><div class="dept-name"><?= e($row['dept']) ?></div></td>
                                            <td><?= e($row['jabatan']) ?></td>
                                            <td>Rp <?= number_format((float)$row['gaji_harian'], 2, ',', '.') ?></td>
                                            <td>Rp <?= number_format((float)$row['upah_lembur_jam'], 2, ',', '.') ?></td>
                                            <td>
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary btn-icon btn-edit"
                                                        title="Edit rate"
                                                        data-id="<?= e($row['id']) ?>"
                                                        data-dept="<?= e($row['dept']) ?>"
                                                        data-jabatan="<?= e($row['jabatan']) ?>"
                                                        data-gaji_harian="<?= e($row['gaji_harian']) ?>"
                                                        data-upah_lembur_jam="<?= e($row['upah_lembur_jam']) ?>">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-delete"
                                                        title="Hapus rate"
                                                        data-id="<?= e($row['id']) ?>"
                                                        data-dept="<?= e($row['dept']) ?>"
                                                        data-jabatan="<?= e($row['jabatan']) ?>">
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

    <!-- Modal Form (Tambah / Edit) -->
    <div class="modal fade" id="rateModal" tabindex="-1" aria-labelledby="rateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content panel shadow-lg">
                <div class="panel-head">
                    <div class="title-wrap">
                        <span class="title-icon"><i class="fa-solid fa-file-invoice-dollar" id="formIcon"></i></span>
                        <div>
                            <h3 class="panel-title" id="formTitle">Tambah Standar Rate</h3>
                            <span class="panel-subtitle" id="formSubtitle">Buat standardisasi upah departemen & jabatan baru</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="rateForm" autocomplete="off">
                    <div class="modal-body form-body">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="action" id="action" value="add">
                        <input type="hidden" name="id" id="rate_id" value="0">

                        <div class="section-label">Identitas Tugas</div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dept" class="form-label">Nama Departemen</label>
                                <input type="text" class="form-control" id="dept" name="dept" required placeholder="Contoh: BOX">
                            </div>
                            <div class="col-md-6">
                                <label for="jabatan" class="form-label">Jabatan / Peran</label>
                                <input type="text" class="form-control" id="jabatan" name="jabatan" required placeholder="Contoh: OPERATOR">
                            </div>
                        </div>

                        <div class="section-label">Rincian Nominal Gaji</div>

                        <div class="row mb-2">
                            <div class="col-md-6">
                                <label for="gaji_harian" class="form-label">Upah Pokok Harian (Rp)</label>
                                <input type="number" step="any" min="0" class="form-control" id="gaji_harian" name="gaji_harian" placeholder="Nilai Gaji" required>
                            </div>
                            <div class="col-md-6">
                                <label for="upah_lembur_jam" class="form-label">Upah Lembur / Jam (Rp)</label>
                                <input type="number" step="any" min="0" class="form-control" id="upah_lembur_jam" name="upah_lembur_jam" placeholder="Upah Lembur" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end gap-2" style="border-top:1px solid var(--line);">
                        <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success px-4" id="btnSubmit">
                            <i class="fa-solid fa-floppy-disk me-2"></i> Simpan Rate Gaji
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
            let table = $('#ratesTable').DataTable({
                pageLength: 10,
                columnDefs: [
                    { orderable: false, targets: [5] },
                    { className: 'text-center', targets: [0, 5] }
                ],
                order: [[1, 'asc'], [2, 'asc']],
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
                $('#rateForm')[0].reset();
                $('#action').val('add');
                $('#rate_id').val('0');
                $('#formTitle').text('Tambah Standar Rate');
                $('#formSubtitle').text('Buat standardisasi upah departemen & jabatan baru');
                $('#btnSubmit').html('<i class="fa-solid fa-floppy-disk me-2"></i> Simpan Rate Gaji');
                $('#formIcon').removeClass('fa-pen-to-square').addClass('fa-file-invoice-dollar');
            }

            document.getElementById('rateModal').addEventListener('hidden.bs.modal', function () {
                resetForm();
            });

            let currentRow = null;

            $('#rateForm').on('submit', function(e) {
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
                        const formattedGaji = 'Rp ' + parseFloat(item.gaji_harian).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        const formattedLembur = 'Rp ' + parseFloat(item.upah_lembur_jam).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        
                        const idCol = `<span class="pill pill-code"><i class="fa-solid fa-hashtag"></i>${item.id}</span>`;
                        const deptCol = `<div class="dept-name">${item.dept}</div>`;
                        
                        const actionCol = `
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-icon btn-edit"
                                    title="Edit rate"
                                    data-id="${item.id}"
                                    data-dept="${item.dept}"
                                    data-jabatan="${item.jabatan}"
                                    data-gaji_harian="${item.gaji_harian}"
                                    data-upah_lembur_jam="${item.upah_lembur_jam}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon btn-delete"
                                    title="Hapus rate"
                                    data-id="${item.id}"
                                    data-dept="${item.dept}"
                                    data-jabatan="${item.jabatan}">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        `;

                        const rowData = [
                            idCol,
                            deptCol,
                            item.jabatan,
                            formattedGaji,
                            formattedLembur,
                            actionCol
                        ];

                        if (action === 'add') {
                            const addedRow = table.row.add(rowData).draw(false);
                            $(addedRow.node()).addClass('table-success-subtle');
                            setTimeout(function() { $(addedRow.node()).removeClass('table-success-subtle'); }, 2000);
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Standar upah berhasil ditambahkan.', confirmButtonColor: '#2073a9' });
                            
                            let total = parseInt($('#statTotal').text()) + 1;
                            $('#statTotal').text(total);
                        } else {
                            table.row(currentRow).data(rowData).draw(false);
                            $(currentRow).addClass('table-info-subtle');
                            setTimeout(function() { $(currentRow).removeClass('table-info-subtle'); }, 2000);
                            Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Standar upah berhasil diperbarui.', confirmButtonColor: '#2073a9' });
                        }

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('rateModal')).hide();
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

            $('#ratesTable').on('click', '.btn-edit', function() {
                currentRow = $(this).closest('tr');
                $('#rate_id').val($(this).data('id'));
                $('#dept').val($(this).data('dept'));
                $('#jabatan').val($(this).data('jabatan'));
                $('#gaji_harian').val($(this).data('gaji_harian'));
                $('#upah_lembur_jam').val($(this).data('upah_lembur_jam'));
                
                $('#action').val('edit');
                $('#formTitle').text('Edit Standar Rate');
                $('#formSubtitle').text('Perbarui standardisasi tarif untuk: ' + $(this).data('dept') + ' / ' + $(this).data('jabatan'));
                $('#btnSubmit').html('<i class="fa-solid fa-floppy-disk me-2"></i> Perbarui Rate Gaji');
                $('#formIcon').removeClass('fa-file-invoice-dollar').addClass('fa-pen-to-square');
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('rateModal')).show();
            });

            $('#ratesTable').on('click', '.btn-delete', function() {
                const row = $(this).closest('tr');
                const id = $(this).data('id');
                const dept = $(this).data('dept');
                const jabatan = $(this).data('jabatan');

                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: `Anda akan menghapus standar upah untuk "${dept} / ${jabatan}".`,
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
                            id: id
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                table.row(row).remove().draw(false);
                                
                                const total = parseInt($('#statTotal').text()) - 1;
                                $('#statTotal').text(total);

                                Swal.fire({ icon: 'success', title: 'Dihapus!', text: 'Standar rate berhasil dihapus.', confirmButtonColor: '#2073a9' });
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
        });
    </script>
</body>
</html>
