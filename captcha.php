<?php
// captcha.php (minimal) - simpan sebagai UTF-8 without BOM
// Pastikan file tidak memiliki spasi/baris kosong sebelum <?php
session_start();

// Buat kode sederhana dan simpan ke session (bisa diubah nanti)
$code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5);
$_SESSION['captcha'] = $code;

// set waktu pembuatan captcha dan reset percobaan tiap kali captcha baru dibuat
$_SESSION['captcha_time'] = time();
$_SESSION['captcha_attempts'] = 0;

// Jika GD tersedia, buat PNG sederhana (tidak menggunakan TTF)
if (function_exists('imagecreatetruecolor') && function_exists('imagepng')) {
    $w = 140; $h = 48;
    $im = imagecreatetruecolor($w, $h);

    $bg = imagecolorallocate($im, 255, 255, 255);
    $fg = imagecolorallocate($im, 30, 30, 90);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);

    // noise garis
    for ($i = 0; $i < 5; $i++) {
        $c = imagecolorallocate($im, rand(120,200), rand(120,200), rand(120,200));
        imageline($im, rand(0,$w), rand(0,$h), rand(0,$w), rand(0,$h), $c);
    }

    // noise titik
    for ($i = 0; $i < 30; $i++) {
        $c = imagecolorallocate($im, rand(120,220), rand(120,220), rand(120,220));
        imagesetpixel($im, rand(0,$w-1), rand(0,$h-1), $c);
    }

    // tampilkan kode memakai imagestring (fallback aman)
    imagestring($im, 5, 12, 12, $code, $fg);

    header('Content-Type: image/png');
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    header('Expires: 0');

    imagepng($im);
    imagedestroy($im);
    exit;
}

// Jika GD tidak tersedia, pakai SVG fallback
header('Content-Type: image/svg+xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
$esc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
echo "<svg xmlns='http://www.w3.org/2000/svg' width='140' height='48'><rect width='100%' height='100%' fill='#fff'/><text x='10' y='30' font-family='Arial' font-size='20' fill='#333'>$esc</text></svg>";
exit;
?>