<?php
session_start();
require_once 'config1.php';


// Auto migration
$conn->query("CREATE TABLE IF NOT EXISTS `gaji_harian` (
    `id` int NOT NULL AUTO_INCREMENT,
    `no_staff` int NOT NULL,
    `tanggal` date NOT NULL,
    `jabatan_aktual` varchar(100) NOT NULL,
    `dept_aktual` varchar(100) DEFAULT NULL,
    `status_hadir` enum('Hadir','Ijin','Alpa','Sakit') DEFAULT 'Hadir',
    `nominal_gaji` decimal(15,2) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_staff_tgl` (`no_staff`, `tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");

// Ensure dept_aktual exists for older table versions
$check_col = $conn->query("SHOW COLUMNS FROM `gaji_harian` LIKE 'dept_aktual'");
if ($check_col && $check_col->num_rows == 0) {
    $conn->query("ALTER TABLE `gaji_harian` ADD COLUMN `dept_aktual` varchar(100) DEFAULT NULL AFTER `jabatan_aktual`");
}

$tanggal = isset($_GET['tanggal']) ? $_GET['tanggal'] : date('Y-m-d');

// Fetch map of depts to jabatans from rate_gaji_harian
$dept_jabatan_map = [];
$depts = [];
$r_map = $conn->query("SELECT DISTINCT dept, jabatan FROM rate_gaji_harian ORDER BY dept ASC, jabatan ASC");
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
    }
}
$dept_jabatan_json = json_encode($dept_jabatan_map);

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_rekap') {
    $tgl_save = $_POST['tanggal_save'];
    $staff_data = $_POST['staff'] ?? [];
    
    $stmt = $conn->prepare("INSERT INTO gaji_harian (no_staff, tanggal, jabatan_aktual, dept_aktual, status_hadir) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE jabatan_aktual = VALUES(jabatan_aktual), dept_aktual = VALUES(dept_aktual), status_hadir = VALUES(status_hadir)");
                            
    foreach ($staff_data as $no_staff => $data) {
        $jabatan = $data['jabatan'];
        $dept = $data['dept'];
        $hadir = $data['hadir'];
        
        $stmt->bind_param("issss", $no_staff, $tgl_save, $jabatan, $dept, $hadir);
        $stmt->execute();
    }
    
    echo "<script>alert('Rekap absen harian berhasil disimpan!'); window.location='mutasi_harian.php?tanggal=$tgl_save';</script>";
    exit;
}

// Fetch employees (jenis_gaji = mingguan) + their daily record
$sql = "SELECT k.no_staff, k.nama, k.jabatan as jabatan_master, k.dept as dept_master,
               g.jabatan_aktual, g.dept_aktual, g.status_hadir 
        FROM data_karyawan k 
        LEFT JOIN gaji_harian g ON k.no_staff = g.no_staff AND g.tanggal = '$tanggal'
        WHERE k.jenis_gaji = 'mingguan' AND k.aktive = 'aktive'
        ORDER BY k.nama ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mutasi Karyawan - BFB</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        :root {
            --primary: #2073a9;
            --primary-dark: #154e75;
            --primary-soft: #e8f1f7;
            --ink: #1f2937;
            --bg: #f3f4f6;
            --muted: #6b7280;
            --line: #e5e7eb;
            --surface: #fff;
            --radius: 8px;
            --shadow: 0 16px 42px rgba(32, 115, 169, .08);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, rgba(32, 115, 169, .08), transparent 330px), var(--bg);
            color: var(--ink);
            min-height: 100vh;
        }

        .app-nav { background: var(--primary-dark); }
        .navbar-brand { font-weight: 800; }
        .brand-mark {
            width: 38px; height: 38px; border-radius: var(--radius);
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(255,255,255,.14); color: #fff;
        }

        .page-header { margin: 30px 0; }
        .eyebrow { color: var(--primary); font-size: .8rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .page-title { margin: 6px 0 0; color: var(--primary-dark); font-size: clamp(1.7rem,3vw,2.35rem); font-weight: 800; }
        .page-subtitle { color: var(--muted); margin-top: 5px; }

        .panel { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--line); overflow: hidden; margin-bottom: 30px;}
        .panel-head { background: linear-gradient(135deg, var(--primary-soft), #fff); padding: 15px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; }
        
        .floating-action-bar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 20px;
            border-bottom: 1px solid var(--line);
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-box {
            position: relative;
            max-width: 300px;
        }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .search-box input { padding-left: 40px; border-radius: 50px; border: 1px solid var(--line); }
        .search-box input:focus { box-shadow: 0 0 0 0.25rem rgba(44, 132, 105, 0.25); border-color: var(--primary); }

        .sticky-thead th {
            background: var(--primary-dark) !important;
            color: white !important;
        }

        .emp-name { font-weight: 700; color: var(--ink); }
        .emp-role { font-size: 0.8rem; color: var(--muted); }
        
        select.form-select { border-radius: 6px; border: 1px solid var(--line); font-size: 0.9rem; }
        select.form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 0.2rem rgba(44, 132, 105, 0.25); }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark app-nav">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="home.php">
                <span class="brand-mark"><i class="fa-solid fa-cart-shopping"></i></span>
                <span>BFB</span>
            </a>
            <div class="d-flex align-items-center gap-2 flex-wrap">
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

    <div class="container">
        <header class="page-header">
            <div class="eyebrow">Manajemen Kehadiran</div>
            <h1 class="page-title">Mutasi Karyawan</h1>
            <p class="page-subtitle">Pilih tanggal dan sesuaikan posisi aktual serta kehadiran karyawan secara masal dengan cepat.</p>
        </header>

        <div class="panel">
            <div class="panel-head">
                <div class="d-flex align-items-center gap-3">
                    <i class="fa-solid fa-calendar-day fs-3" style="color: var(--primary)"></i>
                    <div>
                        <h5 class="mb-0 fw-bold text-dark">Pilih Tanggal Mutasi</h5>
                        <small class="text-muted">Pilih tanggal spesifik untuk direkap.</small>
                    </div>
                </div>
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="tanggal" class="form-control" value="<?= htmlspecialchars($tanggal) ?>" required>
                    <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-search me-1"></i> Buka</button>
                </form>
            </div>

            <form method="POST" id="batchForm">
                <input type="hidden" name="action" value="save_rekap">
                <input type="hidden" name="tanggal_save" value="<?= htmlspecialchars($tanggal) ?>">
                
                <?php if($result && $result->num_rows > 0): ?>
                <!-- Floating Action Bar -->
                <div class="floating-action-bar">
                    <div class="search-box flex-grow-1 me-4">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" class="form-control form-control-lg" id="liveSearch" placeholder="Cari nama karyawan...">
                    </div>
                    <button type="button" class="btn btn-success btn-lg px-5 shadow-sm fw-bold" onclick="submitForm()">
                        <i class="fa-solid fa-save me-2"></i> SIMPAN SEMUA (<span id="countVisible"><?= $result->num_rows ?></span>)
                    </button>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="empTable">
                        <thead class="sticky-thead">
                            <tr>
                                <th width="5%" class="text-center py-3">No</th>
                                <th width="25%" class="py-3">Informasi Karyawan</th>
                                <th width="25%" class="py-3">Dept Aktual (Hari Ini)</th>
                                <th width="25%" class="py-3">Jabatan Aktual (Hari Ini)</th>
                                <th width="20%" class="py-3">Status Hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result && $result->num_rows > 0): ?>
                                <?php $no = 1; while($row = $result->fetch_assoc()): ?>
                                    <?php 
                                        $current_jabatan = !empty($row['jabatan_aktual']) ? $row['jabatan_aktual'] : $row['jabatan_master'];
                                        $current_dept = !empty($row['dept_aktual']) ? $row['dept_aktual'] : $row['dept_master'];
                                        $current_hadir = !empty($row['status_hadir']) ? $row['status_hadir'] : 'Hadir';
                                        
                                        $is_mutasi = ($current_dept !== $row['dept_master'] || $current_jabatan !== $row['jabatan_master']);
                                    ?>
                                    <tr class="emp-row">
                                        <td class="text-center text-muted fw-bold"><?= $no++ ?></td>
                                        <td>
                                            <div class="emp-name search-target"><?= htmlspecialchars($row['nama']) ?></div>
                                            <div class="emp-role">
                                                <i class="fa-solid fa-briefcase text-muted me-1"></i>
                                                Master: <?= htmlspecialchars($row['dept_master']) ?> / <?= htmlspecialchars($row['jabatan_master']) ?>
                                                <?php if($is_mutasi): ?>
                                                    <span class="badge bg-warning text-dark ms-1" style="font-size:0.6rem;"><i class="fa-solid fa-arrows-spin"></i> Mutasi</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="staff[<?= $row['no_staff'] ?>][dept]" class="form-select dept-select" data-target="jabatan-<?= $row['no_staff'] ?>">
                                                <?php foreach($depts as $dept): ?>
                                                    <option value="<?= htmlspecialchars($dept) ?>" <?= ($current_dept == $dept) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($dept) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if(!in_array($current_dept, $depts)): ?>
                                                    <option value="<?= htmlspecialchars($current_dept) ?>" selected>
                                                        <?= htmlspecialchars($current_dept) ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="staff[<?= $row['no_staff'] ?>][jabatan]" id="jabatan-<?= $row['no_staff'] ?>" class="form-select jabatan-select" data-selected="<?= htmlspecialchars($current_jabatan) ?>">
                                                <?php 
                                                    $available_roles = isset($dept_jabatan_map[$current_dept]) ? $dept_jabatan_map[$current_dept] : [$current_jabatan];
                                                ?>
                                                <?php foreach($available_roles as $role): ?>
                                                    <option value="<?= htmlspecialchars($role) ?>" <?= ($current_jabatan == $role) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($role) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                                <?php if(!in_array($current_jabatan, $available_roles)): ?>
                                                    <option value="<?= htmlspecialchars($current_jabatan) ?>" selected>
                                                        <?= htmlspecialchars($current_jabatan) ?>
                                                    </option>
                                                <?php endif; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="staff[<?= $row['no_staff'] ?>][hadir]" class="form-select <?= $current_hadir != 'Hadir' ? 'border-danger text-danger fw-bold bg-danger bg-opacity-10' : '' ?>" onchange="if(this.value!='Hadir'){this.classList.add('border-danger','text-danger','fw-bold','bg-danger','bg-opacity-10');}else{this.classList.remove('border-danger','text-danger','fw-bold','bg-danger','bg-opacity-10');}">
                                                <option value="Hadir" <?= ($current_hadir == 'Hadir') ? 'selected' : '' ?>>✅ Hadir</option>
                                                <option value="Ijin" <?= ($current_hadir == 'Ijin') ? 'selected' : '' ?>>⚠️ Ijin</option>
                                                <option value="Sakit" <?= ($current_hadir == 'Sakit') ? 'selected' : '' ?>>🏥 Sakit</option>
                                                <option value="Alpa" <?= ($current_hadir == 'Alpa') ? 'selected' : '' ?>>❌ Alpa</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-folder-open fs-1 mb-3" style="color: var(--line)"></i>
                                        <br>Tidak ada data karyawan mingguan/harian.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
    <script src="assets/sweetalert2.all.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        var empTable = $('#empTable').DataTable({
            paging: false,
            lengthChange: false,
            ordering: false,
            info: true,
            language: {
                search: '',
                searchPlaceholder: 'Cari data...',
                info: 'Menampilkan total _TOTAL_ karyawan',
                infoEmpty: 'Tidak ada data',
                infoFiltered: '(difilter dari _MAX_)'
            }
        });
        
        // Hide default DataTables search box
        $('.dataTables_filter').hide();

        // Live Search Functionality Linked to DataTables
        document.getElementById('liveSearch')?.addEventListener('keyup', function() {
            empTable.search(this.value).draw();
        });

        // Sync Dropdowns (Cascading)
        const deptJabatanMap = <?= $dept_jabatan_json ?>;
        
        $('#empTable').on('change', '.dept-select', function() {
            const targetId = this.getAttribute('data-target');
            const targetSelect = document.getElementById(targetId);
            const selectedDept = this.value;
            const previouslySelectedJabatan = targetSelect.getAttribute('data-selected');
            
            targetSelect.innerHTML = '';
            
            if (deptJabatanMap[selectedDept] && deptJabatanMap[selectedDept].length > 0) {
                deptJabatanMap[selectedDept].forEach(jabatan => {
                    const opt = document.createElement('option');
                    opt.value = jabatan;
                    opt.textContent = jabatan;
                    if (jabatan === previouslySelectedJabatan) {
                        opt.selected = true;
                    }
                    targetSelect.appendChild(opt);
                });
            } else {
                // Fallback if empty
                const opt = document.createElement('option');
                opt.value = previouslySelectedJabatan || '-';
                opt.textContent = previouslySelectedJabatan || '-';
                opt.selected = true;
                targetSelect.appendChild(opt);
            }
        });

        // Submit with SweetAlert confirmation
        function submitForm() {
            Swal.fire({
                title: 'Simpan Rekap?',
                text: "Data absen dan mutasi untuk semua karyawan di tanggal ini akan disimpan.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#2073a9',
                cancelButtonColor: '#d33',
                confirmButtonText: '<i class="fa-solid fa-check me-1"></i> Ya, Simpan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menyimpan...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                    
                    var form = document.getElementById('batchForm');
                    // Append inputs from all pages in DataTable that are NOT currently in the DOM
                    empTable.$('input, select').each(function(){
                        if(!$.contains(document, this)){
                            if(this.name){
                                $(form).append(
                                    $('<input>').attr('type', 'hidden').attr('name', this.name).val(this.value)
                                );
                            }
                        }
                    });
                    
                    form.submit();
                }
            })
        }
    </script>
</body>
</html>
