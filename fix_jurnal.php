<?php
$conn = new mysqli('127.0.0.1', 'root', '', 'symotec2_bfb');
$conn->query("UPDATE jurnal SET jurnal_sementara = journal_number WHERE (jurnal_sementara IS NULL OR jurnal_sementara = '') AND (journal_number IS NOT NULL AND journal_number != '') AND keterangan LIKE '%Pembayaran AP%'");
echo "Updated " . $conn->affected_rows . " rows.\n";
?>
