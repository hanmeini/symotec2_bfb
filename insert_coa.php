<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
$stmt = $conn->prepare("INSERT INTO coa (account_code, account_name, posisi, dc, layer, open, parent_account) VALUES ('21101', 'Hutang Dagang', 'neraca', 'credit', 4, 0.00, '211')");
if($stmt->execute()) {
    echo "COA 21101 inserted successfully.";
} else {
    echo "Error inserting COA: " . $stmt->error;
}
?>
