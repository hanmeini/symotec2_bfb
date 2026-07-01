<?php

  
    



require 'config1.php';


?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Scan QR Aset</title>
  <script src="https://unpkg.com/html5-qrcode"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f2f9ff;
      margin: 0;
      padding: 20px;
      color: #333;
    }
    .container {
      max-width: 600px;
      margin: auto;
      padding: 20px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      text-align: center;
    }
    #reader {
      width: 100%;
      margin-bottom: 20px;
    }
    h2 {
      color: #003366;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Scan QR Code Aset</h2>
    <div id="reader"></div>
    <p>Arahkan kamera ke QR Code untuk melihat detail aset.</p>
  </div>

  <script>
    function onScanSuccess(decodedText, decodedResult) {
        // Setelah berhasil scan, arahkan ke asetcekdetail.php
        window.location.href = "asetcekdetail.php?kode=" + encodeURIComponent(decodedText);
    }

    function onScanFailure(error) {
        // Optional: console log jika scan gagal
        console.warn(`Scan gagal: ${error}`);
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
        "reader",
        {
            fps: 10,
            qrbox: 250,
            rememberLastUsedCamera: true,
            showTorchButtonIfSupported: true
        },
        false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
  </script>
</body>
</html>
