<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'use_only_cookies'=> true,
    'use_strict_mode' => true,
]);

/* ================= VALIDASI REFERER ================= */




/* ================= VALIDASI LOGIN ================= */
if (!isset($_SESSION['username'])) {
    header("Location: index.html");
    exit();
}

/* ================= LOAD CONFIG ================= */
require_once 'config1.php';

/* ================= KONEKSI DATABASE ================= */






/* ================= AMBIL DATA CLOSE ================= */
$latestMonth = 0;
$latestYear  = 0;

$query  = "SELECT bulan, tahun FROM close ORDER BY tahun DESC, bulan DESC LIMIT 1";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $row         = $result->fetch_assoc();
    $latestMonth = (int)$row['bulan'];
    $latestYear  = (int)$row['tahun'];
}

/* ================= DEFAULT TANGGAL ================= */
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>INPUT CN AP</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
*{
    box-sizing:border-box;
}

body{
    margin:0;
    padding:0;
    font-family:Arial, sans-serif;
    background-image:linear-gradient(to right,#ea90e6,#6c6cc9);
    color:maroon;
}

.container{
    max-width:800px;
    margin:20px auto;
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.15);
}

h2{
    margin-top:0;
    text-align:center;
    color:#333;
}

label{
    display:block;
    margin-bottom:5px;
    font-weight:bold;
}

input[type="date"],
input[type="text"],
textarea,
select{
    width:100%;
    padding:10px;
    border:1px solid #ccc;
    border-radius:5px;
    margin-bottom:15px;
    font-size:14px;
}

textarea{
    resize:vertical;
}

button,
input[type="submit"]{
    padding:10px 15px;
    border:none;
    border-radius:5px;
    cursor:pointer;
    font-weight:bold;
}

input[type="submit"]{
    width:100%;
    background:#28a745;
    color:#fff;
    font-size:16px;
}

input[type="submit"]:hover{
    background:#218838;
}

.home-icon1,
.left-icon{
    position:fixed;
    top:10px;
    font-size:24px;
    color:white;
    z-index:999;
}

.home-icon1{
    left:10px;
}

.left-icon{
    right:10px;
}

.amount-input{
    text-align:right;
}

.info-box{
    background:#fff3cd;
    color:#856404;
    padding:10px;
    border-radius:5px;
    margin-bottom:15px;
    border:1px solid #ffeeba;
}

@media(max-width:768px){
    .container{
        margin:10px;
    }
}
</style>
</head>

<body>

<a href="home.php" class="home-icon1">
    <i class="fas fa-home"></i>
</a>

<a href="home.php" class="left-icon">
    <i class="fa-solid fa-circle-left"></i>
</a>

<div class="container">

    <h2>INPUT CN AP</h2>

    <div class="info-box">
        Pilih tanggal lebih besar dari periode closing terakhir.
    </div>

    <form 
        id="formJurnal"
        action="save_cnap.php"
        method="post"
        enctype="multipart/form-data"
        onsubmit="return validateForm();"
    >

        <!-- TANGGAL -->
        <label for="tanggal">Tanggal</label>

        <input 
            type="date"
            id="tanggal"
            name="tanggal"
            value="<?= htmlspecialchars($today) ?>"
            required
        >

        <!-- SUPPLIER -->
        <label for="kode">Vendor / Supplier</label>

        <select name="kode" id="kode" required>
            <option value="">-- Pilih Vendor / Supplier --</option>

            <?php
            $sql5 = "SELECT id, kode, nama FROM sup ORDER BY nama ASC";
            $result5 = $conn->query($sql5);

            if ($result5 && $result5->num_rows > 0) {

                while ($row = $result5->fetch_assoc()) {

                    $id   = htmlspecialchars($row['id']);
                    $nama = htmlspecialchars($row['nama']);

                    echo "<option value='{$id}'>{$nama}</option>";
                }
            }
            ?>
        </select>

        <!-- KETERANGAN -->
        <label for="description">Keterangan</label>

        <textarea
            name="description"
            id="description"
            rows="3"
            placeholder="Masukkan keterangan"
            required
        ></textarea>

        <!-- NILAI CN -->
        <label for="cn">Nilai CN</label>

        <input
            type="text"
            id="cn"
            name="cn"
            value="0"
            class="amount-input"
            required
            oninput="formatLargeNumber(this)"
            onfocus="removeFormatting(this)"
            onblur="applyFormatting(this)"
        >

        <input type="submit" value="Submit">

    </form>
</div>

<script>
/* ================= VALIDASI TANGGAL CLOSE ================= */
document.addEventListener("DOMContentLoaded", function () {

    let latestMonth = <?= $latestMonth ?>;
    let latestYear  = <?= $latestYear ?>;

    const dateInput = document.getElementById("tanggal");

    dateInput.addEventListener("change", function () {

        if (!this.value) return;

        let selectedDate  = new Date(this.value);
        let selectedMonth = selectedDate.getMonth() + 1;
        let selectedYear  = selectedDate.getFullYear();

        if (
            selectedYear < latestYear ||
            (
                selectedYear === latestYear &&
                selectedMonth <= latestMonth
            )
        ) {
            alert(
                "Tanggal tidak valid! " +
                "Periode sudah di-close."
            );

            this.value = "";
        }
    });

});

/* ================= FORMAT ANGKA ================= */
function formatLargeNumber(input) {

    let value = input.value.replace(/,/g, '');

    value = value.replace(/[^\d]/g, '');

    input.value = formatWithCommas(value);

}

function formatWithCommas(value) {

    return value.replace(/\B(?=(\d{3})+(?!\d))/g, ",");

}

function removeFormatting(input) {

    input.value = input.value.replace(/,/g, '');

}

function applyFormatting(input) {

    let value = input.value.replace(/,/g, '');

    if (value !== '' && !isNaN(value)) {
        input.value = formatWithCommas(value);
    }

}

/* ================= VALIDASI FORM ================= */
function validateForm() {

    const cn = document.getElementById('cn').value.replace(/,/g,'');

    if (
        cn === '' ||
        isNaN(cn) ||
        parseFloat(cn) <= 0
    ) {
        alert('Nilai CN harus lebih besar dari 0.');
        return false;
    }

    return confirm("Apakah Anda yakin data CN sudah benar?");
}
</script>

</body>
</html>