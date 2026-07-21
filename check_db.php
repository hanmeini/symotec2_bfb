<?php require "config1.php"; $res = $conn->query("SELECT * FROM penjualanho1 WHERE J = '0012/ORD/VII/2026'"); while($r = $res->fetch_assoc()) print_r($r);
