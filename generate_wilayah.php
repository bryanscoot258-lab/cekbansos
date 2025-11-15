<?php
/**
 * generate_wilayah.php
 *
 * Ambil data wilayah Indonesia lengkap (provinsi -> kab/kota -> kecamatan -> desa)
 * dari https://emsifa.github.io/api-wilayah-indonesia/1.0.0
 *
 * Fitur:
 *  - curl dengan retry/backoff
 *  - opsi: --out=FILE, --no-villages, --split, --pretty, --delay=ms
 *  - resume otomatis saat --split (menyimpan provinsi per file + state)
 *
 * Cara pakai:
 *  php generate_wilayah.php --out=wilayah.json
 *  php generate_wilayah.php --out=wilayah.json --no-villages
 *  php generate_wilayah.php --split --pretty
 *  php generate_wilayah.php --delay=250
 *
 * Catatan:
 *  - Untuk resume yang handal gunakan --split (menyimpan tiap provinsi ke folder provinsi/)
 *  - File lengkap bisa sangat besar (puluhan MB) jika include desa.
 */

$BASE = 'https://emsifa.github.io/api-wilayah-indonesia/1.0.0';
$options = [
    'out' => 'wilayah.json',
    'fetch_villages' => true,
    'split' => false,
    'pretty' => false,
    'delay' => 120, // ms
    'state_file' => 'generate_wilayah.state.json',
];

foreach ($argv as $arg) {
    if (strpos($arg, '--out=') === 0) $options['out'] = substr($arg, 6);
    if ($arg === '--no-villages') $options['fetch_villages'] = false;
    if ($arg === '--split') $options['split'] = true;
    if ($arg === '--pretty') $options['pretty'] = true;
    if (strpos($arg, '--delay=') === 0) $options['delay'] = (int)substr($arg, 8);
    if ($arg === '--help' || $arg === '-h') {
        echo "Usage: php generate_wilayah.php [--out=FILE] [--no-villages] [--split] [--pretty] [--delay=ms]\n";
        exit(0);
    }
}

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
        // jika gagal, tunda lalu retry
        usleep($wait);
        $wait *= 2;
    }
    return null;
}

function safe_filename($s) {
    // buat nama file aman: id_name.json
    $s = preg_replace('/[^\w\- ]+/', '', $s);
    $s = str_replace(' ', '_', $s);
    return $s;
}

// load provinsi list
echo "Ambil daftar provinsi dari $BASE/provinces.json ...\n";
$provinces = fetch_json($BASE . '/provinces.json');
if (!$provinces) {
    fwrite(STDERR, "Gagal memuat daftar provinsi.\n");
    exit(1);
}

if ($options['split']) {
    if (!is_dir('provinsi')) {
        if (!mkdir('provinsi', 0755, true)) {
            fwrite(STDERR, "Gagal membuat folder provinsi/\n");
            exit(1);
        }
    }
}

// load state jika ada (hanya dipakai untuk mode --split)
$state = ['done' => []];
if ($options['split'] && file_exists($options['state_file'])) {
    $raw = @file_get_contents($options['state_file']);
    $tmp = json_decode($raw, true);
    if (is_array($tmp) && isset($tmp['done']) && is_array($tmp['done'])) $state = $tmp;
}

function save_state($file, $state) {
    @file_put_contents($file, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$main_fp = null;
if (!$options['split']) {
    // tulis file tunggal (overwrite)
    $main_fp = fopen($options['out'], 'wb');
    if ($main_fp === false) {
        fwrite(STDERR, "Gagal membuat file output: {$options['out']}\n");
        exit(1);
    }
    fwrite($main_fp, "{\"provinces\":[");
}

$is_first_prov = true;
$counts = ['prov'=>0,'reg'=>0,'dist'=>0,'vill'=>0];

foreach ($provinces as $p) {
    $provId = (string)$p['id'];
    $provName = $p['name'];
    $counts['prov']++;
    echo "Provinsi: {$provName} ({$provId})\n";

    // resume support only for split mode
    if ($options['split'] && in_array($provId, $state['done'], true)) {
        echo "  => sudah pernah diproses (skip)\n";
        continue;
    }

    $provObj = ['id' => $provId, 'name' => $provName, 'regencies' => []];

    // ambil regencies
    $regencies = fetch_json($BASE . "/regencies/{$provId}.json");
    if (!$regencies) {
        fwrite(STDERR, "  Peringatan: gagal memuat regencies untuk provinsi {$provId}\n");
    } else {
        foreach ($regencies as $r) {
            $regId = (string)$r['id'];
            $regName = $r['name'];
            echo "  Kab/Kota: {$regName} ({$regId})\n";
            $counts['reg']++;
            $regObj = ['id' => $regId, 'name' => $regName, 'districts' => []];

            $districts = fetch_json($BASE . "/districts/{$regId}.json");
            if (!$districts) {
                fwrite(STDERR, "    Peringatan: gagal memuat districts untuk kab/kota {$regId}\n");
            } else {
                foreach ($districts as $d) {
                    $distId = (string)$d['id'];
                    $distName = $d['name'];
                    echo "    Kecamatan: {$distName} ({$distId})\n";
                    $counts['dist']++;
                    $distObj = ['id' => $distId, 'name' => $distName, 'villages' => []];

                    if ($options['fetch_villages']) {
                        $villages = fetch_json($BASE . "/villages/{$distId}.json");
                        if (!$villages) {
                            fwrite(STDERR, "      Peringatan: gagal memuat villages untuk kecamatan {$distId}\n");
                        } else {
                            foreach ($villages as $v) {
                                $distObj['villages'][] = ['id' => (string)$v['id'], 'name' => $v['name']];
                                $counts['vill']++;
                            }
                        }
                        usleep($options['delay'] * 1000);
                    }

                    $regObj['districts'][] = $distObj;
                }
            }

            $provObj['regencies'][] = $regObj;
            usleep($options['delay'] * 1000);
        }
    }

    // tulis provinsi
    if ($options['split']) {
        $fname = 'provinsi/' . $provId . '_' . safe_filename($provName) . '.json';
        $flags = $options['pretty'] ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;
        $ok = @file_put_contents($fname, json_encode($provObj, $flags));
        if ($ok === false) {
            fwrite(STDERR, "  Gagal menulis file provinsi: $fname\n");
        } else {
            echo "  Ditulis: $fname\n";
            // catat state
            $state['done'][] = $provId;
            save_state($options['state_file'], $state);
        }
    } else {
        // mode single file: streaming JSON (menambahkan koma sesuai posisi)
        $json = json_encode($provObj, $options['pretty'] ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE);
        if (!$is_first_prov) fwrite($main_fp, ",");
        fwrite($main_fp, $json);
        $is_first_prov = false;
    }

    // delay antar provinsi
    usleep($options['delay'] * 1000);
}

if (!$options['split']) {
    fwrite($main_fp, "]}");
    fclose($main_fp);
    echo "File utama ditulis: {$options['out']}\n";
} else {
    echo "Mode split selesai (setiap provinsi disimpan di folder provinsi/). State tersimpan di {$options['state_file']} untuk resume.\n";
}

// ringkasan
echo "\nRingkasan:\n";
echo "  Provinsi diproses: {$counts['prov']}\n";
echo "  Kab/Kota (terhitung saat fetch): {$counts['reg']}\n";
echo "  Kecamatan (terhitung saat fetch): {$counts['dist']}\n";
if ($options['fetch_villages']) echo "  Desa/kelurahan (terhitung saat fetch): {$counts['vill']}\n";
echo "Selesai.\n";
exit(0);
?>