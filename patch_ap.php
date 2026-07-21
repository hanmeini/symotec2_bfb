<?php
function patchFile($filename) {
    $content = file_get_contents($filename);
    
    // Step 1: Change the INSERT INTO block to include both columns
    // Currently, apc.php has journal_number, and apb.php has journal_number
    $content = str_replace(
"                journal_number,
                tanggal,
                keterangan,",
"                journal_number,
                jurnal_sementara,
                tanggal,
                keterangan,",
        $content
    );
    
    $content = str_replace(
"            VALUES
            (?,?,?,?,?,?,?,?,?)",
"            VALUES
            (?,?,?,?,?,?,?,?,?,?)",
        $content
    );
    
    // Step 2: Change bind_params
    // They are:
    // $stmtJurnal->bind_param(
    //     "sssssddss",
    //     $kodeCOS,
    // (We also need to replace $kodeCOB for apb if it uses it)
    $content = preg_replace(
        '/"sssssddss",\s*\$kodeCOS,/',
        '"ssssssddss",
            $kodeCOS,
            $kodeCOS,',
        $content
    );
    
    file_put_contents($filename, $content);
    echo "Patched $filename\n";
}

patchFile('c:\Users\X1 CARBON\Downloads\symotec2_bfb\apc.php');
patchFile('c:\Users\X1 CARBON\Downloads\symotec2_bfb\apb.php');
?>
