<?php
function fixQuery($file) {
    $c = file_get_contents($file);
    // Replace column list
    $c = preg_replace(
        '/INSERT INTO jurnal\s*\(\s*journal_number,\s*tanggal,/',
        "INSERT INTO jurnal\n            (\n                journal_number,\n                jurnal_sementara,\n                tanggal,",
        $c
    );
    // Replace question marks
    $c = preg_replace(
        '/\(\?,\?,\?,\?,\?,\?,\?,\?,\?\)/',
        '(?,?,?,?,?,?,?,?,?,?)',
        $c
    );
    file_put_contents($file, $c);
    echo "Fixed $file\n";
}
fixQuery('c:\Users\X1 CARBON\Downloads\symotec2_bfb\apc.php');
fixQuery('c:\Users\X1 CARBON\Downloads\symotec2_bfb\apb.php');
?>
