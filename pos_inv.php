<?php
require_once 'config1.php';

$id_gudang = isset($_GET['id_gudang']) ? (int)$_GET['id_gudang'] : 0;
$jabatan = $_SESSION['jabatan'] ?? null;
$userid = $_SESSION['userid'] ?? null;

// Validasi akses sama seperti pos.php
$is_authorized = false;
if ($_SESSION['location'] === 'HO' || $_SESSION['location'] === 'HO1' || $_SESSION['bagian'] === 'owner') {
    $is_authorized = true;
} else if ($id_gudang > 0) {
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM master_sales WHERE userid = ? AND id_gudang = ?");
    $stmt_check->bind_param("ii", $userid, $id_gudang);
    $stmt_check->execute();
    $stmt_check->bind_result($is_sales);
    $stmt_check->fetch();
    $stmt_check->close();
    if ($is_sales > 0) {
        $is_authorized = true;
    }
}

if (!$is_authorized) {
    die("<h2 style='text-align:center; color:red; margin-top:50px;'>AKSES DITOLAK: Halaman POS INV hanya bisa diakses oleh akun yang berwenang.</h2><div style='text-align:center;'><a href='home.php'>Kembali ke Home</a></div>");
}

$username1 = $_SESSION['username'];

// Untuk validasi diskon (sama seperti pos.php)
$sql = "SELECT username, password FROM me WHERE jabatan IN ('1', 'kacab', 'owner') AND location IN ('HO1','HO')";
$result = $conn->query($sql);
$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[$row['username']] = $row['password'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    $username = $input['username'];
    $password = $input['password'];
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        echo json_encode(["valid" => true]);
    } else {
        echo json_encode(["valid" => false]);
    }
    exit();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>POS Inventory</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:5px}
h2{text-align:center;color:#333;margin-bottom:20px}
label{display:block;margin-bottom:8px;font-weight:bold}
input[type="text"],input[type="number"],input[type="datetime-local"]{width:100%;padding:5px;border:1px solid #ccc;border-radius:4px;margin:0}
input[type="submit"]{background:#4CAF50;color:#fff;border:none;padding:12px 20px;border-radius:4px;cursor:pointer}
input[type="submit"]:hover{background:#45a049}
table{width:100%;border-collapse:collapse;margin-top:20px}
th,td{padding:2px;border:1px solid #ccc;text-align:right}
th{background:#f2f2f2;text-align:center}
.home-icon1{position:absolute;left:0;top:0;padding-left:10px;color:maroon;font-size:24px}
.left-icon{position:absolute;right:0;top:0;padding-right:10px;color:maroon;font-size:24px}
.item{margin-bottom:10px}
.suggestions{position:absolute;background:#fff;border:1px solid #ccc;z-index:10;max-height:150px;overflow-y:auto}
.suggestion-item{padding:5px;cursor:pointer}
.suggestion-item:hover{background:#eee}
.total-container{text-align:right;margin-top:20px;border-top:2px solid #4CAF50;padding-top:10px}
.total-container p{font-weight:bold}
.diskon-container{display:flex;align-items:center;gap:10px;margin-top:10px}
.diskon-container label{flex:1;text-align:right}
.diskon-container input{flex:2;padding:5px;border:1px solid #ccc;border-radius:4px;max-width:150px}
/* Badge INV di header */
.badge-inv{display:inline-block;background:#e74c3c;color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;margin-left:8px;vertical-align:middle;letter-spacing:0.5px;}
</style>
</head>
<body>
<div class="table-container">
<a href="home.php" class="home-icon1"><i class="fas fa-home"></i></a>
<a href="home.php" class="left-icon"><i class="fa-solid fa-circle-left"></i></a>

<div class="container">
<h2>Point of Sale <span class="badge-inv">INV</span></h2>
<h1>Sales: <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

<form id="myForm" method="POST" action="simpan_penjualan.php" onsubmit="return false;">
    <div style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <label>Tipe Penjualan:</label>
        <div style="display: flex; gap: 20px; align-items: center;">
            <label style="font-weight: normal; margin: 0;"><input type="radio" name="jenis_penjualan" value="grosir" checked onchange="toggleSalesType()"> Grosir (Harga Normal)</label>
            <label style="font-weight: normal; margin: 0;"><input type="radio" name="jenis_penjualan" value="retail" onchange="toggleSalesType()"> Retail / Eceran (Harga Diskon)</label>
        </div>
    </div>

    <label for="tanggal_transaksi">Tanggal Transaksi:</label>
    <input type="datetime-local" name="tanggal_transaksi" id="tanggal_transaksi" readonly required>
    
    <div id="customer_container">
        <label>Pelanggan:</label>
        <div style="display: flex; gap: 10px;">
            <input type="text" id="cust" name="cust" placeholder="Kode Pelanggan" value="0" readonly required>
            <input type="text" id="cust_name" name="cust_name" placeholder="Nama Pelanggan" value="Umum" readonly required>
            <button type="button" id="btn_pilih_cust" onclick="openCustomerPopup()" disabled style="padding: 10px; cursor: not-allowed; opacity: 0.5;">Pilih Pelanggan</button>
        </div>
    </div>
    <br>
    <br><br>
    <label for="po">Nomor PO/Kontrak:</label>
    <input type="text" id="po" name="po" placeholder="Kosongkan jika tidak ada">
    <input type="hidden" name="id_gudang" id="id_gudang" value="<?php echo htmlspecialchars((string)$id_gudang); ?>">
    <input type="hidden" name="total_dpp" id="total_dpp" value="0">
    <input type="hidden" name="diskon" id="hidden_diskon" value="0">
    <input type="hidden" name="total_dpp_setelah_diskon" id="total_dpp_setelah_diskon" value="0">
    <input type="hidden" name="total_ppn" id="total_ppn" value="0">
    <input type="hidden" name="total_harga_termasuk_ppn" id="total_harga_termasuk_ppn" value="0">

    <table>
        <thead>
            <tr>
                <!-- KOLOM STOK "TERSEDIA" SENGAJA TIDAK DITAMPILKAN sesuai instruksi -->
                <th>Kode Barang</th><th>Nama Barang</th><th>Jumlah</th><th>Revisi Harga</th><th>Harga</th><th>Harga Total</th><th>Garansi</th><th>Bulan</th><th>No seri</th><th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <tr class="item">
                <td><input type="text" name="kode_b[]" onkeyup="getBarang(this.value,this)" required><div class="suggestions"></div></td>
                <td><input type="text" name="nama_b[]" readonly required></td>
                <!-- stok disimpan di hidden field, tidak ditampilkan ke user -->
                <input type="hidden" name="stok[]">
                <td><input type="number" name="jumlah_k[]" min="0" value="0" oninput="calculatePPN(this);updateTotals();" required></td>
                <td><input type="number" name="hargajual[]" class="hargajual" onchange="hitungDariHargaJual(this)"></td>
                <td><input type="text" name="harga_k2[]" id="harga_k2" readonly required></td>
                <td><input type="text" name="hargat_k[]" readonly required></td>
                <td>
                    <select name="garansi[]" required>
                        <option value="none">None</option>
                        <option value="pabrik">Pabrik</option>
                        <option value="toko">Toko</option>
                    </select>
                </td>
                <td><input type="number" name="bulan[]"></td>
                <td><input type="text" name="noseri[]"></td>
                <td>
                    <button type="button" onclick="removeItem(this)"><i class="fas fa-trash"></i></button>
                    <input type="hidden" name="username1" value="<?php echo htmlspecialchars($username1); ?>">
                    <input type="hidden" name="harga_k[]" required>
                    <input type="hidden" name="harga_k1[]" readonly required>
                    <input type="hidden" name="ppn_k[]" readonly required>
                    <input type="hidden" name="hargapack[]">
                    <input type="hidden" name="qpack[]">
                    <input type="hidden" name="fp" id="fp" value="1">
                </td>
            </tr>
        </tbody>
    </table>

    <button type="button" onclick="addItem()">Tambah Item</button>

    <div class="total-container">
        <p>Total Dasar (DPP): <span id="totalDPP">0</span></p>
        <p>PPN: <span id="totalPPN_display">0</span></p>
        <p>Diskon: <span id="totalDiskon">0</span></p>
        <p style="font-size: 1.2em; font-weight: bold; color: #d9534f;">Grand Total Dibayar: <span id="totalGrand">0</span></p>
    </div>

<input type="submit" id="submitBtn" value="Simpan Transaksi" onclick="handleSubmit()">
</form>
</div>

<div class="diskon-container">
    <label for="diskon">Diskon:</label>
    <input type="number" name="diskon" id="diskon" min="0" value="0">
    <button type="button" onclick="validateDiskon()">Terapkan Diskon</button>
</div>
</div>

<script>
// ============================================================
// SUBMIT HANDLER — cek stok, tampilkan warning jika over,
// tapi bisa tetap dilanjutkan setelah user konfirmasi
// ============================================================
let isSubmitting = false;

async function handleSubmit() {
    if (isSubmitting) return;

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.value = 'Memeriksa...';

    const items = document.querySelectorAll('.item');
    const idGudang = document.getElementById('id_gudang').value;

    let overStockMessages = [];
    let validationErrors = [];

    try {
        for (const item of items) {
            const kodeBarang = item.querySelector('input[name="kode_b[]"]').value;
            const jumlahInput = parseInt(item.querySelector('input[name="jumlah_k[]"]').value) || 0;

            // Validasi garansi
            const garansi = item.querySelector('[name="garansi[]"]').value;
            const bulan = item.querySelector('[name="bulan[]"]').value;
            const noseri = item.querySelector('[name="noseri[]"]').value;

            if (garansi !== 'none') {
                if (!bulan || parseInt(bulan) <= 0) {
                    validationErrors.push(`Barang ${kodeBarang}: Bulan garansi wajib diisi!`);
                    break;
                }
                if (!noseri || noseri.trim() === '') {
                    validationErrors.push(`Barang ${kodeBarang}: No seri wajib diisi!`);
                    break;
                }
            }

            // Cek stok dari server (stok tidak ditampilkan ke user, tapi tetap dicek)
            if (kodeBarang) {
                const response = await fetch(`cek_stok.php?kode_b=${encodeURIComponent(kodeBarang)}&id_gudang=${idGudang}`);
                const data = await response.json();
                const remainingStock = data.total_stock - jumlahInput;

                if (remainingStock < 0) {
                    overStockMessages.push(`• ${kodeBarang}: kekurangan ${Math.abs(remainingStock)} unit`);
                }
            }
        }

        // Jika ada error validasi wajib, hentikan
        if (validationErrors.length > 0) {
            Swal.fire({
                icon: 'error',
                title: 'Validasi Gagal',
                text: validationErrors[0]
            });
            submitBtn.disabled = false;
            submitBtn.value = 'Simpan Transaksi';
            return;
        }

        // Jika ada over-stok, tampilkan WARNING tapi bisa dilanjutkan
        if (overStockMessages.length > 0) {
            const result = await Swal.fire({
                icon: 'warning',
                title: 'Peringatan: Stok Tidak Mencukupi',
                html: `<div style="text-align:left;">Barang berikut melebihi stok tersedia:<br><br>
                       <strong>${overStockMessages.join('<br>')}</strong>
                       <br><br>Apakah Anda yakin ingin melanjutkan?</div>`,
                showCancelButton: true,
                confirmButtonColor: '#e67e22',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check"></i> Ya, Lanjutkan',
                cancelButtonText: '<i class="fas fa-times"></i> Batal'
            });

            if (!result.isConfirmed) {
                // User pilih batal
                submitBtn.disabled = false;
                submitBtn.value = 'Simpan Transaksi';
                return;
            }
        }

        // Validasi harga tidak 0
        const hargaEl = document.getElementById('harga_k2');
        if (hargaEl && (parseInt(hargaEl.value) === 0 || hargaEl.value === '')) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Harga tidak boleh 0!' });
            submitBtn.disabled = false;
            submitBtn.value = 'Simpan Transaksi';
            return;
        }

        // Semua OK (atau user sudah konfirmasi over-stok) → submit
        isSubmitting = true;
        document.getElementById('myForm').submit();

    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Terjadi kesalahan saat validasi stok.' });
        submitBtn.disabled = false;
        submitBtn.value = 'Simpan Transaksi';
    }
}

// ============================================================
// FUNGSI UMUM (sama dengan pos.php)
// ============================================================
function getCurrentDateTime(){
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}T${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}`;
}
window.onload = () => document.getElementById('tanggal_transaksi').value = getCurrentDateTime();

function addItem(){
    const tableBody = document.querySelector('tbody');
    const newRow = document.createElement('tr');
    newRow.classList.add('item');
    newRow.innerHTML = `
        <td><input type="text" name="kode_b[]" onkeyup="getBarang(this.value,this)" required><div class="suggestions"></div></td>
        <td><input type="text" name="nama_b[]" readonly required></td>
        <input type="hidden" name="stok[]">
        <td><input type="number" name="jumlah_k[]" min="0" value="0" oninput="calculatePPN(this);updateTotals();" required></td>
        <td><input type="number" name="hargajual[]" class="hargajual" onchange="hitungDariHargaJual(this)"></td>
        <td><input type="text" name="harga_k2[]" readonly required></td>
        <td><input type="text" name="hargat_k[]" readonly required></td>
        <td>
            <select name="garansi[]" required>
                <option value="none">None</option>
                <option value="pabrik">Pabrik</option>
                <option value="toko">Toko</option>
            </select>
        </td>
        <td><input type="number" name="bulan[]"></td>
        <td><input type="text" name="noseri[]"></td>
        <td><button type="button" onclick="removeItem(this)"><i class="fas fa-trash"></i></button>
            <input type="hidden" name="harga_k[]" required>
            <input type="hidden" name="harga_k1[]" readonly required>
            <input type="hidden" name="ppn_k[]" readonly required>
            <input type="hidden" name="hargapack[]">
            <input type="hidden" name="qpack[]">
        </td>`;
    tableBody.appendChild(newRow);
}

function removeItem(btn){ btn.closest('tr').remove(); updateTotals(); }

function getBarang(kode_b, el){
    if(!kode_b){ el.closest('.item').querySelector('.suggestions').innerHTML=""; return; }
    const idGudang = document.getElementById('id_gudang').value;
    const isRetail = document.querySelector('input[name="jenis_penjualan"]:checked').value === 'retail';
    const endpoint = isRetail ? 'search_barang_retail.php' : 'search_barang.php';
    fetch(`${endpoint}?kode_b=${encodeURIComponent(kode_b)}&id_gudang=${idGudang}`)
    .then(r => r.json())
    .then(suggestions => {
        const suggestionBox = el.closest('.item').querySelector('.suggestions');
        suggestionBox.innerHTML = "";
        let exactMatch = false;
        suggestions.forEach(s => {
            const div = document.createElement("div");
            div.textContent = `${s.kode_b} - ${s.nama_b}`;
            div.classList.add("suggestion-item");
            div.onclick = function(){ applyBarang(el, s); suggestionBox.innerHTML=""; };
            suggestionBox.appendChild(div);
            if(s.kode_b === kode_b){ applyBarang(el, s); exactMatch = true; }
        });
        if(exactMatch){ suggestionBox.innerHTML=""; }
    });
}

function applyBarang(el, s){
    el.value = s.kode_b;
    const item = el.closest('.item');
    item.querySelector('[name="nama_b[]"]').value = s.nama_b;
    // Stok disimpan di hidden field, tidak ditampilkan
    item.querySelector('[name="stok[]"]').value = s.stok;
    item.querySelector('[name="harga_k[]"]').value = s.harga_b;
    item.querySelector('[name="ppn_k[]"]').value = s.ppn_b || 0;
    item.querySelector('[name="ppn_k[]"]').dataset.ppn = s.ppn_b || 0;
    item.querySelector('[name="hargapack[]"]').value = s.hargapack || 0;
    item.querySelector('[name="qpack[]"]').value = s.qpack || 0;
    calculatePPN(item.querySelector('[name="jumlah_k[]"]'));
    updateTotals();
}

function calculatePPN(el){
    const item = el.closest('.item');
    const harga_b = parseFloat(item.querySelector('[name="harga_k[]"]').value) || 0;
    const jumlah_k = parseFloat(el.value) || 0;
    const ppn_k = parseFloat((item.querySelector('[name="ppn_k[]"]').dataset.ppn || "0")) || 0;
    const hargapack = parseFloat(item.querySelector('[name="hargapack[]"]').value) || 0;
    const qpack = parseFloat(item.querySelector('[name="qpack[]"]').value) || 0;

    let harga_k1 = 0, harga_k2 = 0, hargat_k = 0;

    if (qpack > 0 && hargapack > 0 && (jumlah_k / qpack) >= 1) {
        const bossJumlahPack = jumlah_k / qpack;
        harga_k2 = hargapack;
        harga_k1 = hargapack + ppn_k;
        hargat_k = harga_k1 * bossJumlahPack;
    } else {
        harga_k1 = harga_b + ppn_k;
        harga_k2 = harga_b;
        hargat_k = harga_k1 * jumlah_k;
    }

    item.querySelector('[name="harga_k1[]"]').value = harga_k1.toFixed(2);
    item.querySelector('[name="harga_k2[]"]').value = harga_k2.toFixed(2);
    item.querySelector('[name="hargat_k[]"]').value = hargat_k.toFixed(2);
    item.querySelector('[name="hargajual[]"]').value = '';
}

function hitungDariHargaJual(el){
    const item = el.closest('.item');
    const hargajual = parseFloat(el.value) || 0;
    const qty = parseFloat(item.querySelector('[name="jumlah_k[]"]').value) || 0;
    const harga_b = parseFloat(item.querySelector('[name="harga_k[]"]').value) || 0;

    if (hargajual > 0 && hargajual < harga_b) {
        alert("Revisi harga tidak boleh lebih rendah dari Harga dasar!");
        el.value = '';
        calculatePPN(item.querySelector('[name="jumlah_k[]"]'));
        updateTotals();
        return;
    }
    if (hargajual <= 0) { calculatePPN(item.querySelector('[name="jumlah_k[]"]')); return; }

    const grandTotal = hargajual * qty;
    item.querySelector('[name="harga_k1[]"]').value = hargajual.toFixed(2);
    item.querySelector('[name="harga_k2[]"]').value = hargajual.toFixed(2);
    item.querySelector('[name="ppn_k[]"]').value = (0).toFixed(2);
    item.querySelector('[name="hargat_k[]"]').value = grandTotal.toFixed(2);
    updateTotals();
}

function formatRibuan(angka){ return Number(angka).toLocaleString('id-ID'); }

function updateTotals(){
    const items = document.querySelectorAll('.item');
    let totalPPN = 0, totalHarga = 0;
    const totalDiskon = parseFloat(document.getElementById('diskon')?.value) || 0;

    items.forEach(item => {
        let ppnVal = item.querySelector('[name="ppn_k[]"]').value || "0";
        const ppn_k = parseFloat(ppnVal.toString().replace(/,/g,'')) || 0;
        const qty = parseFloat(item.querySelector('[name="jumlah_k[]"]').value) || 0;
        const dpp_item = parseFloat(item.querySelector('[name="harga_k2[]"]').value) || 0;
        totalPPN += ppn_k * qty;
        totalHarga += dpp_item * qty;
    });

    const totalSetelahDiskon = Math.max(totalHarga - totalDiskon, 0);
    const totalPPNDiskon = Math.max(totalPPN - (totalDiskon * 0.11), 0);
    const totalHargaTermasukPPN = totalSetelahDiskon + totalPPNDiskon;

    document.querySelector('.total-container').innerHTML = `
        <p>Total Dasar (DPP): ${formatRibuan(totalHarga)}</p>
        <p>PPN: ${formatRibuan(totalPPN)}</p>
        <p style="font-size: 1.2em; font-weight: bold; color: #d9534f;">Grand Total Dibayar: ${formatRibuan(totalHargaTermasukPPN)}</p>
    `;

    document.getElementById('total_dpp').value = totalHarga.toFixed(2);
    document.getElementById('hidden_diskon').value = totalDiskon.toFixed(2);
    document.getElementById('total_dpp_setelah_diskon').value = totalSetelahDiskon.toFixed(2);
    document.getElementById('total_ppn').value = totalPPNDiskon.toFixed(2);
    document.getElementById('total_harga_termasuk_ppn').value = totalHargaTermasukPPN.toFixed(2);
}

let authorizedUsers = <?php echo json_encode($users); ?>;
function validateDiskon(){
    const diskonInput = document.getElementById('diskon');
    const diskonValue = parseFloat(diskonInput.value) || 0;
    if(diskonValue > 0){
        const username = prompt("Masukkan Username:");
        const password = prompt("Masukkan Password:");
        if(!username || !password){ alert("Username dan password harus diisi!"); diskonInput.value = 0; updateTotals(); return false; }
        fetch("", {method:"POST", headers:{"Content-Type":"application/json"}, body:JSON.stringify({username, password})})
        .then(r => r.json()).then(data => {
            if(data.valid){ alert("Diskon diterapkan!"); updateTotals(); }
            else{ alert("Akses ditolak! Diskon tidak diterapkan. Hubungi kacab."); diskonInput.value = 0; updateTotals(); }
        });
    }
    return true;
}

function openCustomerPopup(){ window.open('cust.php', 'Pilih Pelanggan', 'width=800,height=600'); }
function setCustomerCode(kode, nama){
    document.getElementById('cust').value = kode;
    document.getElementById('cust_name').value = nama;
}

function toggleSalesType(){
    const isRetail = document.querySelector('input[name="jenis_penjualan"]:checked').value === 'retail';
    const custInput = document.getElementById('cust');
    const custNameInput = document.getElementById('cust_name');
    const btnPilih = document.getElementById('btn_pilih_cust');
    if(isRetail){
        custInput.value = ''; custInput.readOnly = false;
        custNameInput.value = ''; custNameInput.readOnly = false;
        btnPilih.disabled = false; btnPilih.style.cursor = 'pointer'; btnPilih.style.opacity = '1';
    } else {
        custInput.value = '0'; custInput.readOnly = true;
        custNameInput.value = 'Umum'; custNameInput.readOnly = true;
        btnPilih.disabled = true; btnPilih.style.cursor = 'not-allowed'; btnPilih.style.opacity = '0.5';
    }
    const items = document.querySelectorAll('.item');
    items.forEach((item, index) => {
        if(index === 0){
            item.querySelector('[name="kode_b[]"]').value = '';
            item.querySelector('[name="nama_b[]"]').value = '';
            item.querySelector('[name="stok[]"]').value = '';
            item.querySelector('[name="jumlah_k[]"]').value = '0';
            item.querySelector('[name="harga_k2[]"]').value = '';
            item.querySelector('[name="hargat_k[]"]').value = '';
            item.querySelector('[name="harga_k[]"]').value = '';
        } else {
            item.remove();
        }
    });
    updateTotals();
}

// Inisialisasi awal
toggleSalesType();
</script>
</body>
</html>
