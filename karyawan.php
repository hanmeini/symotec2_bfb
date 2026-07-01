<?php


require_once 'functions.php'; // harus ada: fungsi db_connect(), e(), log_activity()
$conn = db_connect();

// Handle toggle via AJAX (POST)
if (isset($_POST['toggle_aktive']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $current = $_POST['current'];
    $new = ($current === 'aktive') ? 'nonaktive' : 'aktive';

    $stmt = $conn->prepare("UPDATE data_karyawan SET aktive = ? WHERE no_staff = ?");
    $stmt->bind_param("si", $new, $id);
    $stmt->execute();
    $stmt->close();

    log_activity('toggle', ($_SESSION['user'] ?? 'system'), "Toggle karyawan $id -> $new", $conn);
    echo $new;
    exit;
}

/**
 * Build WHERE clause and parameters (for prepared statements)
 */
$search = trim($_GET['q'] ?? '');
$filter_dep = $_GET['dep'] ?? '';
$filter_jab = $_GET['jab'] ?? '';
$filter_aktive = $_GET['aktive'] ?? '';

$where_clauses = [];
$params = [];
$types = '';

if ($search !== '') {
    $where_clauses[] = "(dk.nama LIKE ? OR dk.nik LIKE ?)";
    $like = "%{$search}%";
    $params[] = &$like; $params[] = &$like;
    $types .= "ss";
}
if ($filter_dep !== '') {
    $where_clauses[] = "dk.dept = ?";
    $params[] = &$filter_dep; $types .= "s";
}
if ($filter_jab !== '') {
    $where_clauses[] = "dk.jabatan = ?";
    $params[] = &$filter_jab; $types .= "s";
}
if ($filter_aktive !== '') {
    $where_clauses[] = "dk.aktive = ?";
    $params[] = &$filter_aktive; $types .= "s";
}

$where_sql = count($where_clauses) ? implode(" AND ", $where_clauses) : "1=1";

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = max(1, intval($_GET['per'] ?? 25));
$offset = ($page - 1) * $per_page;

// 1) Count total
$count_sql = "SELECT COUNT(*) FROM data_karyawan dk WHERE $where_sql";
$stmt = $conn->prepare($count_sql);
if ($params) {
    // bind dynamic params
    $bind_names = array_merge([$types], $params);
    // mysqli bind_param needs references
    $tmp = [];
    foreach ($bind_names as $key => $value) {
        $tmp[$key] = &$bind_names[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($total_count);
$stmt->fetch();
$stmt->close();

$total = intval($total_count);

// 2) Select page data (explicit columns so we can bind_result)
$sql = "SELECT 
            dk.no_staff, dk.nama, dk.LP, dk.dept, dk.jabatan, dk.tgl_masuk, 
            dk.no_telp, dk.foto, dk.aktive,
            d.nama_bagian AS dep_name, j.jabatan AS jab_name
        FROM data_karyawan dk
        LEFT JOIN bagian d ON dk.dept = d.id
        LEFT JOIN jabatan j ON dk.jabatan = j.idj
        WHERE $where_sql
        ORDER BY dk.no_staff DESC
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);

// bind params + offset + per_page
if ($params) {
    // we need to append offset and per_page (integers)
    $types2 = $types . "ii";
    $bind_vals = $params;
    $bind_vals[] = &$offset;
    $bind_vals[] = &$per_page;

    $bind_names = array_merge([$types2], $bind_vals);
    $tmp = [];
    foreach ($bind_names as $key => $value) {
        $tmp[$key] = &$bind_names[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $tmp);
} else {
    $stmt->bind_param("ii", $offset, $per_page);
}

$stmt->execute();
$stmt->store_result();

// bind result variables
$stmt->bind_result(
    $no_staff, $nama, $LP, $dept_id, $jab_id, $tgl_masuk,
    $no_telp, $foto, $aktive, $dep_name, $jab_name
);

// fetch into array for easier iteration (while fetch)
$rows = [];
while ($stmt->fetch()) {
    $rows[] = [
        'no_staff' => $no_staff,
        'nama' => $nama,
        'LP' => $LP,
        'dept_id' => $dept_id,
        'jab_id' => $jab_id,
        'tgl_masuk' => $tgl_masuk,
        'no_telp' => $no_telp,
        'foto' => $foto,
        'aktive' => $aktive,
        'dep_name' => $dep_name,
        'jab_name' => $jab_name
    ];
}
$stmt->close();

// fetch dep/jab lists for filters (non-prepared is fine here if no user input)
$depList = $conn->query("SELECT id, departemen FROM dep ORDER BY departemen ASC");
$jabList = $conn->query("SELECT idj, jabatan FROM jabatan ORDER BY jabatan ASC");

// helper to build querystring for pagination links preserving filters
function build_qs($overrides = []) {
    $qs = array_merge($_GET, $overrides);
    return '?' . http_build_query($qs);
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Data Karyawan</title>
<link href="assets/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<style>
body{font-family:Segoe UI, sans-serif;background:#f5f6fa;margin:0}
:root {
    --primary: #2073a9;
    --primary-dark: #154e75;
    --primary-soft: #e8f1f7;
    --radius: 8px;
}
.app-nav { background: var(--primary-dark); padding: 10px 0; }
.navbar-brand { font-weight: 800; letter-spacing: .02em; color: #fff !important; }
.brand-mark {
    width: 38px; height: 38px; border-radius: var(--radius);
    display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,.14); color: #fff;
}
.app-nav .btn { background: transparent !important; border: 1px solid rgba(255,255,255,.55) !important; color: #fff !important; font-size: 0.85rem; font-weight: 700; padding: 6px 12px; }
.app-nav .btn:hover { background: rgba(255,255,255,.15) !important; }

.container{max-width:1200px;margin:20px auto;background:#fff;padding:20px;border-radius:8px}
.table{width:100%;border-collapse:collapse;margin-top:12px}
.table th{background:#2073a9;color:#fff;padding:10px}
.table td{border:1px solid #ddd;padding:8px;text-align:center}
.controls{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
input[type="text"], select{padding:8px;border-radius:6px;border:1px solid #ccc}
.btn{padding:8px 12px;border-radius:6px;background:#17a2b8;color:#fff;text-decoration:none}
.btn-add{background:#28a745}
.switch{position:relative;display:inline-block;width:50px;height:24px}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;background:#ccc;border-radius:24px;top:0;left:0;bottom:0;right:0}
.slider:before{content:"";position:absolute;background:white;height:18px;width:18px;border-radius:50%;left:3px;bottom:3px;transition:.4s}
input:checked + .slider{background:#28a745}
input:checked + .slider:before{transform:translateX(26px)}
.pagination{margin-top:12px;text-align:right}
img.small{border-radius:6px}
</style>

<script>
function toggleAktive(id,current){
    var xhr=new XMLHttpRequest();
    xhr.open("POST","karyawan.php",true);
    xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    xhr.onload=function(){
        if(xhr.status==200){
            document.getElementById('switch_'+id).checked = (xhr.responseText == 'aktive');
        }
    };
    xhr.send("toggle_aktive=1&id="+id+"&current="+encodeURIComponent(current));
}

function goExport(){
    var q = encodeURIComponent(document.getElementById('q').value);
    var dep = document.getElementById('dep').value;
    var jab = document.getElementById('jab').value;
    var aktive = document.getElementById('aktive').value;
    window.location = 'export.php?format=csv&q='+q+'&dep='+dep+'&jab='+jab+'&aktive='+aktive;
}
</script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark app-nav mb-4">
        <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-flex align-items-center gap-2" href="home.php">
                <span class="brand-mark"><i class="fa-solid fa-cart-shopping"></i></span>
                <span>MKB</span>
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
<div class="container">
    <h2>Data Karyawan</h2>

    <div class="controls">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="text" id="q" name="q" placeholder="Cari nama atau NIK..." value="<?= e($search) ?>">
            <select id="dep" name="dep">
                <option value="">-- Semua Dept --</option>
                <?php 
                // reset pointer if needed
                if ($depList) {
                    while ($r = $depList->fetch_assoc()) {
                        $sel = ($filter_dep == $r['id']) ? 'selected' : '';
                        echo '<option value="'.e($r['id']).'" '.$sel.'>'.e($r['departemen']).'</option>';
                    }
                }
                ?>
            </select>
            <select id="jab" name="jab">
                <option value="">-- Semua Jabatan --</option>
                <?php 
                if ($jabList) {
                    while ($r = $jabList->fetch_assoc()) {
                        $sel = ($filter_jab == $r['idj']) ? 'selected' : '';
                        echo '<option value="'.e($r['idj']).'" '.$sel.'>'.e($r['jabatan']).'</option>';
                    }
                }
                ?>
            </select>
            <select id="aktive" name="aktive">
                <option value="">-- Semua Status --</option>
                <option value="aktive" <?= ($filter_aktive == 'aktive') ? 'selected' : '' ?>>Aktive</option>
                <option value="nonaktive" <?= ($filter_aktive == 'nonaktive') ? 'selected' : '' ?>>Nonaktive</option>
            </select>
            <button class="btn" type="submit">Filter</button>
        </form>

        <div style="margin-left:auto;display:flex;gap:8px;">
            <a class="btn btn-add" href="tambah_karyawan.php">+ Tambah Karyawan</a>
           <a href="export_excel.php" class="btn btn-success">Export Excel</a>

        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>No</th><th>Nama</th><th>LP</th><th>Dept</th><th>Jabatan</th><th>Tgl Masuk</th><th>Telepon</th><th>Foto</th><th>Aktif</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $no = $offset + 1;
        foreach ($rows as $row):
        ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= e($row['nama']) ?></td>
                <td><?= e($row['LP']) ?></td>
                <td><?= e($row['dep_name'] ?: $row['dept_id']) ?></td>
                <td><?= e($row['jab_name'] ?: $row['jab_id']) ?></td>
                <td><?= e($row['tgl_masuk']) ?></td>
                <td><?= e($row['no_telp']) ?></td>
                <td><?php if ($row['foto']) { ?><img src="uploads/<?= e($row['foto']) ?>" width="40" height="40" class="small"><?php } ?></td>
                <td>
                    <label class="switch">
                        <input type="checkbox" id="switch_<?= $row['no_staff'] ?>" <?= ($row['aktive'] == 'aktive') ? 'checked' : '' ?> onclick="toggleAktive(<?= $row['no_staff'] ?>,'<?= $row['aktive'] ?>')">
                        <span class="slider"></span>
                    </label>
                </td>
                <td>
                    <a class="btn" href="detailkaryawan.php?id=<?= $row['no_staff'] ?>">Detail</a>
                    <a class="btn" href="edit_karyawan.php?id=<?= $row['no_staff'] ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php
        $pages = max(1, ceil($total / $per_page));
        for ($p = 1; $p <= $pages; $p++) {
            $style = ($p == $page) ? 'background:#ddd;padding:6px 10px;border-radius:4px;margin-left:6px;' : 'margin-left:6px;';
            $qs = $_GET;
            $qs['page'] = $p;
            $qs['per'] = $per_page;
            $link = '?' . http_build_query($qs);
            echo "<a href='$link' style='$style'>$p</a>";
        }
        ?>
    </div>

</div>
</body>
</html>
