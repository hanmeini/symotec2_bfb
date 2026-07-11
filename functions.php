<?php
// functions.php
require_once 'config.php';

function db_connect(){
    $host = getenv('DB_HOST');
    $dbname = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $conn = new mysqli($host,$username,$password,$dbname);
    if($conn->connect_error) die("Koneksi DB gagal: ".$conn->connect_error);
    $conn->set_charset("utf8mb4");
    return $conn;
}

function uploadFileSimple($fileField, $old = ''){
    $folder = "uploads/";
    $allowed = ['jpg','jpeg','png','pdf'];
    if(!isset($_FILES[$fileField]) || $_FILES[$fileField]['error']==4) return $old;
    $ext = strtolower(pathinfo($_FILES[$fileField]['name'], PATHINFO_EXTENSION));
    if(!in_array($ext,$allowed)) return $old;
    $new = $fileField."_".md5(uniqid()).".".$ext;
    if(move_uploaded_file($_FILES[$fileField]['tmp_name'], $folder.$new)) return $new;
    return $old;
}

function log_activity($action, $user, $note, $conn=null){
    // $action: add/edit/delete/toggle/export
    if(!$conn) $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO log_activity (`action`,`user`,`note`,`created_at`) VALUES(?,?,?,NOW())");
    $stmt->bind_param("sss",$action,$user,$note);
    $stmt->execute();
    $stmt->close();
}

// simple sanitize output
function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// helper to generate QR image URL (Google Chart API)
function qr_url($text, $size=200){
    $t = urlencode($text);
    return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$t}";
}

// Generate Nomor Dokumen Otomatis (Centralized)
function generateNomorDokumen($conn, $kode_dokumen) {
    $bulan_romawi = [
        1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI',
        7=>'VII', 8=>'VIII', 9=>'IX', 10=>'X', 11=>'XI', 12=>'XII'
    ];
    $bulan = (int)date('n');
    $tahun = date('Y');
    $romawi = $bulan_romawi[$bulan];

    $conn->query("LOCK TABLES master_nomor_dokumen WRITE");

    $stmtInv = $conn->prepare("
        SELECT nomor_terakhir 
        FROM master_nomor_dokumen 
        WHERE kode_dokumen = ? AND bulan = ? AND tahun = ? 
        LIMIT 1
    ");
    $stmtInv->bind_param('sis', $kode_dokumen, $bulan, $tahun);
    $stmtInv->execute();
    $resInv = $stmtInv->get_result();

    if ($resInv->num_rows > 0) {
        $rowInv = $resInv->fetch_assoc();
        $nomorUrut = (int)$rowInv['nomor_terakhir'] + 1;

        $up = $conn->prepare("
            UPDATE master_nomor_dokumen 
            SET nomor_terakhir = ? 
            WHERE kode_dokumen = ? AND bulan = ? AND tahun = ?
        ");
        $up->bind_param('isis', $nomorUrut, $kode_dokumen, $bulan, $tahun);
        $up->execute();
        if ($up->affected_rows < 0) throw new Exception('Gagal update nomor dokumen');
        $up->close();
    } else {
        $nomorUrut = 1;
        $ins = $conn->prepare("
            INSERT INTO master_nomor_dokumen (kode_dokumen, bulan, tahun, nomor_terakhir) 
            VALUES (?, ?, ?, ?)
        ");
        $ins->bind_param('sisi', $kode_dokumen, $bulan, $tahun, $nomorUrut);
        $ins->execute();
        if ($ins->affected_rows <= 0) throw new Exception('Gagal insert nomor dokumen');
        $ins->close();
    }
    $stmtInv->close();
    $conn->query("UNLOCK TABLES");

    return str_pad($nomorUrut, 4, '0', STR_PAD_LEFT) . '/' . strtoupper($kode_dokumen) . '/' . $romawi . '/' . $tahun;
}

// Fitur Konversi UOM (Unit of Measurement)
function format_konversi($total_base_qty, $rasio_besar = 0, $rasio_tengah = 0) {
    $qty_besar = 0;
    $qty_tengah = 0;
    $qty_kecil = $total_base_qty;

    if ($rasio_besar > 0) {
        $qty_besar = floor($qty_kecil / $rasio_besar);
        $qty_kecil = $qty_kecil - ($qty_besar * $rasio_besar);
    }
    
    if ($rasio_tengah > 0) {
        $qty_tengah = floor($qty_kecil / $rasio_tengah);
        $qty_kecil = $qty_kecil - ($qty_tengah * $rasio_tengah);
    }

    // Hanya tampilkan yang relevan, atau kembalikan 1.3.5 format
    return "{$qty_besar}.{$qty_tengah}.{$qty_kecil}";
}

function parse_konversi($qty_besar, $qty_tengah, $qty_kecil, $rasio_besar = 0, $rasio_tengah = 0) {
    $total = (float)$qty_kecil;
    if ($rasio_besar > 0) $total += ((float)$qty_besar * (float)$rasio_besar);
    if ($rasio_tengah > 0) $total += ((float)$qty_tengah * (float)$rasio_tengah);
    return $total;
}

?>
