<?php
// redirect.php

// target URL
$url = 'https://sd.alazzam.sch.id/index.html';

// delay dalam detik (0 = langsung)
$delay = 0;

// jika header belum dikirim, gunakan header Location (paling bersih)
if (!headers_sent()) {
    if ($delay === 0) {
        header("Location: $url");
        exit;
    } else {
        // Refresh header jika ingin delay
        header("Refresh: $delay; url=$url");
        // jangan exit agar HTML fallback tetap dikirim
    }
}
// Jika header sudah dikirim atau header tidak bekerja, gunakan HTML/JS fallback di bawah
?>