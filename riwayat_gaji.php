<?php
require_once 'config1.php';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$view_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($view_id > 0) {
    // View detail
    $q_per = $conn->prepare("SELECT * FROM riwayat_gaji_periode WHERE id = ?");
    $q_per->bind_param("i", $view_id);
    $q_per->execute();
    $periode = $q_per->get_result()->fetch_assoc();

    $q_det = $conn->prepare("SELECT * FROM riwayat_gaji_detail WHERE periode_id = ? ORDER BY nama ASC");
    $q_det->bind_param("i", $view_id);
    $q_det->execute();
    $details = $q_det->get_result();
} else {
    // View periods list
    $periods = $conn->query("SELECT * FROM riwayat_gaji_periode ORDER BY tgl_awal DESC, id DESC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Gaji - MKB</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="assets/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2073a9;
            --primary-dark: #154e75;
            --primary-soft: #e8f1f7;
            --ink: #1f2d37;
            --muted: #687782;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --surface: #fff;
            --radius: 8px;
            --shadow: 0 16px 42px rgba(32, 115, 169, .08);
        }
        body { min-height: 100vh; font-family: Inter, Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .app-nav { background: var(--primary-dark); }
        .navbar-brand { font-weight: 800; letter-spacing: .02em; }
        .brand-mark { width: 38px; height: 38px; border-radius: var(--radius); display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,.14); color: #fff; }
        .page-shell { width: min(1300px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0; }
        .page-header { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 16px; align-items: end; margin-bottom: 22px; }
        .eyebrow { color: var(--primary); font-size: .78rem; font-weight: 800; text-transform: uppercase; }
        .page-title { margin: 6px 0 0; color: var(--primary-dark); font-size: 2rem; font-weight: 800; }
        .panel { border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 24px; }
        .panel-head { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--line); background: var(--primary-soft); }
        .panel-title { margin: 0; color: var(--primary-dark); font-size: 1.06rem; font-weight: 800; }
        .table-wrap { padding: 20px; }
        .table th { background: var(--primary-dark) !important; color: #fff !important; font-size: .8rem; font-weight: 800; text-transform: uppercase; }
        .table td { vertical-align: middle; font-size: .9rem; }
        .pill-code { display: inline-flex; padding: 5px 9px; background: var(--primary-soft); color: var(--primary-dark); border-radius: 999px; font-weight: 800; font-size: .78rem; }
        .slip-gaji { border: 2px dashed var(--primary); border-radius: 12px; padding: 24px; background: #fff; }
        
        @page { size: A4 portrait; margin: 1cm; }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; }
            .slip-gaji { 
                page-break-inside: avoid !important; 
                break-inside: avoid !important; 
                display: block !important;
                width: 100%;
                border: 2px solid #000 !important; 
            }
            .modal { position: absolute; left: 0; top: 0; margin: 0; padding: 0; overflow: visible!important; background: transparent !important; display: block !important; }
            .modal-dialog { max-width: 100%; width: 100%; transform: none!important; margin: 0 !important; display: block !important; align-items: flex-start !important; min-height: auto !important; }
            .modal-content { border: none; box-shadow: none; display: block !important; }
            .hide-on-print { display: none !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark app-nav hide-on-print">
        <div class="container-fluid px-4">
            <a class="navbar-brand d-flex align-items-center gap-2" href="home.php">
                <span class="brand-mark"><i class="fa-solid fa-cart-shopping"></i></span>
                <span>MKB</span>
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

    <main class="page-shell">
        <?php if ($view_id == 0): ?>
            <!-- List of Periods -->
            <header class="page-header">
                <div>
                    <div class="eyebrow">Arsip Keuangan</div>
                    <h1 class="page-title">Riwayat Penggajian (Tutup Buku)</h1>
                </div>
            </header>

            <section class="panel">
                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle dataTable">
                            <thead>
                                <tr>
                                    <th>ID Periode</th>
                                    <th>Tanggal Awal</th>
                                    <th>Tanggal Akhir</th>
                                    <th>Total Karyawan</th>
                                    <th>Total Tagihan Gaji</th>
                                    <th>Tanggal Arsip</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($p = $periods->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="pill-code">#<?= e($p['id']) ?></span></td>
                                    <td class="fw-bold"><?= e(date('d M Y', strtotime($p['tgl_awal']))) ?></td>
                                    <td class="fw-bold"><?= e(date('d M Y', strtotime($p['tgl_akhir']))) ?></td>
                                    <td><span class="badge bg-secondary"><?= e($p['total_karyawan']) ?> Orang</span></td>
                                    <td class="fw-bold text-success">Rp <?= number_format($p['total_thp'], 2, ',', '.') ?></td>
                                    <td class="text-muted small"><?= e(date('d M Y H:i', strtotime($p['created_at']))) ?></td>
                                    <td>
                                        <a href="?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fa-solid fa-folder-open me-1"></i> Buka Arsip
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php else: ?>
            <!-- Detail Period -->
            <header class="page-header hide-on-print">
                <div>
                    <div class="eyebrow">Detail Arsip Penggajian</div>
                    <h1 class="page-title">Periode: <?= e(date('d M Y', strtotime($periode['tgl_awal']))) ?> s/d <?= e(date('d M Y', strtotime($periode['tgl_akhir']))) ?></h1>
                </div>
                <a href="riwayat_gaji.php" class="btn btn-outline-secondary px-4 shadow-sm">
                    <i class="fa-solid fa-arrow-left me-2"></i> Kembali
                </a>
            </header>

            <section class="panel hide-on-print">
                <div class="panel-head">
                    <h2 class="panel-title">Data Penggajian Terkunci</h2>
                    <button type="button" onclick="window.print();" class="btn btn-primary px-3 shadow-sm btn-sm">
                        <i class="fa-solid fa-print me-1"></i> Cetak Rekap
                    </button>
                </div>
                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle dataTable" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>No. Staff</th>
                                    <th>Nama Karyawan</th>
                                    <th>Jenis Gaji</th>
                                    <th>Hadir</th>
                                    <th>Absen</th>
                                    <th>Terlambat (Mnt)</th>
                                    <th>Lembur (Jam)</th>
                                    <th>Gaji Pokok/Hari</th>
                                    <th>Gaji Lembur</th>
                                    <th>Potongan</th>
                                    <th class="text-end">THP (Bersih)</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $details->fetch_assoc()): 
                                    $potongan = (float)$row['potongan_absen'] + (float)$row['potongan_terlambat'];
                                ?>
                                <tr>
                                    <td><span class="pill-code"><i class="fa-solid fa-id-card"></i><?= e($row['no_staff']) ?></span></td>
                                    <td class="fw-bold"><?= e($row['nama']) ?></td>
                                    <td><span class="badge bg-success"><?= e($row['jenis_gaji']) ?></span></td>
                                    <td><?= e($row['total_hadir']) ?></td>
                                    <td><?= e($row['total_absen']) ?></td>
                                    <td><?= e($row['total_terlambat_menit']) ?></td>
                                    <td><?= e($row['total_lembur_jam']) ?></td>
                                    <td>Rp <?= number_format($row['gaji_dasar_periode'], 2, ',', '.') ?></td>
                                    <td>Rp <?= number_format($row['gaji_lembur_periode'], 2, ',', '.') ?></td>
                                    <td class="text-danger">-Rp <?= number_format($potongan, 2, ',', '.') ?></td>
                                    <td class="text-end fw-bold fs-6 text-success">Rp <?= number_format($row['take_home_pay'], 2, ',', '.') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-info btn-slip"
                                            data-no_staff="<?= e($row['no_staff']) ?>"
                                            data-nama="<?= e($row['nama']) ?>"
                                            data-jenis="<?= e($row['jenis_gaji']) ?>"
                                            data-pokok="<?= e($row['gaji_dasar_periode']) ?>"
                                            data-lembur="<?= e($row['gaji_lembur_periode']) ?>"
                                            data-potongan="<?= e($potongan) ?>"
                                            data-thp="<?= e($row['take_home_pay']) ?>"
                                            data-tgl="<?= e(date('d-m-Y', strtotime($periode['tgl_awal']))) . ' s/d ' . e(date('d-m-Y', strtotime($periode['tgl_akhir']))) ?>"
                                        >
                                            <i class="fa-solid fa-receipt me-1"></i>Cetak Slip
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Slip Gaji Modal -->
    <div class="modal fade" id="slipModal" tabindex="-1" aria-labelledby="slipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow-lg">
                <div class="modal-header bg-light border-bottom hide-on-print">
                    <h5 class="modal-title text-dark fw-bold" id="slipModalLabel"><i class="fa-solid fa-receipt text-primary me-2"></i> Slip Gaji Karyawan (Arsip)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="printArea">
                    <div class="slip-gaji mx-auto" style="max-width: 800px;">
                        <div class="text-center mb-4 pb-3 border-bottom">
                            <h2 class="mb-1 fw-bold text-dark" style="letter-spacing:1px;">PT. MKB</h2>
                            <p class="mb-0 text-muted">Slip Gaji Karyawan Terkunci</p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-6">
                                <table class="table table-borderless table-sm mb-0">
                                    <tr><td class="text-muted" width="100">Nama</td><td width="10">:</td><td class="fw-bold fs-5 text-dark" id="slipNama"></td></tr>
                                    <tr><td class="text-muted">ID Staff</td><td>:</td><td class="fw-bold" id="slipId"></td></tr>
                                </table>
                            </div>
                            <div class="col-6">
                                <table class="table table-borderless table-sm mb-0 text-end">
                                    <tr><td class="text-muted">Periode</td><td>:</td><td class="fw-bold text-dark" id="slipPeriode"></td></tr>
                                    <tr><td class="text-muted">Sistem</td><td>:</td><td class="fw-bold text-success text-uppercase" id="slipJenis"></td></tr>
                                </table>
                            </div>
                        </div>

                        <div class="p-3 mb-4 rounded" style="background:#f8fafc; border: 1px solid var(--line);">
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td class="fw-bold text-muted">Gaji / Upah Dasar</td>
                                    <td class="text-end fw-bold">Rp <span id="slipPokok"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold text-muted">Total Lembur</td>
                                    <td class="text-end fw-bold text-success">+ Rp <span id="slipLembur"></span></td>
                                </tr>
                                <tr style="border-bottom: 2px dashed #cbd5e1;">
                                    <td class="fw-bold text-muted pb-3">Potongan (Absen/Telat)</td>
                                    <td class="text-end fw-bold text-danger pb-3">- Rp <span id="slipPotongan"></span></td>
                                </tr>
                                <tr>
                                    <td class="fw-bold fs-5 pt-3 text-dark">TAKE HOME PAY</td>
                                    <td class="text-end fw-bold fs-4 pt-3" style="color:var(--primary-dark);">Rp <span id="slipThp"></span></td>
                                </tr>
                            </table>
                        </div>

                        <div class="row mt-5 pt-3">
                            <div class="col-6 text-center">
                                <p class="mb-5 text-muted fw-bold">Penerima,</p>
                                <p class="fw-bold text-dark mb-0" style="text-decoration: underline;" id="slipTtdNama"></p>
                            </div>
                            <div class="col-6 text-center">
                                <p class="mb-5 text-muted fw-bold">Mengetahui (HRD),</p>
                                <p class="fw-bold text-dark mb-0" style="text-decoration: underline;">HRD Manager</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top hide-on-print">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary px-4" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Cetak Dokumen</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/jquery.min.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
    <script src="assets/jquery.dataTables.min.js"></script>
    <script src="assets/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.dataTable').DataTable({
                pageLength: 25,
                language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_ data', info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data', paginate: { first: 'Awal', last: 'Akhir', next: 'Maju', previous: 'Mundur' } }
            });

            $('.dataTable').on('click', '.btn-slip', function() {
                $('#slipNama').text($(this).data('nama'));
                $('#slipTtdNama').text($(this).data('nama'));
                $('#slipId').text($(this).data('no_staff'));
                $('#slipJenis').text($(this).data('jenis'));
                $('#slipPeriode').text($(this).data('tgl'));
                
                $('#slipPokok').text(parseFloat($(this).data('pokok')).toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                $('#slipLembur').text(parseFloat($(this).data('lembur')).toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                $('#slipPotongan').text(parseFloat($(this).data('potongan')).toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                $('#slipThp').text(parseFloat($(this).data('thp')).toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('slipModal')).show();
            });
        });
    </script>
</body>
</html>
