<?php
require 'config1.php';
$res = $conn->query("DESCRIBE jurnal");
if($res){
    while($r = $res->fetch_assoc()){
        print_r($r);
    }
}else{
    echo $conn->error;
}
?>
