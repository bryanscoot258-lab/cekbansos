<?php
// fetchwilayah.php
// Lightweight HTTP endpoint to generate/cache full wilayah.json from emsifa API.
// NOTE: For large datasets prefer running generate_wilayah.php via CLI (recommended).
// Usage:
//  - GET /fetchwilayah.php          => serves cached wilayah.json if exists
//  - GET /fetchwilayah.php?refresh=1 => force refresh (rebuild) and serve

set_time_limit(0);
@ini_set('memory_limit', '512M');

$BASE = 'https://emsifa.github.io/api-wilayah-indonesia/1.0.0';
$OUT_FILE = __DIR__ . '/wilayah.json';
$refresh = isset($_GET['refresh']) && ($_GET['refresh'] == '1' || $_GET['refresh'] == 'true');

// simple fetch with retry
function fetch_json($url, $tries = 5, $timeout = 15) {
    $wait = 200000; // 200ms
    for ($i = 0; $i < $tries; $i++) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-wilayah-fetcher/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw !== false && $code >= 200 && $code < 300) {
            $json = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) return $json;
        }
        usleep($wait);
        $wait *= 2;
    }
    return null;
}

function safe_response_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// jika file ada dan tidak diminta refresh -> kirim file
if (file_exists($OUT_FILE) && !$refresh) {
    header('Content-Type: application/json; charset=utf-8');
    readfile($OUT_FILE);
    exit;
}

// Build full JSON (may take waktu lama). Jika gagal, beri pesan error yang jelas.
$provinces = fetch_json($BASE . '/provinces.json');
if (!$provinces) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Gagal memuat daftar provinsi dari sumber eksternal. Coba lagi nanti atau jalankan generate_wilayah.php via CLI.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = ['provinces' => []];
foreach ($provinces as $p) {
    $provId = (string)$p['id'];
    $provName = $p['name'];
    $provObj = ['id' => $provId, 'name' => $provName, 'regencies' => []];

    $regencies = fetch_json($BASE . "/regencies/{$provId}.json");
    if (!$regencies) {
        // masukkan kosong agar struktur tetap sama
        $result['provinces'][] = $provObj;
        continue;
    }

    foreach ($regencies as $r) {
        $regId = (string)$r['id'];
        $regName = $r['name'];
        $regObj = ['id' => $regId, 'name' => $regName, 'districts' => []];

        $districts = fetch_json($BASE . "/districts/{$regId}.json");
        if (!$districts) {
            $provObj['regencies'][] = $regObj;
            continue;
        }

        foreach ($districts as $d) {
            $distId = (string)$d['id'];
            $distName = $d['name'];
            $distObj = ['id' => $distId, 'name' => $distName, 'villages' => []];

            $villages = fetch_json($BASE . "/villages/{$distId}.json");
            if ($villages) {
                foreach ($villages as $v) {
                    $distObj['villages'][] = ['id' => (string)$v['id'], 'name' => $v['name']];
                }
            }
            $regObj['districts'][] = $distObj;
        }

        $provObj['regencies'][] = $regObj;
    }

    $result['provinces'][] = $provObj;
    // optional: small sleep untuk meredam beban pada sumber eksternal
    usleep(80 * 1000); // 80 ms
}

// simpan ke file (atomically)
$tmp = $OUT_FILE . '.tmp';
if (@file_put_contents($tmp, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false) {
    @rename($tmp, $OUT_FILE);
}

// kirim hasil ke client
safe_response_json($result);
?>