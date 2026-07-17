<?php
require 'config1.php';
$res = $conn->query("SELECT * FROM coa WHERE account_name LIKE '%Titipan%'");
if($res){
    while($r = $res->fetch_assoc()){
        print_r($r);
    }
}else{
    echo $conn->error;
}
?>
