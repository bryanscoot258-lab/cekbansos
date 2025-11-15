<?php
// verify.php - validate captcha with expiry and attempt limit, then redirect with flash
session_start();

// ambil input
$input = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';
$nama  = isset($_POST['nama_pm']) ? trim($_POST['nama_pm']) : '';

// simple flash helper
function flash($type, $msg) {
    $_SESSION['flash'] = ['type'=>$type, 'msg'=>$msg];
    header('Location: utama.php');
    exit;
}

// cek ada captcha di session?
if (!isset($_SESSION['captcha']) || !isset($_SESSION['captcha_time'])) {
    flash('err', 'Captcha tidak ditemukan. Muat ulang halaman dan coba lagi.');
}

// batasi percobaan (misal 5 percobaan)
$max_attempts = 5;
if (!isset($_SESSION['captcha_attempts'])) $_SESSION['captcha_attempts'] = 0;
$_SESSION['captcha_attempts']++;

// jika melebihi batas
if ($_SESSION['captcha_attempts'] > $max_attempts) {
    unset($_SESSION['captcha']);
    unset($_SESSION['captcha_time']);
    unset($_SESSION['captcha_attempts']);
    flash('err', 'Terlalu banyak percobaan. Captcha telah direset. Silakan muat ulang dan coba lagi.');
}

// cek expiry (5 menit misal)
$expires = 300; // detik
if (time() - $_SESSION['captcha_time'] > $expires) {
    unset($_SESSION['captcha']);
    unset($_SESSION['captcha_time']);
    unset($_SESSION['captcha_attempts']);
    flash('err', 'Captcha sudah kadaluwarsa. Silakan muat ulang halaman untuk captcha baru.');
}

// bandingkan case-insensitive
if (strcasecmp($input, $_SESSION['captcha']) === 0) {
    // cocok: hapus captcha agar tidak bisa dipakai lagi
    unset($_SESSION['captcha']);
    unset($_SESSION['captcha_time']);
    unset($_SESSION['captcha_attempts']);

    // lanjut logika pencarian — disini cuma contoh flash success
    flash('ok', 'Captcha benar. Lanjutkan pencarian untuk: ' . htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'));
} else {
    // salah: simpan percobaan sudah ditambah di atas
    flash('err', 'Captcha salah! Silakan coba lagi.');
}
?>