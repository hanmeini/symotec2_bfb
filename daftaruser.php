<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- KONEKSI DATABASE ---
require_once 'config1.php';

// --- CEK LOGIN ---
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- PROSES FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("CSRF token tidak valid.");
    }

    $action = $_POST['action'];

    // --- TAMBAH USER ---
    if ($action === 'add') {
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $location = $_POST['location'];
        $jabatan = $_POST['jabatan'];
        $bagian = $_POST['bagian'];

        // 🔹 CEK USERNAME SUDAH ADA BELUM
        $cek = $conn->prepare("SELECT userid FROM me WHERE username = ?");
        $cek->bind_param("s", $username);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            echo "<script>
                    alert('❌ Username sudah digunakan, silakan pilih username lain!');
                    window.history.back();
                  </script>";
            $cek->close();
            exit();
        }
        $cek->close();

        // 🔹 INSERT DATA BARU
        $stmt = $conn->prepare("INSERT INTO me (username, password, location, jabatan, bagian, aktif) VALUES (?, ?, ?, ?, ?, '1')");
        $stmt->bind_param("sssss", $username, $password, $location, $jabatan, $bagian);
        $stmt->execute();
        $stmt->close();

        echo "<script>
                alert('✅ User berhasil ditambahkan.');
                window.location.href='daftaruser.php';
              </script>";
        exit();
    }

    // --- EDIT USER ---
    if ($action === 'edit') {
        $userid = intval($_POST['userid']);
        $username = trim($_POST['username']);
        $location = $_POST['location'];
        $jabatan = $_POST['jabatan'];
        $bagian = $_POST['bagian'];

        // 🔹 CEK USERNAME SUDAH ADA (kecuali milik sendiri)
        $cek = $conn->prepare("SELECT userid FROM me WHERE username = ? AND userid <> ?");
        $cek->bind_param("si", $username, $userid);
        $cek->execute();
        $cek->store_result();

        if ($cek->num_rows > 0) {
            echo "<script>
                    alert('❌ Username sudah digunakan oleh user lain!');
                    window.history.back();
                  </script>";
            $cek->close();
            exit();
        }
        $cek->close();

        // 🔹 UPDATE DATA
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE me SET username=?, password=?, location=?, jabatan=?, bagian=? WHERE userid=?");
            $stmt->bind_param("sssssi", $username, $password, $location, $jabatan, $bagian, $userid);
        } else {
            $stmt = $conn->prepare("UPDATE me SET username=?, location=?, jabatan=?, bagian=? WHERE userid=?");
            $stmt->bind_param("ssssi", $username, $location, $jabatan, $bagian, $userid);
        }
        $stmt->execute();
        $stmt->close();

        echo "<script>
                alert('✅ Data user berhasil diperbarui.');
                window.location.href='daftaruser.php';
              </script>";
        exit();
    }

    // --- TOGGLE AKTIF / NONAKTIF ---
    if ($action === 'toggle') {
        $userid = intval($_POST['userid']);
        $aktif = intval($_POST['aktif']) ? '0' : '1';
        $stmt = $conn->prepare("UPDATE me SET aktif=? WHERE userid=?");
        $stmt->bind_param("si", $aktif, $userid);
        $stmt->execute();
        $stmt->close();

        header("Location: daftaruser.php");
        exit();
    }
}

// --- DATA SELECT OPTION (Hardcoded) ---
// Lokasi: sales1, sales2, sales3, HO
// Jabatan: manager, staff
// Bagian: owner, accounting, sales

// --- DATA USER ---
$sql = "SELECT userid, username, location, jabatan, bagian, aktif
        FROM me
        ORDER BY location";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Master User</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
body {
    background-image: url('background.jpg');
    font-family: Arial, sans-serif;
    color: maroon;
    margin: 0; padding: 20px;
}
h2 { text-align: center; color: maroon; }
.container { max-width: 1100px; margin: auto; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.2); }
table { width: 100%; border-collapse: collapse; margin-top: 20px; color: black; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: blue; color: white; }
button, input, select {
    padding: 6px 10px; border-radius: 5px; border: 1px solid #ccc;
}
button {
    background: blue; color: white; border: none; cursor: pointer;
}
button:hover { background: #8b0000; }
.form-inline { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.search-box { text-align: center; margin-top: 10px; }
input[type=text], input[type=password], select { min-width: 150px; }
.status-active { color: green; font-weight: bold; }
.status-inactive { color: red; font-weight: bold; }
</style>
</head>
<body>

<div class="container">
    <a href="home.php"><i class="fa-solid fa-home" style="font-size:24px;color:maroon;"></i></a>
    <h2>DAFTAR USER</h2>

    <!-- FORM TAMBAH / EDIT -->
    <form method="POST" class="form-inline">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="userid" id="userid">
        <input type="hidden" name="action" id="action" value="add">

        <input type="text" name="username" id="username" placeholder="Username" required>
        <input type="password" name="password" id="password" placeholder="Password">
        <select name="location" id="location" required>
            <option value="">--Cabang--</option>
            <option value="sales1">sales1</option>
            <option value="sales2">sales2</option>
            <option value="sales3">sales3</option>
            <option value="HO">HO</option>
        </select>
        <select name="jabatan" id="jabatan" required>
            <option value="">--Jabatan--</option>
            <option value="manager">manager</option>
            <option value="staff">staff</option>
        </select>
        <select name="bagian" id="bagian" required>
            <option value="">--Bagian--</option>
            <option value="owner">owner</option>
            <option value="accounting">accounting</option>
            <option value="sales">sales</option>
        </select>

        <button type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
        <button type="reset" onclick="resetForm()"><i class="fa-solid fa-rotate-left"></i> Batal</button>
    </form>

    <div class="search-box">
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Cari username atau cabang...">
    </div>

    <!-- TABEL USER -->
    <table id="userTable">
        <thead>
            <tr><th>ID</th><th>Username</th><th>Cabang</th><th>Jabatan</th><th>Bagian</th><th>Status</th><th>Aksi</th></tr>
        </thead>
        <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['userid']) ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><?= htmlspecialchars($row['jabatan'] ?: '-') ?></td>
                <td><?= htmlspecialchars($row['bagian'] ?: '-') ?></td>
                <td class="<?= $row['aktif'] == '1' ? 'status-active' : 'status-inactive' ?>">
                    <?= $row['aktif'] == '1' ? 'Aktif' : 'Nonaktif' ?>
                </td>
                <td style="white-space:nowrap;">
                    <button onclick="editUser(<?= $row['userid'] ?>, '<?= htmlspecialchars($row['username']) ?>', '<?= htmlspecialchars($row['location']) ?>', '<?= htmlspecialchars($row['jabatan'] ?? '') ?>', '<?= htmlspecialchars($row['bagian'] ?? '') ?>')">
                        <i class="fa-solid fa-pen-to-square" style="color:white;"></i>
                    </button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="userid" value="<?= $row['userid'] ?>">
                        <input type="hidden" name="aktif" value="<?= htmlspecialchars($row['aktif'] ?? '0') ?>">
                        <button type="submit">
                            <?php if ($row['aktif'] == '1'): ?>
                                <i class="fa-solid fa-user-slash" style="color:red;"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-user-check" style="color:green;"></i>
                            <?php endif; ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
function editUser(id, username, location, jabatan, bagian) {
    document.getElementById('userid').value = id;
    document.getElementById('username').value = username;
    document.getElementById('location').value = location;
    document.getElementById('jabatan').value = jabatan;
    document.getElementById('bagian').value = bagian;
    document.getElementById('action').value = 'edit';
    document.getElementById('password').placeholder = "Kosongkan jika tidak diubah";
}

function resetForm() {
    document.getElementById('action').value = 'add';
    document.getElementById('userid').value = '';
    document.getElementById('password').placeholder = 'Password';
}

function filterTable() {
    const filter = document.getElementById("searchInput").value.toLowerCase();
    const rows = document.querySelectorAll("#userTable tbody tr");
    rows.forEach(row => {
        const username = row.cells[1].textContent.toLowerCase();
        const location = row.cells[2].textContent.toLowerCase();
        row.style.display = (username.includes(filter) || location.includes(filter)) ? "" : "none";
    });
}
</script>

</body>
</html>
<?php $conn->close(); ?>
