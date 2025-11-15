<?php
session_start();

// Ambil pesan flash (jika ada)
$flash = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Cek Bansos Kemensos</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Ukuran dikurangi untuk topbar dan elemen form */
        :root{--primary:#3f51b5;--accent:#1976d2;--muted:#f5f6fa}
        *{box-sizing:border-box}
        body{margin:0;background:var(--muted);font-family:Arial,Helvetica,sans-serif;color:#222}
        /* Topbar lebih ringkas */
        .topbar{
            background:var(--primary);
            color:#fff;
            padding:6px 10px; /* dikurangi */
            display:flex;
            align-items:center;
            gap:10px;
            height:60px; /* tetapkan tinggi agar konsisten */
        }
        .topbar img{
            height:50px; /* logo lebih kecil */
            width:auto;
            display:block;
        }
        .topbar-title{
            font-weight:700;
            font-size:15px; /* lebih kecil */
            line-height:1;
        }

        /* Kontainer lebih rapat */
        .wrap{max-width:980px;margin:18px auto;display:flex;gap:18px;padding:0 12px}
        .card{background:#fff;padding:16px;border-radius:6px;box-shadow:0 2px 6px rgba(0,0,0,0.06)}
        .form-section{flex:1;min-width:300px;padding:16px}
        .info-section{width:260px;padding:14px}
        .form-group{margin-bottom:12px}
        .form-label{display:block;font-weight:700;margin-bottom:6px;font-size:13px}
        select,input[type="text"]{
            width:100%;
            padding:8px; /* lebih kecil */
            border:1px solid #e0e0e0;
            border-radius:4px;
            font-size:13px; /* lebih kecil */
        }
        .captcha-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
        .captcha-img{height:38px;border:1px solid #e0e0e0;border-radius:4px;display:block}
        .refresh-btn{
            background:var(--accent);
            color:#fff;
            border:none;
            border-radius:6px;
            padding:6px 8px; /* dikurangi */
            cursor:pointer;
            font-size:13px;
        }
        .form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:14px}
        .btn{border-radius:6px;padding:8px 12px;border:none;cursor:pointer}
        .btn-cancel{background:#eee}
        .btn-search{background:var(--primary);color:#fff}

        /* Flash sederhana */
        .flash{padding:8px 10px;margin-bottom:10px;border-radius:4px;font-size:13px}
        .flash.success{background:#e6f4ea;color:#154d27}
        .flash.error{background:#fdecea;color:#611212}

        /* Responsive: pada layar kecil, buat stack dan kecilkan lagi */
        @media (max-width:760px){
            .wrap{flex-direction:column;margin:12px auto;gap:12px;padding:0 10px}
            .info-section{width:100%}
            .topbar{height:40px;padding:6px 8px}
            .topbar img{height:24px}
            .topbar-title{font-size:14px}
            .card{padding:12px}
            .form-section{padding:12px}
            select,input[type="text"]{padding:7px;font-size:13px}
            .captcha-img{height:34px}
            .refresh-btn{padding:5px 7px}
        }
    </style>
</head>
<body>
    <div class="topbar">
        <img src="loginlogo.png" alt="Logo" onerror="this.style.display='none'">
        <div class="topbar-title">Cek Bansos Kemensos</div>
    </div>

    <div class="wrap">
        <div class="card form-section">
            <form id="search-form" method="post" action="verify.php" autocomplete="off" novalidate>
                <h3 style="margin-top:0;font-size:16px">PENCARIAN DATA PM (PENERIMA MANFAAT) BANSOS</h3>

                <?php if ($flash): ?>
                    <div class="flash <?php echo ($flash['type'] === 'ok' ? 'success' : 'error'); ?>">
                        <?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">WILAYAH PM (Penerima Manfaat)</label>
                    <select id="provinsi" name="provinsi" required>
                        <option value="">=== Pilih Provinsi ===</option>
                    </select>

                    <select id="kabkota" name="kabkota" required disabled>
                        <option value="">=== Pilih Kab/Kota ===</option>
                    </select>

                    <select id="kecamatan" name="kecamatan" required disabled>
                        <option value="">=== Pilih Kecamatan ===</option>
                    </select>

                    <select id="desa" name="desa" required disabled>
                        <option value="">=== Pilih Desa ===</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">NAMA PM (Penerima Manfaat)</label>
                    <input type="text" name="nama_pm" placeholder="Nama Sesuai KTP" required>
                </div>

                <div class="form-group">
                    <label class="form-label">HURUF KODE</label>
                    <div class="captcha-row">
                        <img src="captcha.php?reload=<?php echo time(); ?>" id="captcha-img" class="captcha-img" alt="CAPTCHA">
                        <button type="button" id="btn-refresh" class="refresh-btn" title="Refresh Captcha">
                            <i class="fa fa-refresh"></i>
                        </button>
                    </div>
                    <input type="text" name="captcha" placeholder="Ketik huruf kode di atas" required>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-cancel"><i class="fa fa-times"></i> BATAL</button>
                    <button type="submit" class="btn btn-search"><i class="fa fa-search"></i> CARI DATA</button>
                </div>
            </form>
        </div>

        <div class="card info-section">
            <b>Petunjuk Pencarian</b>
            <ol style="margin-top:8px;padding-left:18px;font-size:13px">
                <li>Masukkan Provinsi, Kabupaten, Kecamatan, dan Desa/Kelurahan</li>
                <li>Masukkan nama PM sesuai KTP</li>
                <li>Ketikkan 4-5 huruf kode yang tertera pada kotak kode</li>
                <li>Jika huruf kode kurang jelas, klik ikon <button type="button" id="btn-refresh-2" class="refresh-btn" title="Refresh Captcha">
                            <i class="fa fa-refresh"></i>
                        </button> untuk mendapatkan huruf kode baru</li>
                <li>Klik tombol CARI DATA</li>
            </ol>
        </div>
    </div>

    <script>
    // Data variable (akan diisi oleh fetch dari wilayah.json)
    let wilayahData = null;

    // Load JSON file berisi wilayah; default nama file: wilayah.json (ganti dengan wilayah-sample.json untuk testing)
    async function loadWilayah(filename = 'wilayah.sql') {
        try {
            const res = await fetch(filename, {cache: "no-store"});
            if (!res.ok) throw new Error('Gagal memuat ' + filename + ' (status ' + res.status + ')');
            wilayahData = await res.json();
            populateProvinces();
        } catch (err) {
            console.error(err);
            // coba fallback ke sample jika ada
            try {
                const res2 = await fetch('wilayah-sample.json', {cache: "no-store"});
                wilayahData = await res2.json();
                populateProvinces();
            } catch (err2) {
                console.error('Gagal memuat wilayah-sample.json juga', err2);
                alert('Gagal memuat data wilayah. Pastikan file wilayah.json atau wilayah-sample.json ada di folder project.');
            }
        }
    }

    function populateProvinces() {
        const sel = document.getElementById('provinsi');
        sel.innerHTML = '<option value="">=== Pilih Provinsi ===</option>';
        if (!wilayahData || !Array.isArray(wilayahData.provinces)) return;
        wilayahData.provinces.forEach(prov => {
            const opt = document.createElement('option');
            opt.value = prov.id;
            opt.textContent = prov.name;
            sel.appendChild(opt);
        });
        // reset downstream
        resetSelect('kabkota');
        resetSelect('kecamatan');
        resetSelect('desa');
    }

    function resetSelect(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.innerHTML = '<option value="">=== Pilih ' + (id === 'kabkota' ? 'Kab/Kota' : id === 'kecamatan' ? 'Kecamatan' : 'Desa') + ' ===</option>';
        el.disabled = true;
    }

    function findProvinceById(id) {
        if (!wilayahData) return null;
        return wilayahData.provinces.find(p => p.id === id) || null;
    }
    function findRegencyById(prov, id) {
        if (!prov || !prov.regencies) return null;
        return prov.regencies.find(r => r.id === id) || null;
    }
    function populateRegencies(provId) {
        const prov = findProvinceById(provId);
        const sel = document.getElementById('kabkota');
        resetSelect('kabkota');
        resetSelect('kecamatan');
        resetSelect('desa');
        if (!prov) return;
        prov.regencies.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            opt.textContent = r.name;
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    function populateDistricts(provId, regId) {
        const prov = findProvinceById(provId);
        const reg = findRegencyById(prov, regId);
        const sel = document.getElementById('kecamatan');
        resetSelect('kecamatan');
        resetSelect('desa');
        if (!reg) return;
        reg.districts.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    function populateVillages(provId, regId, distId) {
        const prov = findProvinceById(provId);
        const reg = findRegencyById(prov, regId);
        const dist = reg ? reg.districts.find(x => x.id === distId) : null;
        const sel = document.getElementById('desa');
        resetSelect('desa');
        if (!dist) return;
        dist.villages.forEach(v => {
            const opt = document.createElement('option');
            opt.value = v.id;
            opt.textContent = v.name;
            sel.appendChild(opt);
        });
        sel.disabled = false;
    }

    // Event listeners
    document.getElementById('provinsi').addEventListener('change', function () {
        const pid = this.value;
        if (!pid) {
            resetSelect('kabkota');
            resetSelect('kecamatan');
            resetSelect('desa');
            return;
        }
        populateRegencies(pid);
    });

    document.getElementById('kabkota').addEventListener('change', function () {
        const regId = this.value;
        const provId = document.getElementById('provinsi').value;
        if (!regId) {
            resetSelect('kecamatan');
            resetSelect('desa');
            return;
        }
        populateDistricts(provId, regId);
    });

    document.getElementById('kecamatan').addEventListener('change', function () {
        const distId = this.value;
        const provId = document.getElementById('provinsi').value;
        const regId = document.getElementById('kabkota').value;
        if (!distId) {
            resetSelect('desa');
            return;
        }
        populateVillages(provId, regId, distId);
    });

    // load data pada saat halaman ready
    document.addEventListener('DOMContentLoaded', function () {
        // default mencari 'wilayah.json'. Jika tidak ada, akan fallback ke wilayah-sample.json
        loadWilayah('wilayah.json');
        // tombol refresh captcha (utama)
        const btn = document.getElementById('btn-refresh');
        if (btn) {
            btn.addEventListener('click', function () {
                const img = document.getElementById('captcha-img');
                img.src = 'captcha.php?reload=' + Date.now() + Math.floor(Math.random()*1000);
            });
        }
        const imgEl = document.getElementById('captcha-img');
        if (imgEl) {
            imgEl.addEventListener('click', function () {
                this.src = 'captcha.php?reload=' + Date.now() + Math.floor(Math.random()*1000);
            });
        }
        // tombol refresh pada petunjuk (jika ada)
        const btn2 = document.getElementById('btn-refresh-2');
        if (btn2) {
            btn2.addEventListener('click', function () {
                const img = document.getElementById('captcha-img');
                if (img) img.src = 'captcha.php?reload=' + Date.now() + Math.floor(Math.random()*1000);
            });
        }
    });
    </script>
</body>
</html>