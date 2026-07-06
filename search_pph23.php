<?php
$dir = new RecursiveDirectoryIterator('.');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
foreach($files as $file) {
    $content = file_get_contents($file[0]);
    if(stripos($content, 'INSERT INTO pph23') !== false) {
        echo "Found in: " . $file[0] . "\n";
    }
}
