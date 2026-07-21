<?php require "config1.php"; $res = $conn->query("SELECT * FROM stock ORDER BY ids DESC LIMIT 5"); while($r = $res->fetch_assoc()) print_r($r);
