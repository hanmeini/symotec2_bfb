<?php require "config1.php"; $res = $conn->query("SELECT id_transaksi, J, inv, tanggal_transaksi FROM penjualanho1 ORDER BY id_transaksi DESC LIMIT 5"); while($r = $res->fetch_assoc()) print_r($r);
