<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================
// ✅ SESSION AMAN
// ============================================
session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'use_only_cookies' => true,
    'use_strict_mode' => true,
]);

// echo "<pre>";
// print_r($_SESSION);
// echo "</pre>";
// die(); // Hentikan proses sementara untuk melihat datanya

// ============================================
// ✅ CEK LOGIN VALID
// ============================================
if (
    !isset($_SESSION['userid']) ||
    !isset($_SESSION['bagian']) ||
    !isset($_SESSION['jabatan']) ||
    !isset($_SESSION['location'])
) {
    header("Location: index.php");
    exit();
}

$userid = $_SESSION['userid'];
$bagian = $_SESSION['bagian'];
$jabatan = $_SESSION['jabatan'];
$location = $_SESSION['location']; // HO / CABANG

require_once 'config.php';

// ============================================
// ✅ KONEKSI DATABASE
// ============================================
$servername = getenv('DB_HOST');
$db_username = getenv('DB_USER');
$db_password = getenv('DB_PASS');
$database = getenv('DB_NAME');

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$database;charset=utf8mb4", $db_username, $db_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// ============================================
// ✅ CEK STATUS SALES USER
// ============================================
$user_sales_ids = [];
$is_sales = false;
if ($bagian === 'sales') { // Jika Sales
    $stmt_sales = $pdo->prepare("SELECT id_gudang FROM master_sales WHERE userid = ?");
    $stmt_sales->execute([$userid]);
    $user_sales_ids = $stmt_sales->fetchAll(PDO::FETCH_COLUMN);
    $is_sales = count($user_sales_ids) > 0;
}

// ============================================
// ✅ ATURAN AKSES MENU
// ============================================
if ($bagian === 'owner') {
    // OWNER / SUPER ADMIN → Load semua menu dari DB
    $sql = "SELECT * FROM menu WHERE aktif = 1 ORDER BY urutan";
    $stmt = $pdo->query($sql);
} elseif ($is_sales) {
    // SALES → Load menu dari DB kecuali ar.php (id 79)
    $sql = "SELECT * FROM menu WHERE aktif = 1 AND id_menu != 79 ORDER BY urutan";
    $stmt = $pdo->query($sql);
} else {
    if ($location === "HO") {
        // LOKASI HO → akses berdasarkan bagian
        $sql = "
            SELECT DISTINCT m.*
            FROM menu m
            INNER JOIN akses_bagian ab ON ab.id_menu = m.id_menu
            WHERE m.aktif = 1
            AND ab.bagian = :bagian_id
            ORDER BY m.urutan
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['bagian_id' => $bagian]);
    } else {
        // LOKASI CABANG → akses berdasarkan jabatan dan bagian
        $sql = "
            SELECT DISTINCT m.*
            FROM menu m
            INNER JOIN akses_jabatan aj ON aj.id_menu = m.id_menu
            WHERE m.aktif = 1
            AND aj.bagian = :bagian_id
            AND aj.jabatan = :jabatan_id
            ORDER BY m.urutan
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'bagian_id' => $bagian,
            'jabatan_id' => $jabatan
        ]);
    }
}

$menus = $stmt->fetchAll();

// ============================================
// ✅ HITUNG BADGE NOTIFIKASI
// ============================================
$badge_counts = [];
try {
    // 1. Barang masuk supplier (sjbeli.php) -> Harga 0 (Belum di-invoice)
    $q1 = $pdo->query("SELECT COUNT(*) FROM (SELECT SJ FROM transaksiho1 WHERE jumlah_m > 0 GROUP BY SJ HAVING SUM(harga_m) = 0) as t");
    $c1 = (int) $q1->fetchColumn();
    $badge_counts[61] = $c1;
    $badge_counts[62] = $c1;

    // 2. Kurang pelunasan pembelian (ap.php) -> Sisa hutang > 0
    $q2 = $pdo->query("SELECT COUNT(*) FROM (SELECT inv FROM pembelianho1 WHERE j NOT LIKE 'co%' GROUP BY inv HAVING (MAX(hargat_m) - SUM(COALESCE(bayar,0)) - SUM(COALESCE(pph,0))) > 0) as t");
    $badge_counts[75] = (int) $q2->fetchColumn();

    // 3. Antar Gudang (sjrekap.php) -> SJ Mutasi belum diterima (di tabel antar)
    $q3 = $pdo->query("SELECT COUNT(DISTINCT t1.sj) FROM transaksiho1 t1 LEFT JOIN antar tr ON tr.sj = t1.sj WHERE t1.jumlah_k > 0 AND t1.j = 'out' AND tr.notrim IS NULL");
    $c3 = (int) $q3->fetchColumn();
    $badge_counts[91] = $c3;
    $badge_counts[92] = $c3;

    // 4. Penjualan (pos.php) -> SJ Penjualan belum di-invoice
    $q4 = $pdo->query("SELECT COUNT(*) FROM (SELECT SJ FROM transaksiho1 WHERE jumlah_k > 0 AND j = 'jual' GROUP BY SJ HAVING SUM(harga_k) = 0) as t");
    $badge_counts[59] = (int) $q4->fetchColumn();

    // 5. Pelunasan penjualan (ar.php) -> Sisa piutang > 0 (dari penjualanho1)
    $q5 = $pdo->query("SELECT COUNT(*) FROM penjualanho1 WHERE sisa > 0");
    $badge_counts[79] = (int) $q5->fetchColumn();

    // 6. Jurnal ditarik ke LK (jurnal.php) -> Belum posting
    $q6 = $pdo->query("SELECT COUNT(DISTINCT journal_number) FROM jurnal WHERE posting = 'N' OR posting = '' OR jurnal_sementara != ''");
    $c6 = (int) $q6->fetchColumn();
    $badge_counts[32] = $c6;
    $badge_counts[33] = $c6;

    // 7. Notifikasi akumulasi untuk Sales 1 - 5 (Menu 93 - 97)
    for ($i = 1; $i <= 5; $i++) {
        $total_pending = 0;

        // Menghitung Kirim Belum Terima & Terima Belum
        $stmt_kt = $pdo->prepare("SELECT COUNT(*) FROM antar WHERE (notrim='' OR notrim IS NULL) AND (pengirim = ? OR penerima = ?)");
        $stmt_kt->execute([$i, $i]);
        $total_pending += (int) $stmt_kt->fetchColumn();

        // Menghitung AP Sales Belum Lunas
        $stmt_usr = $pdo->prepare("SELECT userid FROM master_sales WHERE id_gudang = ?");
        $stmt_usr->execute([$i]);
        $users = $stmt_usr->fetchAll(PDO::FETCH_COLUMN);

        if (count($users) > 0) {
            $in_clause = str_repeat('?,', count($users) - 1) . '?';
            $sql_ap = "SELECT COUNT(*) FROM penjualanho1 WHERE sisa > 0 AND userinv IN ($in_clause)";
            $stmt_ap = $pdo->prepare($sql_ap);
            $stmt_ap->execute($users);
            $total_pending += (int) $stmt_ap->fetchColumn();
        }

        $menu_id = 92 + $i; // Menu 93 = Sales 1
        if ($total_pending > 0) {
            $badge_counts[$menu_id] = $total_pending;
        }
    }

} catch (Exception $e) {
    // Abaikan jika error
}

$filtered_menus = [];
foreach ($menus as $m) {
    // Cek apakah ini menu sales (gudang/home.php?id=...)
    if ($bagian !== 'owner' && preg_match('/gudang\/home\.php\?id=(\d+)/', $m['file_menu'], $matches)) {
        $id_gudang_menu = (int) $matches[1];
        if ($is_sales) {
            // Sembunyikan gudang yang BUKAN miliknya, ATAU sembunyikan Gudang Pusat (0)
            if (!in_array($id_gudang_menu, $user_sales_ids) || $id_gudang_menu == 0) {
                continue; // Jangan tampilkan
            }
        }
    } else {
        // Ini adalah Menu Standar (bukan menu Gudang 1-5)
        if ($bagian !== 'owner' && $is_sales) {
            // JANGAN KUNCI menu Report Sales (60) dan Master Customer (65)
            if ($m['id_menu'] != 60 && $m['id_menu'] != 65) {
                // KUNCI MENU STANDAR UNTUK SALES
                $m['file_menu'] = "javascript:alert('Akses Ditolak: Anda login sebagai Sales. Menu ini hanya dapat diakses oleh Pusat / HO.');";
            }
        }
    }

    $m['badge1'] = "";
    $m['badge2'] = "";

    // Set badge1 jika ada angka > 0
    $id_menu = (int) $m['id_menu'];
    if (isset($badge_counts[$id_menu]) && $badge_counts[$id_menu] > 0) {
        $m['badge1'] = $badge_counts[$id_menu];
    }

    $filtered_menus[] = $m;
}
$menus = $filtered_menus;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>symotech.id</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* ====== GLOBAL STYLES ====== */
        body {
            background-image: url('background.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            font-family: ROG, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            box-sizing: border-box;
        }

        /* ====== HEADER ====== */
        header {
            position: relative;
            top: 0;
            left: 10;
            width: 100%;
            text-align: center;
            margin-bottom: 0;
        }

        #headt {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .logo {
            position: fixed;
            top: 10px;
            left: 10px;
            width: 400px;
            height: auto;
            filter: drop-shadow(0 0 0px rgba(255, 255, 255, 1));
        }

        /* ====== CONTAINER ====== */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 6rem 10px 4rem;
            box-sizing: border-box;
            text-align: center;
        }

        /* ====== ANIMATION ====== */
        @keyframes jatuh {
            0% {
                transform: translateY(-100px);
                opacity: 0;
            }

            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* ====== TEXT STYLES ====== */
        h1 {
            margin: 0;
            text-align: center;
            font-size: 48px;
            overflow: hidden;
            background-clip: text;
            -webkit-background-clip: text;
            color: white;
        }

        h2 {
            color: white;
            margin: 10px 0 0;
            text-align: center;
        }

        h3 {
            color: white;
            margin: 10px 0;
            font-size: 36px;
            text-align: center;
            animation: jatuh 2s ease-in-out forwards;
        }

        h4 {
            color: white;
            margin: 0;
            text-align: center;
            font-size: 20px;
        }

        p {
            color: white;
            margin: 0;
        }

        /* ====== ANIMATED TEXT ====== */
        .animated-text {
            display: inline-block;
            animation: jatuh 1s ease-in-out forwards;
            opacity: 0;
        }

        /* ====== HOME ICON ====== */
        .home-icon {
            position: absolute;
            top: 10px;
            right: 10px;
            color: #f00;
            transform: scale(1.3);
            transition: transform 0.3s;
        }

        .home-icon:hover {
            transform: scale(3.1);
            color: blue;
        }

        /* ====== ICON GRID ====== */
        .icons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 20px;
            justify-content: center;
            padding: 20px;
            width: 100%;
            box-sizing: border-box;
            /* KUNCI: Agar padding tidak menambah lebar */
        }

        .icon {
            text-align: center;
            /* margin: 10px; <--- DIHAPUS: Ini biang kerok yang bikin asimetris karena sudah pakai gap di .icons */
            padding: 10px;
            background-color: rgba(10, 100, 10, 0.8);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .icon i {
            font-size: 50px;
            background: linear-gradient(to right, #fff, #fff, #fff, #fff, skyblue, #0e504d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .icon p {
            color: white;
            font-weight: bold;
            margin: 10px 0 0;
        }

        .icon:hover {
            transform: scale(1.05) translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.3);
        }

        /* ====== CONTACT FORM ====== */
        .contact-form {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .contact-form h3 {
            color: white;
            margin-bottom: 20px;
        }

        .contact-form input[type="text"],
        .contact-form input[type="email"],
        .contact-form textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: none;
            border-radius: 5px;
            background-color: #06424a;
            color: white;
            transition: background-color 0.3s;
        }

        .contact-form input:focus,
        .contact-form textarea:focus {
            outline: none;
            box-shadow: 0 0 5px #0e504d;
        }

        .contact-form button {
            background-color: #06424a;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 20px;
            font-size: 30px;
            cursor: pointer;
            transition: background-color 0.3s;
            width: 500%;
        }

        .contact-form button:hover {
            background-color: #0e504d;
        }

        form button[type="submit"] {
            background-color: #0529f5;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px;
            font-size: 15px;
            width: 30%;
            margin-bottom: 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        /* ====== FOOTER ====== */
        footer {
            text-align: center;
            padding: 1.5rem 0;
            width: 100%;
            margin-top: auto;
        }

        footer p {
            margin: 0;
            font-size: 0.8rem;
            color: #000000;
            font-weight: bold;
            letter-spacing: 1px;
        }

        /* ====== SECTIONS ====== */
        .section {
            background: #fff;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #ccc;
        }

        .status {
            font-size: 18px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .card {
            background-color: #e9f5ff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: scale(1.02);
        }

        .card h4 {
            margin: 0 0 10px;
            color: #0056b3;
        }

        .card p {
            margin: 0;
            color: #333;
        }

        /* ====== SECTION BACKGROUNDS ====== */
        #tentang {
            background-image: linear-gradient(to right, #41acba, #015d91, #06424a);
            color: white;
        }

        #layanan {
            background-image: linear-gradient(to right, #41acba, #bf721b, #06424a);
            color: white;
        }

        #portofolio {
            background-image: linear-gradient(to right, #41acba, #8c3577, #06424a);
            color: white;
        }

        #kontak {
            background-image: linear-gradient(to right, #41acba, #0c7d34, #06424a);
            color: white;
        }

        /* ====== PORTOFOLIO GRID ====== */
        .portofolio-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .icon-box {
            width: 100%;
        }

        /* ===== BADGE OVERLAY ===== */
        .icon {
            position: relative;
            overflow: visible;
        }

        .badge-overlay {
            position: absolute;
            top: 6px;
            right: 6px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            z-index: 50;
        }

        .badge-overlay span {
            padding: 3px 7px;
            min-width: 26px;
            text-align: center;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
        }

        .badge-red {
            background: #d9534f;
            color: #fff;
        }

        .badge-yellow {
            background: #f0ad4e;
            color: #111;
        }

        /* ====== TABLET/MOBILE RESPONSIVE ====== */
        @media only screen and (max-width: 720px) {
            .icons {
                grid-template-columns: repeat(3, 1fr);
                padding: 10px;
                /* Lebihkan sedikit ruang bernafas di HP */
                gap: 15px;
                /* Jarak antar kotak disesuaikan */
            }

            .icon {
                aspect-ratio: 1 / 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                border-radius: 8px;
            }

            .icon i {
                font-size: 35px;
            }

            .icon p {
                font-size: 12px;
            }

            .logo {
                width: 180px;
                height: auto;
            }

            h1 {
                font-size: 22px;
                /* Dibuat lebih kecil sesuai permintaan */
                margin-top: 30px;
                word-wrap: break-word;
                /* KUNCI: Mencegah kata panjang menjebol layar */
                padding: 0 10px;
            }
        }
    </style>



</head>


<body>



    <header style="position: fixed; top: 0; left: 0; width: 100%; display: flex; justify-content: space-between; align-items: center; padding: 10px 20px; box-sizing: border-box; z-index: 999; background: transparent;">
        <img src="assets/img/logo.png" alt="Logo" style="max-width: 50vw; max-height: 100px; height: auto; object-fit: contain;">
        <a href="logout.php" class="left-icon" style="font-size: 24px; text-decoration: none; color: red; text-align: center; margin-top: 5px;">
            <i class="fa-solid fa-person-walking-dashed-line-arrow-right"></i>
            <p style="margin: 0; font-size: 12px; line-height: 1;">logout</p>
        </a>
    </header>



    <div class="container">

        <h1 id="animated-heading">PT. BFB</h1>

        <section id="hero">
            <div class="icons">

                <?php if (!empty($menus)): ?>
                    <?php foreach ($menus as $m): ?>

                        <div class="icon">

                            <?php 
                            // Hitung dynamic badge untuk Verifikasi Order dan RPC
                            $dynamic_badge = "";
                            $menu_name = trim($m['nama_menu']);
                            if ($menu_name === 'Verifikasi Order') {
                                $stmt_badge = $pdo->query("SELECT COUNT(*) FROM penjualanho1 WHERE J LIKE '%ORD%' AND (inv IS NULL OR inv = '')");
                                $count = $stmt_badge->fetchColumn();
                                if ($count > 0) $dynamic_badge = $count;
                            } elseif ($menu_name === 'Rekap SJ (RPC)') {
                                $stmt_badge = $pdo->query("SELECT COUNT(*) FROM penjualanho1 WHERE inv LIKE '%SJ%' AND (no_rpc IS NULL OR no_rpc = '')");
                                $count = $stmt_badge->fetchColumn();
                                if ($count > 0) $dynamic_badge = $count;
                            }
                            ?>
                            <?php if ($m['badge1'] !== "" || $m['badge2'] !== "" || $dynamic_badge !== ""): ?>
                                <div class="badge-overlay">
                                    <?php if (!empty($dynamic_badge)): ?>
                                        <span class="badge-red"><?= htmlspecialchars($dynamic_badge) ?></span>
                                    <?php elseif (!empty($m['badge1'])): ?>
                                        <span class="badge-red"><?= htmlspecialchars($m['badge1']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($m['badge2'])): ?>
                                        <span class="badge-yellow"><?= htmlspecialchars($m['badge2']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <a href="<?= htmlspecialchars($m['file_menu']) ?>" style="text-decoration:none;">
                                <i class="<?= htmlspecialchars($m['icon_menu']) ?>"></i>
                                <p style="color:white; font-weight:bold;"><?= htmlspecialchars($m['nama_menu']) ?></p>
                            </a>

                        </div>

                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </section>

    </div>

    <footer>
        <p>© 2025 SYMOTECH</p>
    </footer>

    <script>
        // ==== Animasi Tulisan PT ARNI FAMILY ====
        document.addEventListener("DOMContentLoaded", function () {
            var heading = document.getElementById('animated-heading');
            var text = heading.textContent.trim();
            heading.textContent = '';

            for (var i = 0; i < text.length; i++) {
                var span = document.createElement('span');
                span.textContent = text[i];
                span.classList.add('animated-text');
                span.style.animationDelay = (i * 0.1) + 's';
                heading.appendChild(span);
            }
        });

        // // ==== Disable klik kanan + F12 ====
        // document.addEventListener('contextmenu', e => e.preventDefault());
        // document.addEventListener('keydown', function(e) {
        //     if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && ['I','C'].includes(String.fromCharCode(e.keyCode))) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
        //         e.preventDefault();
        //     }
        // });
    </script>

</body>

</html>