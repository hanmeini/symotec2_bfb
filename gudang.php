<?php

require 'config1.php';

/* ===============================
   SIMPAN / UPDATE
================================ */

if(isset($_POST['simpan'])){

    $id     = $_POST['id_gudang'] ?? '';
    $nama   = $_POST['nama_gudang'];
    $alamat = $_POST['alamat'];
    $telp   = $_POST['telp'];
    $sales  = $_POST['sales'];

    // INSERT
    if($id==""){
        $stmt=$conn->prepare("
            INSERT INTO master_gudang
            (nama_gudang,alamat,telp,sales)
            VALUES (?,?,?,?)
        ");
        $stmt->bind_param("ssss",$nama,$alamat,$telp,$sales);
    }
    // UPDATE
    else{
        $stmt=$conn->prepare("
            UPDATE master_gudang
            SET nama_gudang=?,
                alamat=?,
                telp=?,
                sales=?
            WHERE id_gudang=?
        ");
        $stmt->bind_param("ssssi",$nama,$alamat,$telp,$sales,$id);
    }

    $stmt->execute();
    header("Location:gudang.php");
    exit();
}

/* ===============================
   AMBIL DATA
================================ */

$data=[];
$q=$conn->query("SELECT * FROM master_gudang ORDER BY nama_gudang ASC");

while($row=$q->fetch_assoc()){
    $data[]=$row;
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Master Gudang</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
body{
    font-family:Arial;
    margin:30px;
    background:#f4f4f4;
}

.container{
    background:white;
    padding:20px;
    border-radius:10px;
}
.home-btn{
    position:fixed;
    top:15px;
    left:15px;
    font-size:24px;
    color:black;
    z-index:999;
}
table{
    border-collapse:collapse;
    width:100%;
    margin-top:20px;
}

th,td{
    border:1px solid #ccc;
    padding:8px;
}

th{
    background:#007bff;
    color:white;
}

input,textarea{
    width:100%;
    padding:7px;
    margin-bottom:10px;
}

button{
    padding:8px 15px;
    border:0;
    border-radius:5px;
    cursor:pointer;
    background:#007bff;
    color:white;
}

.edit-btn{
    background:#28a745;
    color:white;
    padding:5px 10px;
    border-radius:4px;
    text-decoration:none;
}
</style>
</head>

<body>
<a href="home.php" class="home-btn">
    <i class="fa-solid fa-house"></i>
</a>
<div class="container">

<h2>Master Gudang</h2>

<!-- ================= FORM (ADD + EDIT) ================= -->



<form method="POST" id="formGudang">

<input type="hidden" name="id_gudang" id="id_gudang">

Nama Gudang
<input type="text" name="nama_gudang" id="nama_gudang" required>

Alamat
<textarea name="alamat" id="alamat"></textarea>

Sales
<textarea name="sales" id="sales"></textarea>

Telp
<input type="text" name="telp" id="telp">

<button name="simpan" id="btnSubmit">
    Simpan Gudang
</button>

<button type="button"
onclick="resetForm()"
id="btnBatal"
style="display:none;background:#dc3545;">
Batal Edit
</button>

</form>

<hr>

<!-- ================= TABLE ================= -->

<table>
<tr>
<th>ID</th>
<th>Nama Gudang</th>
<th>Alamat</th>
<th>Sales</th>
<th>Telp</th>
<th>Aksi</th>
</tr>

<?php foreach($data as $d): ?>
<tr>

<td><?= htmlspecialchars($d['id_gudang']) ?></td>
<td><?= htmlspecialchars($d['nama_gudang']) ?></td>
<td><?= htmlspecialchars($d['alamat']) ?></td>
<td><?= htmlspecialchars($d['sales']) ?></td>
<td><?= htmlspecialchars($d['telp']) ?></td>

<td>
<a href="#" class="edit-btn"
onclick="editGudang(
<?= $d['id_gudang'] ?>,
`<?= addslashes($d['nama_gudang']) ?>`,
`<?= addslashes($d['alamat']) ?>`,
`<?= addslashes($d['sales']) ?>`,
`<?= addslashes($d['telp']) ?>`
); return false;">
<i class="fa-solid fa-pen"></i> Edit
</a>
</td>

</tr>
<?php endforeach; ?>

</table>

</div>

<!-- ================= JAVASCRIPT ================= -->

<script>

function editGudang(id,nama,alamat,sales,telp)
{
    // isi data ke form
    document.getElementById("id_gudang").value=id;
    document.getElementById("nama_gudang").value=nama;
    document.getElementById("alamat").value=alamat;
    document.getElementById("sales").value=sales;
    document.getElementById("telp").value=telp;

    // ubah mode edit
    document.getElementById("judulForm").innerText="Edit Gudang";
    document.getElementById("btnSubmit").innerText="Update Gudang";
    document.getElementById("btnBatal").style.display="inline-block";

    // scroll ke atas
    window.scrollTo({
        top:0,
        behavior:'smooth'
    });
}

function resetForm()
{
    document.getElementById("formGudang").reset();
    document.getElementById("id_gudang").value="";

    document.getElementById("judulForm").innerText="Tambah Gudang";
    document.getElementById("btnSubmit").innerText="Simpan Gudang";
    document.getElementById("btnBatal").style.display="none";
}

</script>

</body>
</html>
