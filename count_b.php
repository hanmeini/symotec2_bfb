<?php
require 'config1.php';
$res = $conn->query("SELECT COUNT(*) as c FROM b");
echo $res->fetch_assoc()['c'];
