<?php
$mkb_dir = 'C:/Users/X1 CARBON/OneDrive/Desktop/symotec2_mkb/';
$files = scandir($mkb_dir);

foreach ($files as $file) {
    if (is_file($mkb_dir . $file) && substr($file, -4) === '.php') {
        $content = file_get_contents($mkb_dir . $file);
        
        // Remove the block like:
        // 
        // OR
        // 
        
        $pattern1 = "/if\\s*\\(\\s*!isset\\(\\\$_SERVER\\['HTTP_REFERER'\\]\\).*?exit\\(\\);\\s*\\}/s";
        $content = preg_replace($pattern1, "", $content);
        
        $pattern2 = "/if\\s*\\(\\s*isset\\(\\\$_SERVER\\['HTTP_REFERER'\\]\\).*?exit\\(\\);\\s*\\}/s";
        $content = preg_replace($pattern2, "", $content);

        // Also remove $allowed_referer_domain definition
        $pattern3 = "/\\\$allowed_referer_domain\\s*=\\s*['\"].*?['\"];/s";
        $content = preg_replace($pattern3, "", $content);

        file_put_contents($mkb_dir . $file, $content);
    }
}
echo "Removed referer checks.\n";
