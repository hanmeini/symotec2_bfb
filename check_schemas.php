<?php
require_once 'config1.php';
$conn = new mysqli(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));

$tables = ['jurnal', 'apby', 'pembelianho1', 'titipanap'];
foreach($tables as $t){
    echo "\nTable $t:\n";
    $res = $conn->query("DESCRIBE $t");
    if($res){
        while($r = $res->fetch_assoc()) echo $r['Field'].' - '.$r['Type']."\n";
    } else {
        echo "Not found\n";
    }
}
?>
