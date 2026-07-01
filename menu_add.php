<?php






require_once 'config.php';

// === KONEKSI DATABASE ===
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
    die("Database error: " . $e->getMessage());
}


// =====================================================
// ✅ TAMBAH MENU
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {

    $nama = trim($_POST['nama_menu']);
    $file = trim($_POST['file_menu']);
    $icon = trim($_POST['icon_menu']);
    $urutan = intval($_POST['urutan']);
    $aktif = 1;   // ✅ AUTO AKTIF SAAT DITAMBAH

    $badge1_sql = trim($_POST['badge1_sql'] ?? "");
    $badge2_sql = trim($_POST['badge2_sql'] ?? "");

    if ($nama && $file) {

        $stmt = $pdo->prepare("
            INSERT INTO menu (nama_menu, file_menu, icon_menu, urutan, aktif, badge1_sql, badge2_sql)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nama, $file, $icon, $urutan, $aktif, $badge1_sql, $badge2_sql]);


        // ✅ AUTO CREATE FILE
        $target_dir = "/home/symotech/af.symotech.id/";
        $file_path = $target_dir . basename($file);

        if (preg_match('/^[a-zA-Z0-9_\-\.]+\.php$/', $file)) {
            if (!file_exists($file_path)) {

                $default_content = "<?php
include 'config.php';

if (!isset(\$_SESSION['userid'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>{$nama}</title>
<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css'>
</head>
<body>
<h1>{$nama}</h1>
<p>Halaman ini dibuat otomatis oleh sistem.</p>
</body>
</html>";

                file_put_contents($file_path, $default_content);
            }
        }

        echo "<script>alert('✅ Menu berhasil ditambahkan');location.href='menu_add.php';</script>";
        exit();
    }
}



// =====================================================
// ✅ EDIT MENU
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {

    $id = intval($_POST['id_menu']);
    $nama = trim($_POST['nama_menu']);
    $file = trim($_POST['file_menu']);
    $icon = trim($_POST['icon_menu']);
    $urutan = intval($_POST['urutan']);
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    $badge1_sql = trim($_POST['badge1_sql'] ?? "");
    $badge2_sql = trim($_POST['badge2_sql'] ?? "");

    if ($id && $nama && $file) {

        $stmt = $pdo->prepare("
            UPDATE menu 
            SET nama_menu=?, file_menu=?, icon_menu=?, urutan=?, aktif=?, badge1_sql=?, badge2_sql=?
            WHERE id_menu=?
        ");
        $stmt->execute([$nama, $file, $icon, $urutan, $aktif, $badge1_sql, $badge2_sql, $id]);

        echo "<script>alert('✅ Menu berhasil diperbarui');location.href='menu_add.php';</script>";
        exit();
    }
}



// =====================================================
// ✅ AMBIL SEMUA MENU
// =====================================================
$menus = $pdo->query("SELECT * FROM menu ORDER BY urutan ASC")->fetchAll();

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Menu Manager</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
body { font-family: Arial; background: #eef2f3; padding: 30px; }
.container { background: white; padding: 25px; border-radius: 12px; width: 900px; margin: auto; box-shadow: 0 3px 15px rgba(0,0,0,0.2); }
input, textarea { width: 100%; padding: 10px; margin: 5px 0 15px; border-radius: 8px; border: 1px solid #bbb; }
button { padding: 12px; border: none; background: #007bff; color: white; border-radius: 8px; width: 100%; font-size: 16px; cursor: pointer; }
button:hover { background: #0056b3; }
table { width: 100%; border-collapse: collapse; margin-top: 20px; }
th { background: #007bff; color: white; padding: 10px; }
td { padding: 10px; border: 1px solid #ccc; }
.badgeSQL { font-size: 11px; color: gray; }

/* ✅ TOGGLE SWITCH */
.switch {
  position: relative;
  display: inline-block;
  width: 55px;
  height: 28px;
}
.switch input { display:none; }
.slider {
  position: absolute;
  cursor: pointer;
  background-color: #ccc;
  border-radius: 34px;
  top: 0; left: 0; right: 0; bottom: 0;
  transition: .4s;
}
.slider:before {
  position: absolute;
  content: "";
  height: 22px;
  width: 22px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  border-radius: 50%;
  transition: .4s;
}
input:checked + .slider {
  background-color: #28a745;
}
input:checked + .slider:before {
  transform: translateX(26px);
}

.modal { display: none; position: fixed; top: 0; left:0; width:100%; height:100%; background: rgba(0,0,0,.6); justify-content:center; align-items:center;}
.modal-box { background:white; padding:20px; border-radius:10px; width:450px; }
.close { float:right; cursor:pointer;font-size:22px; }

</style>

</head>
<body>
  <a href="home.php" class="home-btn">🏠</a>
<div class="container">

<h2><i class="fa-solid fa-plus"></i> Tambah Menu Baru</h2>

<form method="post">
    <input type="hidden" name="action" value="add">

    <label>Nama Menu</label>
    <input type="text" name="nama_menu" required>

    <label>File PHP</label>
    <input type="text" name="file_menu" required>

    <label>Icon FontAwesome</label>
    <input type="text" name="icon_menu">

    <label>Urutan</label>
    <input type="number" name="urutan">

    <label>Badge 1 SQL</label>
    <textarea name="badge1_sql"></textarea>

    <label>Badge 2 SQL</label>
    <textarea name="badge2_sql"></textarea>

    <!-- ✅ AUTO AKTIF SAAT ADD (hidden) -->
    <input type="hidden" name="aktif" value="1">

    <button type="submit"><i class="fa-solid fa-save"></i> Simpan Menu</button>
</form>


<h2><i class="fa-solid fa-list"></i> Daftar Menu</h2>

<table>
<tr>

     <th>Urutan</th>
    <th>Icon</th>
    <th>Menu</th>
    <th>File</th>
    <th>Badge 1</th>
    <th>Badge 2</th>
    <th>Aktif</th>
    <th>Aksi</th>
</tr>

<?php $no=1; foreach ($menus as $m): ?>
<tr>
       <td><?= htmlspecialchars($m['urutan']) ?></td>

    <td><i class="<?= htmlspecialchars($m['icon_menu'] ?? "", ENT_QUOTES) ?>"></i></td>
    <td><?= htmlspecialchars($m['nama_menu'] ?? "", ENT_QUOTES) ?></td>
    <td><?= htmlspecialchars($m['file_menu'] ?? "", ENT_QUOTES) ?></td>

    <td class="badgeSQL"><?= nl2br(htmlspecialchars($m['badge1_sql'] ?? "", ENT_QUOTES)) ?></td>
    <td class="badgeSQL"><?= nl2br(htmlspecialchars($m['badge2_sql'] ?? "", ENT_QUOTES)) ?></td>

    <td>
       <label class="switch">
         <input type="checkbox" disabled <?= ($m['aktif']==1?"checked":"") ?>>
         <span class="slider"></span>
       </label>
    </td>

    <td>
        <button onclick="editMenu(
            <?= $m['id_menu'] ?>,
            `<?= addslashes($m['nama_menu'] ?? "") ?>`,
            `<?= addslashes($m['file_menu'] ?? "") ?>`,
            `<?= addslashes($m['icon_menu'] ?? "") ?>`,
            `<?= addslashes($m['badge1_sql'] ?? "") ?>`,
            `<?= addslashes($m['badge2_sql'] ?? "") ?>`,
            <?= $m['urutan'] ?>,
            <?= $m['aktif'] ?>
        )">Edit</button>
    </td>
</tr>
<?php endforeach; ?>

</table>

</div>



<!-- =================== MODAL EDIT =================== -->
<div class="modal" id="modalEdit">
<div class="modal-box">

<span class="close" onclick="document.getElementById('modalEdit').style.display='none'">&times;</span>

<h3>Edit Menu</h3>

<form method="post">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" id="edit_id" name="id_menu">

    <label>Nama Menu</label>
    <input type="text" id="edit_nama" name="nama_menu">

    <label>File PHP</label>
    <input type="text" id="edit_file" name="file_menu">

    <label>Icon FontAwesome</label>
    <input type="text" id="edit_icon" name="icon_menu">

    <label>Urutan</label>
    <input type="number" id="edit_urutan" name="urutan">

    <label>Badge 1 SQL</label>
    <textarea id="edit_b1" name="badge1_sql"></textarea>

    <label>Badge 2 SQL</label>
    <textarea id="edit_b2" name="badge2_sql"></textarea>

    <label>Aktif</label>
    <label class="switch">
        <input type="checkbox" id="edit_aktif" name="aktif">
        <span class="slider"></span>
    </label>

    <button type="submit">Update</button>
</form>

</div>
</div>


<script>
function editMenu(id,nama,file,icon,b1,b2,urutan,aktif){

    document.getElementById('modalEdit').style.display='flex';

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_file').value = file;
    document.getElementById('edit_icon').value = icon;

    document.getElementById('edit_b1').value = b1;
    document.getElementById('edit_b2').value = b2;

    document.getElementById('edit_urutan').value = urutan;
    document.getElementById('edit_aktif').checked = (aktif == 1);
}

window.onclick = function(e){
    if(e.target === document.getElementById('modalEdit')){
        document.getElementById('modalEdit').style.display='none';
    }
}
</script>


</body>
</html>
