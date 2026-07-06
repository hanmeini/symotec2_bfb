<?php

require 'config1.php';

/* =========================
   SIMPAN DATA
=========================*/
if(isset($_POST['simpan'])){

    $userid = $_POST['userid'];
    $kode   = $_POST['kode_sales'];
    $gudang = $_POST['id_gudang'];
    $hp     = $_POST['no_hp'];
    $alamat = $_POST['alamat'];
    $coa_kas = $_POST['coa_kas'];

    $stmt=$conn->prepare("
        INSERT INTO master_sales
        (userid,kode_sales,id_gudang,no_hp,alamat,coa_kas)
        VALUES (?,?,?,?,?,?)
    ");

    $stmt->bind_param("isisss",$userid,$kode,$gudang,$hp,$alamat,$coa_kas);

    if($stmt->execute()){
        $msg="✅ Data sales berhasil disimpan";
    }
}

/* =========================
   HAPUS
=========================*/
if(isset($_GET['hapus'])){
    $id=(int)$_GET['hapus'];
    $conn->query("DELETE FROM master_sales WHERE id_sales=$id");
    header("Location: master_sales.php");
    exit();
}

/* =========================
   DATA USER (SALES)
=========================*/
$user = $conn->query("
    SELECT userid,username
    FROM me
    WHERE aktif='Y' OR aktif IS NULL
    ORDER BY username
");

/* =========================
   DATA GUDANG
=========================*/
$gudang = $conn->query("
    SELECT * FROM master_gudang
    ORDER BY nama_gudang
");

/* =========================
   DATA COA KAS
=========================*/
$coakas = $conn->query("
    SELECT account_code, account_name FROM coa 
    WHERE account_code LIKE '111%'
    ORDER BY account_code
");

/* =========================
   DATA SALES
=========================*/
$data=$conn->query("
    SELECT 
        s.*,
        u.username,
        g.nama_gudang
    FROM master_sales s
    JOIN me u ON s.userid=u.userid
    JOIN master_gudang g ON s.id_gudang=g.id_gudang
    ORDER BY s.id_sales DESC
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Master Sales</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body{font-family:Arial;background:#f4f6f9;}

.container{
    width:1100px;
    margin:30px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 4px 15px rgba(0,0,0,0.15);
}
.home-btn{
    position:fixed;
    top:15px;
    left:15px;
    font-size:24px;
    color:black;
    z-index:999;
}

input,select,textarea{
    width:100%;
    padding:8px;
    margin:5px 0;
}

button{
    background:#28a745;
    color:white;
    border:none;
    padding:10px 15px;
    cursor:pointer;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
}

th,td{
    border:1px solid #ddd;
    padding:8px;
}

th{
    background:#343a40;
    color:white;
}

.hapus{color:red;text-decoration:none;}
</style>
</head>

<body>
<a href="home.php" class="home-btn">
    <i class="fa-solid fa-house"></i>
</a>

<div class="container">

<h2><i class="fa-solid fa-user-tie"></i> Master Sales</h2>

<?php if($msg) echo "<p>$msg</p>"; ?>

<!-- ================= FORM ================= -->

<form method="POST">

<label>Pilih Sales (User)</label>
<select name="userid" required>
<option value="">-- Pilih User --</option>
<?php while($u=$user->fetch_assoc()){ ?>
<option value="<?= $u['userid']; ?>">
<?= $u['username']; ?>
</option>
<?php } ?>
</select>

<label>Kode Sales</label>
<input type="text" name="kode_sales" required>

<label>Pilih Gudang</label>
<select name="id_gudang" required>
<option value="">-- Pilih Gudang --</option>
<?php while($g=$gudang->fetch_assoc()){ ?>
<option value="<?= $g['id_gudang']; ?>">
<?= $g['nama_gudang']; ?>
</option>
<?php } ?>
</select>

<label>No HP</label>
<input type="text" name="no_hp">

<label>COA Kas Sales</label>
<select name="coa_kas" required>
<option value="">-- Pilih COA Kas --</option>
<?php while($c=$coakas->fetch_assoc()){ ?>
<option value="<?= $c['account_code']; ?>">
<?= $c['account_code']; ?> - <?= $c['account_name']; ?>
</option>
<?php } ?>
</select>

<label>Alamat</label>
<textarea name="alamat"></textarea>

<button type="submit" name="simpan">
<i class="fa-solid fa-save"></i> Simpan
</button>

</form>

<!-- ================= TABLE ================= -->

<table>

<tr>
<th>Kode</th>
<th>Nama Sales</th>
<th>Gudang</th>
<th>No HP</th>
<th>COA Kas</th>
<th>Alamat</th>
<th>Aksi</th>
</tr>

<?php while($row=$data->fetch_assoc()){ ?>

<tr>
<td><?= $row['kode_sales']; ?></td>
<td><?= $row['username']; ?></td>
<td><?= $row['nama_gudang']; ?></td>
<td><?= $row['no_hp']; ?></td>
<td><?= $row['coa_kas']; ?></td>
<td><?= $row['alamat']; ?></td>
<td>
<a class="hapus"
onclick="return confirm('Hapus data?')"
href="?hapus=<?= $row['id_sales']; ?>">
<i class="fa-solid fa-trash"></i>
</a>
</td>
</tr>

<?php } ?>

</table>

</div>

</body>
</html>
