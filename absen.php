<?php
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

require_once 'config1.php';

// Auto-migrate tables if they do not exist
try {
    // 1. Create absen_raw_temp if not exists
    $conn->query("CREATE TABLE IF NOT EXISTS `absen_raw_temp` (
        `id` int AUTO_INCREMENT PRIMARY KEY,
        `no_staff` int,
        `nama` varchar(255),
        `dept_excel` varchar(255),
        `tanggal` date,
        `hari` varchar(50),
        `tipe_hari` varchar(50),
        `jadwal` varchar(100),
        `scan_in` varchar(50),
        `scan_out` varchar(50), 
        `kerja` decimal(10,2),
        `lembur` decimal(10,2),
        `kurang` decimal(10,2),
        `terlambat` decimal(10,2),
        `pulang_cepat` decimal(10,2),
        `absen` decimal(10,2),
        `lupa_in_out` decimal(10,2),
        `ijin` decimal(10,2),
        `alasan_ijin` varchar(255)
    )");

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
    error_log("Error creating data_karyawan: " . $e->getMessage());
}

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

        $conn->query("INSERT IGNORE INTO `rate_gaji_harian` (`dept`, `jabatan`, `gaji_harian`, `upah_lembur_jam`) VALUES
            ('BOX', 'OPERATOR', 130000.00, 15000.00),
            ('BOX', 'STAFF', 140000.00, 16000.00),
            ('BOX', 'HARIAN', 120000.00, 15000.00),
            ('CANDLE', 'OPERATOR', 125000.00, 15000.00),
            ('CANDLE', 'STAFF', 135000.00, 16000.00)
        ");
    }
} catch (Exception $e) {
    error_log("Error creating rate_gaji_harian: " . $e->getMessage());
}

try {
    $table_check = $conn->query("SHOW TABLES LIKE 'pindah_tugas_harian'");
    if ($table_check->num_rows == 0) {
        $conn->query("CREATE TABLE `pindah_tugas_harian` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `no_staff` int NOT NULL,
          `tanggal` date NOT NULL,
          `dept` varchar(50) NOT NULL,
          `jabatan` varchar(50) NOT NULL,
          UNIQUE KEY `idx_staff_date` (`no_staff`, `tanggal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("INSERT IGNORE INTO `pindah_tugas_harian` (`no_staff`, `tanggal`, `dept`, `jabatan`) VALUES
            (3, '2026-05-08', 'CANDLE', 'OPERATOR')
        ");
    }
} catch (Exception $e) {
    error_log("Error creating pindah_tugas_harian: " . $e->getMessage());
}

try {
    $table_check = $conn->query("SHOW TABLES LIKE 'gaji_harian'");
    if ($table_check->num_rows == 0) {
        $conn->query("CREATE TABLE `gaji_harian` (
            `id` int NOT NULL AUTO_INCREMENT,
            `no_staff` int NOT NULL,
            `tanggal` date NOT NULL,
            `jabatan_aktual` varchar(100) NOT NULL,
            `dept_aktual` varchar(100) DEFAULT NULL,
            `status_hadir` enum('Hadir','Ijin','Alpa','Sakit') DEFAULT 'Hadir',
            `nominal_gaji` decimal(15,2) DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_staff_tgl` (`no_staff`, `tanggal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        // Ensure dept_aktual exists for older table versions
        $check_col = $conn->query("SHOW COLUMNS FROM `gaji_harian` LIKE 'dept_aktual'");
        if ($check_col && $check_col->num_rows == 0) {
            $conn->query("ALTER TABLE `gaji_harian` ADD COLUMN `dept_aktual` varchar(100) DEFAULT NULL AFTER `jabatan_aktual`");
        }
    }
} catch (Exception $e) {
    error_log("Error creating gaji_harian: " . $e->getMessage());
}

try {
    $table_check = $conn->query("SHOW TABLES LIKE 'riwayat_gaji_periode'");
    if ($table_check->num_rows == 0) {
        $conn->query("CREATE TABLE `riwayat_gaji_periode` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `tgl_awal` date NOT NULL,
          `tgl_akhir` date NOT NULL,
          `total_karyawan` int DEFAULT 0,
          `total_thp` decimal(15,2) DEFAULT 0.00,
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $conn->query("CREATE TABLE `riwayat_gaji_detail` (
          `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `periode_id` int NOT NULL,
          `no_staff` int NOT NULL,
          `nama` varchar(100) NOT NULL,
          `jenis_gaji` varchar(20) NOT NULL,
          `total_hadir` decimal(5,1) DEFAULT 0,
          `total_absen` decimal(5,1) DEFAULT 0,
          `total_terlambat_menit` int DEFAULT 0,
          `total_lembur_jam` decimal(5,1) DEFAULT 0,
          `gaji_dasar_periode` decimal(15,2) DEFAULT 0,
          `gaji_lembur_periode` decimal(15,2) DEFAULT 0,
          `potongan_absen` decimal(15,2) DEFAULT 0,
          `potongan_terlambat` decimal(15,2) DEFAULT 0,
          `take_home_pay` decimal(15,2) DEFAULT 0,
          `detail_harian_json` text,
          FOREIGN KEY (`periode_id`) REFERENCES `riwayat_gaji_periode`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
} catch (Exception $e) {
    error_log("Error creating riwayat_gaji tables: " . $e->getMessage());
}

// Ensure temp directory exists
$temp_dir = __DIR__ . '/temp';
if (!is_dir($temp_dir)) {
    mkdir($temp_dir, 0755, true);
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$recap_data = [];
$error_msg = "";
$success_msg = "";
$uploaded_filename = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tutup_buku') {
    $tgl_awal = $_POST['tgl_awal'];
    $tgl_akhir = $_POST['tgl_akhir'];
    $recap_json = $_POST['recap_data_json'];
    $recap_data_decoded = json_decode($recap_json, true);

    if (is_array($recap_data_decoded) && count($recap_data_decoded) > 0) {
        $total_karyawan = count($recap_data_decoded);
        $total_thp = 0;
        foreach ($recap_data_decoded as $emp) {
            $total_thp += (float)$emp['take_home_pay'];
        }

        // Hapus periode lama dengan tanggal yang sama jika ada (agar tidak double/duplikat)
        $stmt_del = $conn->prepare("DELETE FROM riwayat_gaji_periode WHERE tgl_awal = ? AND tgl_akhir = ?");
        $stmt_del->bind_param("ss", $tgl_awal, $tgl_akhir);
        $stmt_del->execute();
        $stmt_del->close();

        // Simpan Periode Baru
        $stmt_periode = $conn->prepare("INSERT INTO riwayat_gaji_periode (tgl_awal, tgl_akhir, total_karyawan, total_thp) VALUES (?, ?, ?, ?)");
        $stmt_periode->bind_param("ssid", $tgl_awal, $tgl_akhir, $total_karyawan, $total_thp);
        
        if ($stmt_periode->execute()) {
            $periode_id = $conn->insert_id;
            
            // Simpan Detail
            $stmt_detail = $conn->prepare("INSERT INTO riwayat_gaji_detail 
                (periode_id, no_staff, nama, jenis_gaji, total_hadir, total_absen, total_terlambat_menit, total_lembur_jam, gaji_dasar_periode, gaji_lembur_periode, potongan_absen, potongan_terlambat, take_home_pay, detail_harian_json) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            foreach ($recap_data_decoded as $emp) {
                $detail_json = json_encode($emp['detail_harian']);
                $stmt_detail->bind_param("iisssddiddddds", 
                    $periode_id,
                    $emp['no_staff'],
                    $emp['nama'],
                    $emp['jenis_gaji'],
                    $emp['total_hadir'],
                    $emp['total_absen'],
                    $emp['total_terlambat_menit'],
                    $emp['total_lembur_jam'],
                    $emp['gaji_dasar_periode'],
                    $emp['gaji_lembur_periode'],
                    $emp['potongan_absen'],
                    $emp['potongan_terlambat'],
                    $emp['take_home_pay'],
                    $detail_json
                );
                $stmt_detail->execute();
            }
            
            echo "<script>alert('Tutup Buku Berhasil! Data periode penggajian telah diarsipkan permanen.'); window.location.href='riwayat_gaji.php';</script>";
            exit;
        } else {
            $error_msg = "Gagal menyimpan data Tutup Buku: " . $conn->error;
        }
    } else {
        $error_msg = "Data kosong, tidak bisa melakukan Tutup Buku.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['absen_file'])) {
    // Basic verification of file
    $file = $_FILES['absen_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if ($ext !== 'xls' && $ext !== 'xlsx') {
        $error_msg = "Format file tidak valid. Silakan upload file Excel (.xlsx atau .xls).";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error_msg = "Terjadi kesalahan saat mengunggah file absensi.";
    } else {
        $dest_file = $temp_dir . '/uploaded_absen.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dest_file)) {
            $uploaded_filename = $file['name'];
            $parsed_data = null;

            try {
                $spreadsheet = IOFactory::load($dest_file);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Bersihkan tabel temp
                $conn->query("TRUNCATE TABLE `absen_raw_temp`");
                
                // Siapkan query insert
                $stmt_insert_raw = $conn->prepare("INSERT INTO `absen_raw_temp` (no_staff, nama, dept_excel, tanggal, hari, tipe_hari, jadwal, scan_in, scan_out, kerja, lembur, kurang, terlambat, pulang_cepat, absen, lupa_in_out, ijin, alasan_ijin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($worksheet->getRowIterator() as $row_idx => $row) {
                    if ($row_idx <= 2) continue;
                    
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    
                    $data = [];
                    foreach ($cellIterator as $cell) {
                        $data[] = $cell->getFormattedValue();
                    }
                    
                    if (empty($data) || empty(trim((string)($data[0] ?? '')))) continue;
                    if (strpos(trim((string)($data[0] ?? '')), "Total Personal:") === 0) continue;
                    
                    $staff_id_str = trim((string)($data[1] ?? ''));
                    if (empty($staff_id_str)) continue;
                    $staff_id = intval((float)$staff_id_str);

                    $tanggal_str = trim((string)($data[3] ?? ''));
                    $date_obj = DateTime::createFromFormat('d/m/Y', $tanggal_str);
                    $tanggal = $date_obj ? $date_obj->format('Y-m-d') : $tanggal_str;

                    $nama = trim((string)($data[0] ?? ''));
                    $dept_excel = trim((string)($data[2] ?? ''));
                    $hari = trim((string)($data[4] ?? ''));
                    $tipe_hari = trim((string)($data[5] ?? ''));
                    $jadwal = trim((string)($data[6] ?? ''));
                    $scan_in = trim((string)($data[8] ?? ''));
                    $scan_out = trim((string)($data[10] ?? ''));
                    
                    $kerja = floatval($data[15] ?? 0);
                    $lembur = floatval($data[16] ?? 0);
                    $kurang = floatval($data[17] ?? 0);
                    $terlambat = floatval($data[18] ?? 0);
                    $pulang_cepat = floatval($data[19] ?? 0);
                    $absen = floatval($data[20] ?? 0);
                    $lupa_in_out = floatval($data[21] ?? 0);
                    $ijin = floatval($data[22] ?? 0);
                    $alasan_ijin = trim((string)($data[23] ?? ''));
                    
                    $stmt_insert_raw->bind_param("issssssssdddddddds", $staff_id, $nama, $dept_excel, $tanggal, $hari, $tipe_hari, $jadwal, $scan_in, $scan_out, $kerja, $lembur, $kurang, $terlambat, $pulang_cepat, $absen, $lupa_in_out, $ijin, $alasan_ijin);
                    $stmt_insert_raw->execute();
                }
                
                $parsed_data = ["status" => "success"];
            } catch (Exception $e) {
                $error_msg = "Gagal memproses file Excel: " . $e->getMessage();
            }

            if (empty($error_msg) && isset($parsed_data['status']) && $parsed_data['status'] === 'success') {
                $success_msg = "File absensi berhasil diunggah dan dianalisis.";
                $attendance_records = [];
                $res_raw = $conn->query("SELECT * FROM absen_raw_temp ORDER BY tanggal ASC");
                if ($res_raw) {
                    while ($r = $res_raw->fetch_assoc()) {
                        $attendance_records[] = $r;
                    }
                }
                $tanggal_awal = null;
                    $tanggal_akhir = null;
                    
                    // Group by employee no_staff
                    $grouped = [];
                    foreach ($attendance_records as $rec) {
                        $no_staff = $rec['no_staff'];
                        if (!isset($grouped[$no_staff])) {
                            $grouped[$no_staff] = [];
                        }
                        $grouped[$no_staff][] = $rec;
                        
                        $t = strtotime($rec['tanggal']);
                        if ($t) {
                            if ($tanggal_awal === null || $t < strtotime($tanggal_awal)) $tanggal_awal = $rec['tanggal'];
                            if ($tanggal_akhir === null || $t > strtotime($tanggal_akhir)) $tanggal_akhir = $rec['tanggal'];
                        }
                    }
                    
                    // 1. Batch load existing data_karyawan to avoid duplicate DB calls
                    $employees_map = [];
                    $emp_res = $conn->query("SELECT * FROM data_karyawan");
                    while ($row = $emp_res->fetch_assoc()) {
                        $employees_map[$row['no_staff']] = $row;
                    }
                    
                    // 2. Batch load rekap harian (Gaji Harian & Mutasi Harian)
                    $rekap_map = [];
                    $rekap_res = $conn->query("SELECT * FROM gaji_harian");
                    if ($rekap_res) {
                        while ($row = $rekap_res->fetch_assoc()) {
                            $rekap_map[$row['no_staff'] . '_' . $row['tanggal']] = $row;
                        }
                    }
                    
                    // 3. Batch load rates
                    $rates_map = [];
                    $rate_res = $conn->query("SELECT * FROM rate_gaji_harian");
                    while ($row = $rate_res->fetch_assoc()) {
                        $rates_map[$row['dept'] . '_' . $row['jabatan']] = $row;
                    }
                    
                    // For each employee, join and calculate
                    foreach ($grouped as $no_staff => $days) {
                        // Check if employee is registered. If not, auto-register them!
                        if (!isset($employees_map[$no_staff])) {
                            $nama = $days[0]['nama'];
                            $lp = 'L'; // default
                            $dept = $days[0]['dept_excel'];
                            
                            // Infer jabatan and jenis_gaji
                            $is_harian = (stripos($dept, 'harian') !== false || stripos($dept, 'kontrak') !== false || stripos($dept, 'sawmill') !== false || stripos($dept, 'muat') !== false);
                            $jabatan = $is_harian ? 'HARIAN' : 'OPERATOR';
                            $jenis_gaji = $is_harian ? 'mingguan' : 'bulanan';
                            
                            // Infer rate
                            $rate_key = $dept . '_' . $jabatan;
                            $gaji_pokok = 0.00;
                            $upah_lembur = 0.00;
                            if (isset($rates_map[$rate_key])) {
                                $gaji_pokok = (float)$rates_map[$rate_key]['gaji_harian'];
                                $upah_lembur = (float)$rates_map[$rate_key]['upah_lembur_jam'];
                            } else {
                                $gaji_pokok = $is_harian ? 120000.00 : 3000000.00;
                                $upah_lembur = $is_harian ? 15000.00 : 20000.00;
                            }
                            
                            // Insert into database
                            $insert_stmt = $conn->prepare("INSERT INTO data_karyawan (no_staff, nama, LP, dept, jabatan, gaji_pokok, upah_lembur, jenis_gaji, aktive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'aktive')");
                            $insert_stmt->bind_param("issssdds", $no_staff, $nama, $lp, $dept, $jabatan, $gaji_pokok, $upah_lembur, $jenis_gaji);
                            $insert_stmt->execute();
                            $insert_stmt->close();
                            
                            // Add to map
                            $employees_map[$no_staff] = [
                                'no_staff' => $no_staff,
                                'nama' => $nama,
                                'LP' => $lp,
                                'dept' => $dept,
                                'jabatan' => $jabatan,
                                'gaji_pokok' => $gaji_pokok,
                                'upah_lembur' => $upah_lembur,
                                'jenis_gaji' => $jenis_gaji,
                                'aktive' => 'aktive'
                            ];
                        }
                        
                        $emp_info = $employees_map[$no_staff];
                        $emp_info['is_registered'] = true;
                        
                        $emp_recap = [
                            'no_staff' => $no_staff,
                            'nama' => $emp_info['nama'],
                            'LP' => $emp_info['LP'] ?? '-',
                            'default_dept' => $emp_info['dept'],
                            'default_jabatan' => $emp_info['jabatan'],
                            'jenis_gaji' => $emp_info['jenis_gaji'],
                            'gaji_pokok' => (float)$emp_info['gaji_pokok'],
                            'is_registered' => $emp_info['is_registered'],
                            'total_hadir' => 0.0,
                            'total_absen' => 0.0,
                            'total_ijin' => 0.0,
                            'total_lembur_jam' => 0.0,
                            'total_terlambat_menit' => 0.0,
                            'total_lupa_in_out' => 0.0,
                            'gaji_dasar_periode' => 0.0,
                            'gaji_lembur_periode' => 0.0,
                            'potongan_absen' => 0.0,
                            'potongan_terlambat' => 0.0,
                            'take_home_pay' => 0.0,
                            'detail_harian' => []
                        ];
                        
                        foreach ($days as $day) {
                            $tanggal = $day['tanggal'];
                            $mut_key = $no_staff . '_' . $tanggal;
                            
                            $is_mutasi = false;
                            $working_dept = $emp_info['dept'];
                            $working_jabatan = $emp_info['jabatan'];
                            
                            // Override from Rekap Harian (Gaji Harian)
                            if (isset($rekap_map[$mut_key])) {
                                $working_jabatan = $rekap_map[$mut_key]['jabatan_aktual'];
                                if (!empty($rekap_map[$mut_key]['dept_aktual'])) {
                                    $working_dept = $rekap_map[$mut_key]['dept_aktual'];
                                }
                                
                                // Hanya tandai sebagai mutasi jika jabatannya atau departemennya berbeda dari aslinya
                                if ($working_jabatan !== $emp_info['jabatan'] || $working_dept !== $emp_info['dept']) {
                                    $is_mutasi = true;
                                } else {
                                    // Jika sama (kembali normal), batalkan status mutasi
                                    $is_mutasi = false;
                                }
                                
                                // Jika di rekap ternyata di set Ijin/Sakit/Alpa, maka nol-kan kehadiran
                                $status = $rekap_map[$mut_key]['status_hadir'];
                                if ($status != 'Hadir') {
                                    $day['kerja'] = 0; // hapus jam kerja
                                    $day['scan_in'] = ''; // Paksa hapus scan in agar tidak dianggap hadir
                                    $day['scan_out'] = ''; // Paksa hapus scan out
                                    
                                    if ($status == 'Alpa') {
                                        $day['absen'] = 2; // set full absen (2 sesi)
                                    } elseif ($status == 'Ijin' || $status == 'Sakit') {
                                        $day['ijin'] = 2; // set full ijin (2 sesi)
                                    }
                                }
                            }
                            
                            // Calculate daily rate based on working dept/jabatan
                            $daily_rate = 0.00;
                            $overtime_hourly_rate = (float)($emp_info['upah_lembur'] ?? 0.0);
                            
                            $rate_key = $working_dept . '_' . $working_jabatan;
                            if (isset($rates_map[$rate_key])) {
                                $rate_row = $rates_map[$rate_key];
                                $daily_rate = (float)$rate_row['gaji_harian'];
                                $overtime_hourly_rate = (float)$rate_row['upah_lembur_jam'];
                            } else {
                                // Fallback if no specific standard rate exists
                                if ($emp_info['jenis_gaji'] === 'mingguan') {
                                    $daily_rate = (float)$emp_info['gaji_pokok'];
                                } else {
                                    $daily_rate = (float)$emp_info['gaji_pokok'] / 26.0;
                                }
                            }
                            
                            // Aturan baru dari atasan: Asal ada absen masuk (scan in), dihitung masuk penuh (1 hari).
                            // Jika tidak masuk sama sekali, dan mesin mencatat itu hari kerja (ada nilai absen), dihitung absen (1 hari).
                            
                            $actual_kerja = 0.0;
                            $actual_absen = 0.0;
                            $actual_ijin  = (float)$day['ijin'] > 0 ? 1.0 : 0.0; // Jika ada ijin, dianggap ijin 1 hari penuh
                            
                            if (!empty(trim((string)$day['scan_in'])) || (float)$day['kerja'] > 0) {
                                // Dia datang / scan masuk
                                $actual_kerja = 1.0;
                            } else {
                                // Tidak datang, cek apakah mesin menganggapnya absen (berarti bukan hari libur)
                                if ((float)$day['absen'] > 0) {
                                    $actual_absen = 1.0;
                                }
                            }
                            
                            // Sum metrics
                            $emp_recap['total_hadir'] += $actual_kerja;
                            $emp_recap['total_absen'] += $actual_absen;
                            $emp_recap['total_ijin'] += $actual_ijin;
                            $emp_recap['total_lembur_jam'] += (float)$day['lembur'];
                            $emp_recap['total_terlambat_menit'] += (float)$day['terlambat'];
                            $emp_recap['total_lupa_in_out'] += (float)$day['lupa_in_out'];
                            
                            // Daily calculation
                            $daily_basic_wage = 0.00;
                            // Atasan meminta agar lembur tidak usah dihitung
                            $daily_overtime_pay = 0.00; // (float)$day['lembur'] * $overtime_hourly_rate;
                            
                            if ($emp_info['jenis_gaji'] === 'mingguan') {
                                $daily_basic_wage = $actual_kerja * $daily_rate;
                            } else {
                                $daily_basic_wage = $actual_kerja * $daily_rate;
                            }
                            
                            // Karena gaji dasar sudah dihitung berdasarkan kehadiran (per hari),
                            // kita tidak perlu memotong absen lagi (jika dipotong, berarti kena penalti ganda).
                            $daily_absent_cut = 0.00;
                            $daily_late_cut = 0.00; // Denda terlambat ditiadakan sesuai instruksi user
                            
                            $emp_recap['gaji_dasar_periode'] += $daily_basic_wage;
                            $emp_recap['gaji_lembur_periode'] += $daily_overtime_pay;
                            $emp_recap['potongan_absen'] += $daily_absent_cut;
                            $emp_recap['potongan_terlambat'] += $daily_late_cut;
                            
                            $emp_recap['detail_harian'][] = [
                                'tanggal' => $tanggal,
                                'hari' => $day['hari'],
                                'tipe_hari' => $day['tipe_hari'],
                                'jadwal' => $day['jadwal'],
                                'scan_in' => $day['scan_in'],
                                'scan_out' => $day['scan_out'],
                                'kerja' => $actual_kerja,
                                'lembur' => $day['lembur'],
                                'terlambat' => $day['terlambat'],
                                'absen' => $actual_absen,
                                'ijin' => $actual_ijin,
                                'is_mutasi' => $is_mutasi,
                                'working_dept' => $working_dept,
                                'working_jabatan' => $working_jabatan,
                                'rate_harian' => $daily_rate,
                                'rate_lembur' => $overtime_hourly_rate,
                                'daily_wage' => $daily_basic_wage,
                                'daily_overtime' => $daily_overtime_pay
                            ];
                        }
                        
                        // Final Take Home Pay for period
                        if ($emp_info['jenis_gaji'] === 'bulanan') {
                            // Monthly: Basic Salary + Overtime - Deductions (for absences/lateness)
                            // Basic salary is fixed monthly, so we show basic salary proportionate or full
                            // Let's assume they get full basic salary, but deducted for absences
                            $emp_recap['take_home_pay'] = $emp_recap['gaji_pokok'] + $emp_recap['gaji_lembur_periode'] - $emp_recap['potongan_absen'] - $emp_recap['potongan_terlambat'];
                        } else {
                            // Harian/Weekly: Days worked * Daily rate + Overtime - Lateness
                            $emp_recap['take_home_pay'] = $emp_recap['gaji_dasar_periode'] + $emp_recap['gaji_lembur_periode'] - $emp_recap['potongan_terlambat'];
                        }
                        
                        $recap_data[] = $emp_recap;
                    }
                }
        } else {
            $error_msg = "Gagal memindahkan file ke direktori temp.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi & Gaji - MKB</title>
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

        .panel { border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 22px; }
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
        
        .upload-section { padding: 25px; background: #fff; border: 2px dashed var(--primary); border-radius: var(--radius); text-align: center; }
        .table-wrap { padding: 20px; }
        .emp-name { color: var(--ink); font-weight: 800; }
        .pill {
            display: inline-flex; align-items: center; gap: 6px; border-radius: 999px;
            padding: 5px 9px; font-weight: 800; font-size: .78rem; white-space: nowrap;
        }
        .pill-code { color: var(--primary-dark); background: var(--primary-soft); }
        .detail-table th { background: #34495e !important; color: #fff !important; font-size: 0.8rem; }
        .detail-table td { font-size: 0.82rem; }
        
        @page { size: A4 portrait; margin: 1cm; }
        @media print {
            body.printing-modal > * { display: none !important; }
            body.printing-modal > .modal.show { 
                display: block !important; 
                position: relative !important; 
                overflow: visible !important; 
                background: transparent !important; 
            }
            body.printing-modal .modal-dialog { 
                max-width: 100% !important; 
                width: 100% !important; 
                transform: none !important; 
                margin: 0 !important; 
                display: block !important; 
            }
            body.printing-modal .modal-content { border: none !important; box-shadow: none !important; display: block !important; }
            
            .slip-gaji { 
                page-break-inside: avoid !important; 
                break-inside: avoid !important; 
                display: block !important;
                width: 100%;
                border: 2px solid #000 !important; 
            }
            .hide-on-print { display: none !important; }
        }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark app-nav">
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
        <header class="page-header">
            <div>
                <div class="eyebrow">Payroll & Kehadiran</div>
                <h1 class="page-title">Rekap Absensi & Slip Gaji</h1>
                <p class="page-subtitle">Unggah file laporan absensi mesin (.xlsx) untuk menghitung upah kerja, lembur, dan mutasi harian secara otomatis.</p>
            </div>
        </header>

        <!-- Form Upload -->
        <section class="panel">
            <div class="panel-head">
                <div class="title-wrap">
                    <span class="title-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                    <div>
                        <h2 class="panel-title">Unggah Laporan Absensi</h2>
                        <span class="panel-subtitle">Pilih file laporan mesin (.xlsx) untuk dianalisis.</span>
                    </div>
                </div>
            </div>
            <div class="form-body p-4">
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error_msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fa-solid fa-circle-check me-2"></i><?= e($success_msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form id="uploadForm" action="absen.php" method="POST" enctype="multipart/form-data">
                    <div class="upload-section py-5">
                        <i class="fa-solid fa-file-excel fa-4x text-success mb-3"></i>
                        <h5>Pilih File Laporan Absensi Mesin (.xlsx)</h5>
                        <p class="text-muted small">File harus berupa format .xlsx standar hasil export mesin absensi.</p>
                        <div class="d-flex justify-content-center mt-3">
                            <div class="col-md-6">
                                <input type="file" name="absen_file" class="form-control mb-3" accept=".xlsx,.xls" required>
                                <button type="submit" class="btn btn-success px-4 w-100">
                                    <i class="fa-solid fa-calculator me-2"></i> Analisis & Hitung Gaji
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <!-- Rekap Tabel Hasil Analisis -->
        <?php if (!empty($recap_data)): ?>
            <section class="panel">
                <div class="panel-head">
                    <div class="title-wrap">
                        <span class="title-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                        <div>
                            <h2 class="panel-title">Laporan Rekapitulasi Gaji & Absensi</h2>
                            <span class="panel-subtitle">File yang dianalisis: <strong><?= e($uploaded_filename) ?></strong></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" onclick="window.print();" class="btn btn-outline-primary px-3 shadow-sm btn-sm">
                            <i class="fa-solid fa-print me-1"></i> Cetak Rekap
                        </button>
                        <form method="POST" action="absen.php" class="m-0 p-0" id="tutupBukuForm">
                            <input type="hidden" name="action" value="tutup_buku">
                            <input type="hidden" name="tgl_awal" value="<?= e($tanggal_awal ?? '') ?>">
                            <input type="hidden" name="tgl_akhir" value="<?= e($tanggal_akhir ?? '') ?>">
                            <input type="hidden" name="recap_data_json" value="<?= e(json_encode($recap_data)) ?>">
                            <button type="button" class="btn btn-primary px-3 shadow-sm btn-sm" onclick="confirmTutupBuku()">
                                <i class="fa-solid fa-lock me-1"></i> Tutup Buku & Simpan Slip
                            </button>
                        </form>
                    </div>
                </div>

                <div class="table-wrap">
                    <div class="table-responsive">
                        <table id="recapTable" class="table table-hover align-middle" style="width:100%;">
                            <thead>
                                <tr>
                                    <th>No. Staff</th>
                                    <th>Nama Karyawan</th>
                                    <th>Jenis Gaji</th>
                                    <th>Hadir (Hari)</th>
                                    <th>Absen (Hari)</th>
                                    <th>Terlambat (Mnt)</th>
                                    <th>Lembur (Jam)</th>
                                    <th>Gaji Pokok (Bln/Hari)</th>
                                    <th>Lembur Periode</th>
                                    <th>Potongan</th>
                                    <th class="text-end">Gaji Bersih (THP)</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recap_data as $row): ?>
                                    <tr>
                                        <td><span class="pill pill-code"><i class="fa-solid fa-id-card"></i><?= e($row['no_staff']) ?></span></td>
                                        <td>
                                            <div class="emp-name"><?= e($row['nama']) ?></div>
                                            <?php if (!$row['is_registered']): ?>
                                                <span class="badge bg-danger text-white" style="font-size:0.65rem;">Belum Terdaftar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge rounded text-uppercase <?= $row['jenis_gaji'] === 'mingguan' ? 'bg-warning text-dark' : 'bg-primary text-white' ?>">
                                                <?= e($row['jenis_gaji']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-bold text-success"><?= e($row['total_hadir']) ?></td>
                                        <td class="text-center fw-bold text-danger"><?= e($row['total_absen']) ?></td>
                                        <td class="text-center"><?= e($row['total_terlambat_menit']) ?></td>
                                        <td class="text-center fw-bold text-info"><?= e($row['total_lembur_jam']) ?></td>
                                        <td>Rp <?= number_format($row['gaji_pokok'], 2, ',', '.') ?></td>
                                        <td>Rp <?= number_format($row['gaji_lembur_periode'], 2, ',', '.') ?></td>
                                        <td class="text-danger">
                                            - Rp <?= number_format($row['potongan_absen'] + $row['potongan_terlambat'], 2, ',', '.') ?>
                                        </td>
                                        <td class="text-end fw-bold text-success">
                                            Rp <?= number_format($row['take_home_pay'], 2, ',', '.') ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-success btn-detail" 
                                                data-no_staff="<?= e($row['no_staff']) ?>"
                                                data-nama="<?= e($row['nama']) ?>"
                                                data-detail='<?= json_encode($row['detail_harian']) ?>'>
                                                <i class="fa-solid fa-eye me-1"></i> Rincian
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-slip ms-1 mt-1 mt-lg-0"
                                                data-no_staff="<?= e($row['no_staff']) ?>"
                                                data-nama="<?= e($row['nama']) ?>"
                                                data-jenis="<?= e($row['jenis_gaji']) ?>"
                                                data-pokok="<?= e($row['gaji_dasar_periode']) ?>"
                                                data-lembur="<?= e($row['gaji_lembur_periode']) ?>"
                                                data-potongan="<?= e($row['potongan_absen'] + $row['potongan_terlambat']) ?>"
                                                data-thp="<?= e($row['take_home_pay']) ?>"
                                                data-tgl="<?= e($tanggal_awal ?? '') ?> s/d <?= e($tanggal_akhir ?? '') ?>">
                                                <i class="fa-solid fa-receipt me-1"></i> Cetak
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Modal Rincian Harian -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content panel shadow-lg">
                <div class="panel-head">
                    <div class="title-wrap">
                        <span class="title-icon"><i class="fa-solid fa-list-check"></i></span>
                        <div>
                            <h3 class="panel-title" id="detailTitle">Rincian Kehadiran Harian</h3>
                            <span class="panel-subtitle" id="detailSubtitle">Log absen tanggal-ke-tanggal beserta perhitungan rate upah</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered detail-table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Hari</th>
                                    <th>Tipe Hari</th>
                                    <th>Jadwal Kerja</th>
                                    <th>Scan In</th>
                                    <th>Scan Out</th>
                                    <th>Status Kerja</th>
                                    <th>Terlambat (Mnt)</th>
                                    <th>Lembur (Jam)</th>
                                    <th>Departemen / Jabatan</th>
                                    <th>Rate Harian</th>
                                    <th>Gaji Harian</th>
                                    <th>U. Lembur</th>
                                </tr>
                            </thead>
                            <tbody id="detailTableBody">
                                <!-- Terisi via Javascript -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end" style="border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Slip Gaji -->
    <div class="modal fade" id="slipModal" tabindex="-1" aria-labelledby="slipModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content panel shadow-lg">
                <div class="modal-body p-0" id="printArea">
                    <div class="slip-gaji p-4">
                        <div class="text-center mb-4 border-bottom pb-3">
                            <h4 class="mb-1 fw-bold text-dark">PT. MKB</h4>
                            <p class="mb-0 text-muted" style="font-size: 0.9rem; letter-spacing: 2px;">SLIP GAJI KARYAWAN</p>
                        </div>
                        <table class="table table-borderless table-sm mb-4" style="font-size: 0.9rem;">
                            <tr><td width="35%" class="text-muted">Nama Karyawan</td><td width="5%">:</td><td id="slipNama" class="fw-bold text-dark"></td></tr>
                            <tr><td class="text-muted">ID Karyawan</td><td>:</td><td id="slipId" class="text-dark"></td></tr>
                            <tr><td class="text-muted">Tipe Gaji</td><td>:</td><td id="slipJenis" class="text-uppercase text-dark"></td></tr>
                            <tr><td class="text-muted">Periode</td><td>:</td><td id="slipPeriode" class="text-dark"></td></tr>
                        </table>
                        
                        <h6 class="fw-bold text-muted border-bottom pb-1 mb-2" style="font-size: 0.8rem; letter-spacing: 1px;">PENERIMAAN</h6>
                        <table class="table table-borderless table-sm mb-4 text-dark" style="font-size: 0.9rem;">
                            <tr><td width="55%">Gaji Dasar / Pokok</td><td width="5%">:</td><td width="10%">Rp</td><td id="slipPokok" class="text-end">0</td></tr>
                            <tr><td>Upah Lembur</td><td>:</td><td>Rp</td><td id="slipLembur" class="text-end">0</td></tr>
                        </table>
                        
                        <h6 class="fw-bold text-muted border-bottom pb-1 mb-2" style="font-size: 0.8rem; letter-spacing: 1px;">POTONGAN</h6>
                        <table class="table table-borderless table-sm mb-4 text-dark" style="font-size: 0.9rem;">
                            <tr><td width="55%">Absen / Terlambat / Ijin</td><td width="5%">:</td><td width="10%">Rp</td><td id="slipPotongan" class="text-end text-danger fw-bold">0</td></tr>
                        </table>
                        
                        <div class="border-top border-2 border-dark pt-2 mt-2">
                            <table class="table table-borderless table-sm mb-0">
                                <tr>
                                    <td width="55%" class="fw-bold text-dark" style="font-size: 1.1rem;">TAKE HOME PAY</td>
                                    <td width="5%" class="fw-bold fs-6">:</td>
                                    <td width="10%" class="fw-bold fs-6 text-success">Rp</td>
                                    <td id="slipThp" class="fw-bold fs-6 text-success text-end">0</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="row mt-5 pt-3 text-center text-dark" style="font-size: 0.9rem;">
                            <div class="col-6">
                                <p class="mb-5">Penerima,</p>
                                <p class="mb-0 text-decoration-underline fw-bold" id="slipTtdNama">Nama</p>
                            </div>
                            <div class="col-6">
                                <p class="mb-5">HRD / Keuangan,</p>
                                <p class="mb-0 text-decoration-underline fw-bold">....................</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between hide-on-print" style="border-top:1px solid var(--line);">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary px-4" onclick="printModal()"><i class="fa-solid fa-print me-1"></i> Cetak Dokumen</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/jquery.min.js"></script>
    <script src="assets/bootstrap.bundle.min.js"></script>
    <script src="assets/jquery.dataTables.min.js"></script>
    <script src="assets/dataTables.bootstrap5.min.js"></script>
    <script src="assets/sweetalert2.all.min.js"></script>

    <script>
        function printModal() {
            document.body.classList.add('printing-modal');
            window.print();
            setTimeout(function() {
                document.body.classList.remove('printing-modal');
            }, 1000);
        }

        function confirmTutupBuku() {
            Swal.fire({
                title: 'Apakah Anda Yakin?',
                text: 'Proses Tutup Buku akan menyimpan angka penggajian ini secara permanen ke Riwayat. Aksi ini tidak dapat dibatalkan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#2073a9',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Tutup Buku Sekarang!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('tutupBukuForm').submit();
                }
            })
        }

        $(document).ready(function() {
            if ($('#recapTable').length > 0) {
                $('#recapTable').DataTable({
                    pageLength: 25,
                    order: [[10, 'desc']], // Order by net salary descending
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
            }

            $('#recapTable').on('click', '.btn-detail', function() {
                const no_staff = $(this).data('no_staff');
                const nama = $(this).data('nama');
                const details = $(this).data('detail');

                $('#detailTitle').text('Rincian Kehadiran Harian: ' + nama);
                $('#detailSubtitle').text('Staff ID: ' + no_staff + ' | Log data absen & upah harian temporer');

                let rowsHtml = '';
                details.forEach(function(day) {
                    const dateParts = day.tanggal.split('-');
                    const formattedDate = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
                    
                    const isMutasiBadge = day.is_mutasi 
                        ? ' <span class="badge bg-warning text-dark"><i class="fa-solid fa-arrows-spin me-1"></i>Mutasi</span>' 
                        : '';
                    
                    const cellScanIn = day.scan_in || '<span class="text-danger">-</span>';
                    const cellScanOut = day.scan_out || '<span class="text-danger">-</span>';
                    
                    const cellDeptJob = day.working_dept + ' / ' + day.working_jabatan + isMutasiBadge;
                    
                    const formattedRate = 'Rp ' + parseFloat(day.rate_harian).toLocaleString('id-ID', { minimumFractionDigits: 2 });
                    const formattedWage = 'Rp ' + parseFloat(day.daily_wage).toLocaleString('id-ID', { minimumFractionDigits: 2 });
                    const formattedOvertime = 'Rp ' + parseFloat(day.daily_overtime).toLocaleString('id-ID', { minimumFractionDigits: 2 });
                    
                    let bgClass = '';
                    let statusText = day.kerja > 0 ? day.kerja + ' Hadir' : 'Libur';
                    
                    if (parseFloat(day.absen) > 0) {
                        bgClass = 'table-danger';
                        statusText = 'Alpa';
                    } else if (parseFloat(day.ijin) > 0) {
                        bgClass = 'table-warning text-dark';
                        statusText = 'Ijin / Sakit';
                    } else if (parseFloat(day.lembur) > 0) {
                        bgClass = 'table-info';
                    } else if (day.is_mutasi) {
                        bgClass = 'table-warning';
                    }

                    rowsHtml += `
                        <tr class="${bgClass}">
                            <td><strong>${formattedDate}</strong></td>
                            <td>${day.hari}</td>
                            <td>${day.tipe_hari}</td>
                            <td>${day.jadwal || '-'}</td>
                            <td>${cellScanIn}</td>
                            <td>${cellScanOut}</td>
                            <td class="text-center fw-bold">${statusText}</td>
                            <td class="text-center">${day.terlambat}</td>
                            <td class="text-center fw-bold">${day.lembur}</td>
                            <td>${cellDeptJob}</td>
                            <td>${formattedRate}</td>
                            <td class="fw-bold">${formattedWage}</td>
                            <td>${formattedOvertime}</td>
                        </tr>
                    `;
                });

                $('#detailTableBody').html(rowsHtml);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('detailModal')).show();
            });

            $('#recapTable').on('click', '.btn-slip', function() {
                const no_staff = $(this).data('no_staff');
                const nama = $(this).data('nama');
                const jenis = $(this).data('jenis');
                const pokok = parseFloat($(this).data('pokok')) || 0;
                const lembur = parseFloat($(this).data('lembur')) || 0;
                const potongan = parseFloat($(this).data('potongan')) || 0;
                const thp = parseFloat($(this).data('thp')) || 0;
                const tgl = $(this).data('tgl');

                $('#slipNama').text(nama);
                $('#slipTtdNama').text(nama);
                $('#slipId').text(no_staff);
                $('#slipJenis').text(jenis);
                $('#slipPeriode').text(tgl);
                
                $('#slipPokok').text(pokok.toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                $('#slipLembur').text(lembur.toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                $('#slipPotongan').text(potongan.toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                $('#slipThp').text(thp.toLocaleString('id-ID', { minimumFractionDigits: 2 }));
                
                bootstrap.Modal.getOrCreateInstance(document.getElementById('slipModal')).show();
            });
        });

        // Loading animation for upload form
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function() {
                Swal.fire({
                    title: 'Menganalisis Data...',
                    text: 'Sistem sedang memproses file Excel (mengekstrak dan menghitung data), harap tunggu beberapa saat...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            });
        }
    </script>
</body>
</html>
