<?php
session_start();

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    // Jika belum login, redirect ke halaman login
    header("Location: index.html");
    exit();
}

// Periksa apakah session location adalah 'HO' atau 'HO1'
if ($_SESSION['location'] !== 'HO' && $_SESSION['location'] !== 'HO1') {
    // Jika lokasi bukan 'HO' atau 'HO1', redirect ke halaman login
    header("Location: index.html");
    exit();
}
$username1 = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tampilan Point of Sale</title>
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 5px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
       
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"],
        input[type="datetime-local"] {
            width: 100%;
            padding: 5px;
            margin-left: 0px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-wrap: wrap;
               margin: 0;
        }
        input[type="submit"] {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
               display: flex;
        flex-wrap: wrap;
        }
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 1px;
            border: 1px solid #ccc;
            text-align: right; /* Ubah menjadi rata kanan */
        }
        th {
            background-color: #f2f2f2;
            text-align: center; /* Judul tabel tetap di tengah */
            font-weight: bold;
        }
        
   .home-icon1 {
        position: absolute;
        left: 0;
        top: 0;
        padding-left: 10px;
         color: maroon;
        font-size: 24px;
    }

    .left-icon {
        position: absolute;
        right: 0;
        top: 0;
        padding-right: 10px;
 color: maroon;
        font-size: 24px;
    }
        .item {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
             flex: 1 1 100px;
        }
        .suggestions {
            position: absolute;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-height: 150px;
            overflow-y: auto;
            z-index: 10;
        }
        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            transition: background-color 0.2s ease-in-out;
        }
        .suggestion-item:hover {
            background-color: #e3e3e3;
        }
        .total-container {
            text-align: right; /* Agar total rata kanan */
            margin-top: 20px;
            border-top: 2px solid #4CAF50;
            padding-top: 10px;
        }
        .total-container p {
            font-size: 1.2em; /* Ukuran font lebih besar untuk total */
            margin: 5px 0; /* Jarak antar baris */
            font-weight: bold; /* Font bold untuk penekanan */
        }

        /* Responsif untuk perangkat mobile */
        @media (max-width: 600px) {
            h2 {
                font-size: 1.5em; /* Ukuran font lebih kecil untuk judul */
            }
            input[type="submit"] {
                font-size: 8px; /* Ukuran font tombol lebih kecil */
                padding: 10px; /* Padding tombol lebih kecil */
            }
                    @media (max-width: 600px) {
input {
    font-size: 6px; /* Ukuran font lebih kecil */
    padding: 0px; /* Padding lebih kecil */
    margin: 0; /* Menghapus margin */
    border: 0px solid #ccc; /* Menjaga border untuk tetap terlihat */
    border-radius: 3px; /* Mengatur border-radius */
    text-align: left; /* Teks rata kiri */
    white-space: none; /* Membungkus teks jika tidak cukup ruang */
    width: flex; /* Lebar menyesuaikan konten */
    display: flex;
    flex-wrap: wrap;
   
     
}


            }
            
            
             
            table {
                font-size: 8px; /* Ukuran font tabel lebih kecil */
            }
           
    text-align: center; /* Rata tengah untuk semua sel */
            }
            
        }
    </style>
    <script>
        function getCurrentDateTime() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hours}:${minutes}`;
        }

        window.onload = function() {
            document.getElementById('tanggal_transaksi').value = getCurrentDateTime();
        };

        function addItem() {
            var itemContainer = document.getElementById("itemContainer").getElementsByTagName('tbody')[0];
            var newRow = document.createElement("tr");
            newRow.classList.add("item");
            newRow.innerHTML = `
                <td>
                    <input type="text" name="kode_b[]" onkeyup="getBarang(this.value, this)" required>
                    <div class="suggestions"></div>
                </td>
                <td>
                    <input type="text" name="nama_b[]" readonly required>
                </td>
                <td>
                    <input type="number" name="jumlah_m[]" min="0" value="0" oninput="calculatePPN(this); updateTotals();" required>
                </td>
                <td>
                    <input type="file" id="gambar_b" name="gambar_b[]" accept="image/*" onchange="displayFileExtension(this)">
                    <div id="fileInfo" style="color: green; display: none;"></div>
                </td>
                <td>
                    <button type="button" onclick="removeItem(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            itemContainer.appendChild(newRow);
        }

        function removeItem(button) {
            var itemRow = button.closest('tr');
            itemRow.remove();
            updateTotals(); // Perbarui total setelah menghapus item
        }

         function getBarang(kode_b, el) {
            if (kode_b.length === 0) {
                el.closest('.item').querySelector('.suggestions').innerHTML = "";
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    var suggestions = JSON.parse(this.responseText);
                    var suggestionBox = el.closest('.item').querySelector('.suggestions');
                    suggestionBox.innerHTML = "";

                    suggestions.forEach(function(suggestion) {
                        var div = document.createElement("div");
                        div.innerHTML = suggestion.kode_b + " - " + suggestion.nama_b;
                        div.classList.add("suggestion-item");
                        div.onclick = function() {
                            el.value = suggestion.kode_b;
                            el.closest('.item').querySelector('[name="nama_b[]"]').value = suggestion.nama_b;
                           // Kalkulasi ulang setelah memilih barang
                            suggestionBox.innerHTML = ""; // Hapus saran
                            suggestionBox.style.display = "none"; // Sembunyikan kotak saran
                            updateTotals(); // Perbarui total setelah memilih barang
                        };
                        suggestionBox.appendChild(div);
                    });

                    // Tampilkan kotak saran jika ada saran
                    suggestionBox.style.display = suggestions.length > 0 ? "block" : "none";
                }
            };
            xhr.open("GET", "search_barang.php?kode_b=" + kode_b, true);
            xhr.send();
        }

        function calculatePPN(el) {
            var item = el.closest('.item');
            var harga_k = parseFloat(item.querySelector('[name="harga_k[]"]').value) || 0;
            var jumlah_k = parseFloat(el.value) || 0;

            var ppn_k = (harga_k * 0.11) * jumlah_k;
            var hargat_k = (harga_k * 1.11) * jumlah_k;
            var harga_k1 = harga_k * 1.11; 
            var harga_k2 = harga_k;

            item.querySelector('[name="harga_k1[]"]').value = formatRibuan(harga_k1);
            item.querySelector('[name="harga_k2[]"]').value = formatRibuan(harga_k2);
            item.querySelector('[name="ppn_k[]"]').value = formatRibuan(ppn_k);
            item.querySelector('[name="hargat_k[]"]').value = formatRibuan(hargat_k);
        }
  function openSupPopup() {
    // Buka halaman cust.php sebagai popup
    window.open('sup.php', 'Pilih Supplyer', 'width=800,height=600');
}

// Fungsi untuk menerima kode dari popup
function setsSupCode(kode, nama) {
    document.getElementById('sup').value = kode;
    document.getElementById('sup_name').value = nama; // Misalnya, untuk menyimpan nama pelanggan
}

        
    </script>
</head>
<body>
    <div class="table-container">
    <a href="home.php" class="home-icon1">
        <i class="fas fa-home"></i>
    </a>
    <a href="home.php" class="left-icon">
        <i class="fa-solid fa-circle-left"></i>
    </a>
    <h2>Barang masuk</h2>
    <form action="simpan_masuk.php" method="POST" enctype="multipart/form-data">
        <label for="tanggal_transaksi">Tanggal Transaksi:</label>
        <input type="datetime-local" id="tanggal_transaksi" name="tanggal_transaksi" required>
         <input type="text" id="sup" name="sup" placeholder="Kode supplyer" readonly>
<input type="text" id="sup_name" name="sup_name" placeholder="Nama Supplyer" readonly>
<button type="button" onclick="openSupPopup()">Pilih supplyer</button>
<input type="hidden" name="username1" value="<?php echo htmlspecialchars($username1); ?>">
<br>
<br>
        <label for="rek">Nomor Surat Jalan:</label>
<input type="text" id="sj" name="sj" required>
        <div id="itemContainer">
            <table>
                <thead>
                    <tr>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                       
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="item">
                        <td>
                            <input type="text" name="kode_b[]" onkeyup="getBarang(this.value, this)" required>
                            <div class="suggestions"></div>
                        </td>
                        <td>
                            <input type="text" name="nama_b[]" readonly required>
                        </td>
                        <td>
                            <input type="number" name="jumlah_m[]" min="0" value="0" oninput="calculatePPN(this); updateTotals();" required>
                        </td>
                        
                        <td>
                            <button type="button" onclick="removeItem(this)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <button type="button" onclick="addItem()">Tambah Item</button>
        </div>
       <br>
        <input type="submit" value="Simpan Transaksi">
    </form>
</body>
<script>
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
    document.addEventListener('keydown', function(e) {
        if (e.keyCode == 123 || (e.ctrlKey && e.shiftKey && e.keyCode == 'I'.charCodeAt(0)) || (e.ctrlKey && e.shiftKey && e.keyCode == 'C'.charCodeAt(0)) || (e.ctrlKey && e.keyCode == 'U'.charCodeAt(0))) {
            e.preventDefault();
        }
    });
</script>
</html>
