<?php
$dir = __DIR__;
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$replacements = [
    'Mitra Karya Bahagia' => 'BFB',
    'MITRA KARYA BAHAGIA' => 'BFB',
    'mitra karya bahagia' => 'bfb',
    'MKB' => 'BFB',
    'mkb' => 'bfb',
    'M K B' => 'B F B'
];

$count = 0;
foreach ($files as $file) {
    if ($file->isFile()) {
        $path = $file->getRealPath();
        // Skip .git folder, vendor folder, images, and this script itself
        if (strpos($path, '.git') !== false || strpos($path, 'vendor') !== false || strpos($path, 'replace_mkb_to_bfb.php') !== false) {
            continue;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if (in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'gif', 'zip', 'pdf'])) {
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) continue;
        
        $originalContent = $content;
        $content = strtr($content, $replacements);
        
        if ($content !== $originalContent) {
            file_put_contents($path, $content);
            $count++;
            echo "Updated: " . str_replace($dir, '', $path) . "\n";
        }
    }
}
echo "Total files updated: $count\n";
