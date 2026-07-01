<?php

   // Ambil config
require_once 'config1.php';

// DB connections are now globally provided by config1.php ($conn and $pdo)

// Ambil kurs terbaru
try {
    $sqlKurs = "SELECT kurs, tanggal FROM kurs ORDER BY tanggal DESC LIMIT 1";
    $stmtKurs = $pdo->prepare($sqlKurs);
    $stmtKurs->execute();
    $resultKurs = $stmtKurs->fetch();

    $kurs = $resultKurs['kurs'] ?? 0;
    $tanggal = $resultKurs['tanggal'] ?? 'Data tidak tersedia';
} catch (PDOException $e) {
    die("Query gagal: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kalkulator Kurs USD ↔ IDR</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding: 20px;
        }
        .currency-calculator {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 320px;
            margin: auto;
        }
        .currency-calculator h3 {
            margin-bottom: 15px;
        }
        .currency-calculator label {
            display: block;
            font-weight: bold;
            margin-top: 10px;
            text-align: left;
        }
        .currency-calculator input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            text-align: right;
        }
        .currency-calculator p {
            font-weight: bold;
            margin-top: 15px;
            color: #333;
        }
    </style>
</head>
<body>

<div class="currency-calculator">
    <h3>Kalkulator Kurs USD ↔ IDR</h3>

    <p style="font-size: 14px; color: gray;">
        Kurs terbaru: <b>Rp <?= number_format($kurs, 0, ',', '.') ?></b> (<?= $tanggal ?>)
    </p>

    <label for="usd">USD sesuai RK:</label>
    <input type="text" id="usd" oninput="convertToIDR(); checkSelisih()" onfocus="removeFormatting(this)" onblur="applyFormatting(this)">
    
    <label for="idrb">Nilai IDR bank USD sesuai neraca:</label>
    <input type="text" id="idrb" oninput="checkSelisih()" onfocus="removeFormatting(this)" onblur="applyFormatting(this)">

    <label for="kurs">Kurs (IDR per USD):</label>
    <input type="text" id="kurs" value="<?= $kurs ?>" >

    <label for="idr">IDR auto IDR:</label>
    <input type="text" id="idr" oninput="convertToUSD(); checkSelisih()" onfocus="removeFormatting(this)" onblur="applyFormatting(this)">

    <p id="conversion-result">-</p>
    <p id="selisih-result">-</p>
</div>

<script>
    function convertToIDR() {
        let usd = parseFloat(document.getElementById("usd").value.replace(/,/g, '')) || 0;
        let kurs = parseFloat(document.getElementById("kurs").value.replace(/,/g, '')) || 0;

        if (usd > 0 && kurs > 0) {
            let idr = usd * kurs;
            document.getElementById("idr").value = formatNumber(idr);
            document.getElementById("conversion-result").innerText = usd + " USD = " + formatNumber(idr) + " IDR";
        }
    }

    function convertToUSD() {
        let idr = parseFloat(document.getElementById("idr").value.replace(/,/g, '')) || 0;
        let kurs = parseFloat(document.getElementById("kurs").value.replace(/,/g, '')) || 0;

        if (idr > 0 && kurs > 0) {
            let usd = idr / kurs;
            document.getElementById("usd").value = formatNumber(usd.toFixed(2));
            document.getElementById("conversion-result").innerText = formatNumber(idr) + " IDR = " + usd.toFixed(2) + " USD";
        }
    }

    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    function removeFormatting(input) {
        input.value = input.value.replace(/[^0-9.]/g, '').replace(/,/g, '');
    }

    function applyFormatting(input) {
        if (input.value !== "") {
            input.value = formatNumber(input.value);
        }
    }

    function checkSelisih() {
        let idrAuto = parseFloat(document.getElementById("idr").value.replace(/,/g, '')) || 0;
        let idrBank = parseFloat(document.getElementById("idrb").value.replace(/,/g, '')) || 0;
        let selisih = idrAuto - idrBank;

        let resultText = "";
        if (selisih > 0) {
            resultText = "Laba selisih kurs, silakan input di bank **IN** USD (+" + formatNumber(selisih) + " IDR)";
        } else if (selisih < 0) {
            resultText = "Rugi selisih kurs, silakan input di bank **OUT** USD (" + formatNumber(Math.abs(selisih)) + " IDR)";
        } else {
            resultText = "Tidak ada selisih kurs.";
        }

        document.getElementById("selisih-result").innerText = resultText;
    }
</script>

</body>
</html>
