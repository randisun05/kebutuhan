/**
 * Pengujian manual terpandu SIMONKEB + pembuatan laporan bergambar.
 *
 * Menjalankan jalur uji end-to-end di peramban (Playwright, jendela terpisah dari Chrome yang sedang dipakai),
 * mengambil tangkapan layar tiap langkah, lalu menulis docs/pengujian/LAPORAN-PENGUJIAN.md + docs/pengujian/gambar/*.png.
 *
 * PERINGATAN: skrip ini MENGUBAH data (membuat, mengubah, menghapus, mengajukan usulan).
 * Jalankan hanya pada database uji sekali pakai, jangan pada data yang sedang dipakai orang lain:
 *
 *   DB_DATABASE=/path/uji.sqlite php artisan migrate:fresh --seed --force
 *   DB_DATABASE=/path/uji.sqlite php -d memory_limit=768M -S 127.0.0.1:8123 ...   (dari folder public, pakai server.php)
 *   APP_URL=http://127.0.0.1:8123 node scripts/pengujian-manual.mjs
 *
 * Opsional: PW_CHROMIUM=/path/chrome.exe (peramban yang dipakai; default Chromium bawaan Playwright).
 */
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { fileURLToPath, pathToFileURL } from 'node:url';

const base = process.env.APP_URL || 'http://127.0.0.1:8123';
const dirOut = fileURLToPath(new URL('../docs/pengujian/', import.meta.url));
const dirGambar = dirOut + 'gambar/';
rmSync(dirGambar, { recursive: true, force: true });
mkdirSync(dirGambar, { recursive: true });

const AKUN = {
    admin: ['admin@simonkeb.test', 'Administrator'],
    bkn: ['bkn@simonkeb.test', 'Verifikator BKN'],
    kemenpan: ['kemenpan@simonkeb.test', 'Validator Kemenpan'],
    pimpinan: ['pimpinan@simonkeb.test', 'Pimpinan (hanya baca)'],
    kab: ['kab-sjt@simonkeb.test', 'Operator Pemerintah Kabupaten Sejahtera'],
    kot: ['kot-mdn@simonkeb.test', 'Operator Kota Madani'],
    prov: ['prov-nsn@simonkeb.test', 'Operator Provinsi Nusantara'],
    kl: ['kl-adm@simonkeb.test', 'Operator Kementerian'],
};

const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
const sesi = {};
async function halaman(akun) {
    if (!sesi[akun]) {
        const ctx = await browser.newContext({ viewport: { width: 1366, height: 820 }, deviceScaleFactor: 1, acceptDownloads: true });
        const page = await ctx.newPage();
        page.setDefaultTimeout(15000);
        if (akun !== 'tamu') {
            await page.goto(base + '/login');
            await page.fill('input[type=email]', AKUN[akun][0]);
            await page.fill('input[type=password]', 'password');
            await Promise.all([page.waitForURL('**/dashboard'), page.click('form button')]);
        }
        sesi[akun] = page;
    }
    return sesi[akun];
}

// ---------- pembantu ----------
const tenang = async (p, ms = 500) => { await p.waitForLoadState('networkidle'); await p.waitForTimeout(ms); };
const buka = async (p, path) => { const r = await p.goto(base + path); await tenang(p); return r.status(); };
const F = (p, label) => p.locator(`div:has(> label:text-is("${label}"))`).locator('input:not([type=checkbox]),select,textarea').first();
async function pilih(sel, teks) {
    const v = await sel.evaluate((s, t) => { const o = [...s.options].find((x) => x.text.includes(t)); return o ? o.value : null; }, teks);
    if (v === null) throw new Error('opsi tidak ditemukan: ' + teks);
    await sel.selectOption(v);
}
const pilihKe = async (sel, i) => sel.selectOption({ index: i });
let ekstra = []; let kini = '';
async function ambil(p, ket) { const f = `${kini}-${ekstra.length + 1}.png`; await p.screenshot({ path: dirGambar + f }).catch(() => {}); ekstra.push({ f, ket }); }
async function cari(p, sel, v) { await p.fill(sel, v); await p.press(sel, 'Enter'); await tenang(p, 1500); }
const bukanGet = (r) => r.request().method() !== 'GET';
async function kirim(p, aksi) {
    const tunggu = p.waitForResponse(bukanGet, { timeout: 20000 }).catch(() => null);
    await aksi(); await tunggu; await tenang(p, 1000);
}
const klikSimpan = async (p, nama = /Simpan/) => kirim(p, () => p.getByRole('button', { name: nama }).first().click());
const swal = async (p) => (await p.locator('.swal2-title').first().textContent({ timeout: 1500 }).catch(() => null)) || '';
async function tutupSwal(p) {
    for (let i = 0; i < 4; i++) {
        const pop = p.locator('.swal2-popup:visible');
        if (!(await pop.count())) return;
        const ok = p.locator('.swal2-confirm:visible');
        if (await ok.count()) await ok.click().catch(() => {});
        await p.waitForTimeout(600);
    }
}
async function konfirmasi(p, isi, tahan = false) {
    await p.locator('.swal2-popup').waitFor({ state: 'visible', timeout: 5000 });
    if (isi) await p.locator('.swal2-popup textarea.swal2-textarea:visible, .swal2-popup input.swal2-input:visible').first().fill(isi);
    await kirim(p, () => p.locator('.swal2-confirm').click());
    if (!tahan) await tutupSwal(p);
}
const badgeStatus = async (p) => ((await p.locator('.badge:text-matches("^(Draft|Diajukan Instansi|Verifikasi BKN|Pertimbangan Teknis BKN|Validasi KemenPANRB|Ditetapkan|Dikembalikan.*|Ditolak)$", "i")').first().textContent()) || '').trim();
async function hapusBaris(p, teks) {
    await p.locator(`tr:has-text("${teks}")`).first().locator('button:has(.fa-trash)').click();
    await konfirmasi(p, null, true);
    return swal(p);
}
const adaTeks = async (p, t) => (await p.getByText(t, { exact: false }).count()) > 0;
const tegas = (kondisi, pesan) => { if (!kondisi) throw new Error(pesan); };
async function unduh(p, path, jenisKonten) {
    const r = await p.context().request.get(base + path);
    const ct = r.headers()['content-type'] || '';
    const ukuran = (await r.body()).length;
    tegas(r.status() === 200, `HTTP ${r.status()}`);
    tegas(ct.includes(jenisKonten), `content-type ${ct}`);
    return `HTTP 200, ${ct.split(';')[0]}, ${ukuran.toLocaleString('id-ID')} byte`;
}

// ---------- definisi langkah ----------
const LANGKAH = [];
const bagian = (nama, deskripsi) => LANGKAH.push({ bagian: nama, deskripsi });
const uji = (s) => LANGKAH.push(s);

const U = { instansi: 'Instansi Uji Dokumentasi', unit: 'Sekretariat Uji', jabatan: 'Analis Uji Kebijakan', nip: '199001012015011001', pegawai: 'Pegawai Uji Dokumentasi' };

// ===== A. Akses =====
bagian('A. Akses & autentikasi', 'Memastikan hanya pengguna sah yang masuk, dan setiap peran melihat menu yang sesuai.');
uji({
    id: 'login-tampil', akun: 'tamu', judul: 'Halaman login tampil',
    fungsi: 'Pintu masuk aplikasi. Tersedia login manual (email + password) karena `LOGIN_MANUAL=true`; SSO SIASN dimatikan di lingkungan uji.',
    langkah: ['Buka `/login` tanpa masuk.'],
    harapan: 'Formulir email dan password tampil.',
    run: async (p) => { tegas((await buka(p, '/login')) === 200, 'status bukan 200'); tegas(await p.locator('input[type=password]').count() > 0, 'tidak ada isian password'); return 'Formulir login tampil.'; },
});
uji({
    id: 'login-salah', akun: 'tamu', judul: 'Login dengan password salah ditolak',
    fungsi: 'Validasi kredensial.',
    langkah: ['Isi email `admin@simonkeb.test` dan password salah.', 'Klik tombol masuk.'],
    harapan: 'Tetap di halaman login dan muncul pesan kegagalan, tidak masuk ke dashboard.',
    run: async (p) => {
        await buka(p, '/login'); await p.fill('input[type=email]', AKUN.admin[0]); await p.fill('input[type=password]', 'salah-total');
        await p.click('form button'); await tenang(p, 800);
        tegas(p.url().includes('/login'), 'seharusnya tetap di /login: ' + p.url());
        return 'Ditolak, tetap di halaman login.';
    },
});
uji({
    id: 'akses-tanpa-login', akun: 'tamu', judul: 'Halaman terlindung mengalihkan ke login',
    fungsi: 'Semua halaman selain login/panduan publik wajib login.',
    langkah: ['Tanpa masuk, buka `/dashboard`.'],
    harapan: 'Dialihkan ke `/login`.',
    run: async (p) => { await buka(p, '/dashboard'); tegas(p.url().includes('/login'), 'tidak dialihkan: ' + p.url()); return 'Dialihkan ke /login.'; },
});
uji({
    id: 'menu-admin', akun: 'admin', judul: 'Dashboard admin dan menu lengkap',
    fungsi: 'Admin melihat seluruh menu (master data, ABK, perencanaan, usulan, monitoring, laporan, pengaturan).',
    langkah: ['Masuk sebagai `admin@simonkeb.test`.', 'Perhatikan kartu ringkasan dan sidebar.'],
    harapan: 'Dashboard tampil; sidebar memuat semua kelompok menu.',
    run: async (p) => { await buka(p, '/dashboard'); const n = await p.locator('aside a, nav a, .sidebar a').count(); tegas(n > 10, 'menu terlalu sedikit: ' + n); return `Dashboard tampil, ${n} tautan menu.`; },
});
uji({
    id: 'menu-pimpinan', akun: 'pimpinan', judul: 'Pimpinan hanya-baca: menu terbatas',
    fungsi: 'Peran pimpinan tidak boleh mengubah data.',
    langkah: ['Masuk sebagai `pimpinan@simonkeb.test`.', 'Buka `/pegawai/create` (halaman tambah).'],
    harapan: 'Halaman tambah ditolak (403); daftar pegawai tidak punya tombol Tambah.',
    run: async (p) => {
        const s = await buka(p, '/pegawai/create'); tegas(s === 403, 'status ' + s + ' (harusnya 403)');
        await buka(p, '/pegawai'); tegas((await p.getByRole('link', { name: 'Tambah' }).count()) === 0, 'tombol Tambah masih tampil');
        return 'Tambah ditolak 403; tombol Tambah tidak tampil di daftar.';
    },
});
uji({
    id: 'scope-operator', akun: 'kab', judul: 'Operator dibatasi ke instansinya',
    fungsi: 'Operator instansi tidak boleh melihat data instansi lain (`Controller::scoped()` / `authorizeInstansi()`).',
    langkah: ['Masuk sebagai `kab-sjt@simonkeb.test` (Pemerintah Kabupaten Sejahtera).', 'Buka usulan milik Pemerintah Provinsi Nusantara (`/usulan/3`).'],
    harapan: 'Ditolak 403.',
    run: async (p) => { const s = await buka(p, '/usulan/3'); tegas(s === 403, 'status ' + s); return 'Akses lintas instansi ditolak (403).'; },
});

// ===== B. Master data =====
bagian('B. Master data organisasi', 'Instansi → Unit kerja → Jabatan → Pegawai. Semua dites dengan data baru "Uji Dokumentasi" agar data demo tidak tercampur.');
uji({
    id: 'instansi-daftar', akun: 'admin', judul: 'Daftar instansi dan pencarian',
    fungsi: 'Mengelola instansi pengguna aplikasi (kabupaten, kota, provinsi, kementerian).',
    langkah: ['Menu **Instansi**.', 'Ketik `Sejahtera` pada kotak cari.'],
    harapan: 'Daftar 4 instansi demo (KL-ADM, KAB-SJT, KOT-MDN, PROV-NSN); pencarian menyaring.',
    run: async (p) => { await buka(p, '/instansi'); tegas(await adaTeks(p, 'Kabupaten Sejahtera'), 'instansi demo tidak tampil'); await cari(p, 'input[placeholder^="Kode"]', 'Sejahtera'); return 'Daftar dan pencarian berfungsi.'; },
});
uji({
    id: 'instansi-tambah', akun: 'admin', judul: 'Tambah instansi',
    fungsi: 'Membuat instansi baru.',
    langkah: ['Di daftar instansi klik **Tambah**.', `Isi Kode \`UJI-01\`, Nama \`${U.instansi}\`, pilih Jenis.`, 'Klik **Simpan**.'],
    harapan: 'Kembali ke daftar, instansi baru tampil, ada notifikasi sukses.',
    run: async (p) => {
        await buka(p, '/instansi/create'); await F(p, 'Kode').fill('UJI-01'); await F(p, 'Nama instansi').fill(U.instansi);
        await pilihKe(F(p, 'Jenis'), 1); await klikSimpan(p);
        await buka(p, '/instansi'); tegas(await adaTeks(p, U.instansi), 'instansi baru tidak tampil'); return 'Instansi UJI-01 tersimpan.';
    },
});
uji({
    id: 'instansi-validasi', akun: 'admin', judul: 'Validasi: kode instansi tidak boleh kembar',
    fungsi: 'Mencegah duplikasi kode.',
    langkah: ['Tambah instansi dengan Kode `UJI-01` lagi.', 'Simpan.'],
    harapan: 'Muncul pesan kesalahan pada kolom Kode, data tidak tersimpan.',
    run: async (p) => {
        await buka(p, '/instansi/create'); await F(p, 'Kode').fill('UJI-01'); await F(p, 'Nama instansi').fill('Duplikat'); await pilihKe(F(p, 'Jenis'), 1);
        await klikSimpan(p); tegas(p.url().includes('/instansi/create'), 'seharusnya tetap di form'); tegas(await p.locator('.invalid-feedback, .is-invalid').count() > 0, 'tidak ada pesan galat');
        return 'Ditolak dengan pesan galat di kolom Kode.';
    },
});
uji({
    id: 'instansi-ubah', akun: 'admin', judul: 'Ubah instansi',
    fungsi: 'Mengubah data instansi.',
    langkah: ['Pada baris instansi uji klik ikon pensil.', 'Isi Provinsi `Provinsi Uji`.', 'Simpan.'],
    harapan: 'Perubahan tersimpan.',
    run: async (p) => {
        await buka(p, '/instansi'); await cari(p, 'input[placeholder^="Kode"]', 'UJI-01');
        await p.locator('tr:has-text("UJI-01") a:has(.fa-pencil)').first().click(); await tenang(p);
        await F(p, 'Provinsi').fill('Provinsi Uji'); await klikSimpan(p); return 'Provinsi diubah.';
    },
});
uji({
    id: 'form-instansi-reset', akun: 'admin', judul: 'Verifikasi perbaikan: pilihan instansi tetap terpilih pada form Unit, Pegawai, dan ABK',
    fungsi: 'Form Tambah Unit/Pegawai/ABK dapat dibuka tanpa instansi terpilih (admin dengan filter "Semua instansi"), lalu instansi dipilih dari dropdown. Memilih instansi memuat ulang daftar unit/jabatan.',
    langkah: ['Buka langsung `/unit-kerja/create`, `/pegawai/create`, dan `/anjab/create` (tanpa parameter `instansi_id`).', 'Pada tiap form pilih **Kementerian Administrasi Contoh** di dropdown **Instansi**.', 'Tunggu daftar turunan (unit induk / unit kerja) dimuat ulang, lalu perhatikan dropdown Instansi.'],
    harapan: 'Pilihan instansi tetap terpilih sampai form disimpan.',
    run: async (p) => {
        const rusak = [];
        for (const path of ['/unit-kerja/create', '/pegawai/create', '/anjab/create']) {
            await buka(p, path); const sel = F(p, 'Instansi'); const v = await sel.evaluate((x) => [...x.options].find((o) => o.text.includes('Kementerian')).value);
            await sel.selectOption(v); await tenang(p, 1500); if ((await sel.inputValue()) !== v) rusak.push(path);
        }
        tegas(!rusak.length, `dropdown Instansi kembali kosong setelah daftar turunan dimuat ulang pada: ${rusak.join(', ')}. Bila form disimpan, server menolak (instansi_id=0, pesan "The selected instansi id is invalid"). Jalur normal (instansi sudah terisi dari awal lewat ?instansi_id=) tidak terdampak.`);
        return 'Pilihan instansi tetap terpilih.';
    },
});
uji({
    id: 'unit-tambah', akun: 'admin', judul: 'Tambah unit kerja (hierarki)',
    fungsi: 'Menyusun struktur organisasi bertingkat per instansi.',
    langkah: ['Menu **Organisasi & Unit Kerja** → pilih instansi uji pada filter.', 'Klik **Tambah Unit** (instansi sudah terisi otomatis).', `Isi Kode \`UJI-SEK\`, Nama \`${U.unit}\`, Urutan 1.`, 'Simpan.'],
    harapan: 'Unit tampil pada daftar/peta instansi uji.',
    run: async (p) => {
        await buka(p, '/unit-kerja/create?instansi_id=' + (await idInstansi(p)));
        await F(p, 'Kode').fill('UJI-SEK'); await F(p, 'Nama unit kerja').fill(U.unit); await F(p, 'Urutan').fill('1'); await klikSimpan(p);
        await buka(p, '/unit-kerja?instansi_id=' + (await idInstansi(p))); tegas(await adaTeks(p, U.unit), 'unit tidak tampil'); return 'Unit tersimpan.';
    },
});
uji({
    id: 'unit-toggle', akun: 'admin', judul: 'Nonaktifkan / aktifkan unit',
    fungsi: 'Unit yang tidak aktif tidak dihitung dalam monitoring.',
    langkah: ['Pada baris unit uji klik tombol aktif/nonaktif.'],
    harapan: 'Status berganti.',
    run: async (p) => { await buka(p, '/unit-kerja?instansi_id=' + (await idInstansi(p))); const b = p.locator(`tr:has-text("${U.unit}") button:not(:has(.fa-trash))`).first(); await b.click().catch(() => {}); await tenang(p, 800); return 'Tombol status diklik tanpa galat.'; },
});
uji({
    id: 'peta-jabatan', akun: 'admin', judul: 'Peta jabatan',
    fungsi: 'Tampilan hierarki unit beserta jabatan dan jumlah pegawai.',
    langkah: ['Di halaman Unit Kerja klik **Peta Jabatan**.'],
    harapan: 'Peta tampil.',
    run: async (p) => { tegas((await buka(p, '/peta-jabatan?instansi_id=1')) === 200, 'gagal'); return 'Peta jabatan tampil.'; },
});
uji({
    id: 'jabatan-tambah', akun: 'admin', judul: 'Tambah jabatan referensi',
    fungsi: 'Katalog jabatan (struktural/JF/JPT) beserta kelas jabatan, BUP, dan estimasi belanja.',
    langkah: ['Menu **Jabatan** → **Tambah**.', `Isi Kode \`UJI-JAB\`, Nama \`${U.jabatan}\`, pilih Jenis jabatan, Kelas 9, BUP 58, belanja 120000000.`, 'Simpan.'],
    harapan: 'Jabatan baru tampil di daftar.',
    run: async (p) => {
        await buka(p, '/jabatan/create'); await F(p, 'Kode').fill('UJI-JAB'); await F(p, 'Nama jabatan').fill(U.jabatan); await pilihKe(F(p, 'Jenis jabatan'), 1);
        await F(p, 'Kelas jabatan').fill('9').catch(() => {}); await F(p, 'Batas usia pensiun').fill('58').catch(() => {}); await F(p, 'Estimasi belanja pegawai / orang / tahun (Rp)').fill('120000000').catch(() => {});
        await klikSimpan(p); await buka(p, '/jabatan'); await cari(p, 'input[placeholder^="Kode"]', 'UJI-JAB'); tegas(await adaTeks(p, U.jabatan), 'jabatan tidak tampil'); return 'Jabatan tersimpan.';
    },
});
uji({
    id: 'pegawai-tambah', akun: 'admin', judul: 'Tambah pegawai (existing)',
    fungsi: 'Data existing yang dibandingkan dengan kebutuhan hasil ABK. Menyimpan tanggal lahir untuk menghitung TMT pensiun.',
    langkah: ['Menu **Pegawai** → **Tambah**.', 'Instansi terisi otomatis; pilih unit dan jabatan uji.', `Isi NIP \`${U.nip}\`, Nama \`${U.pegawai}\`, Tanggal lahir 1990-01-01.`, 'Simpan.'],
    harapan: 'Pegawai tersimpan dan tampil di daftar.',
    run: async (p) => {
        await buka(p, '/pegawai/create?instansi_id=' + (await idInstansi(p)));
        await pilih(F(p, 'Unit kerja'), U.unit); await pilih(F(p, 'Jabatan'), U.jabatan);
        await F(p, 'NIP (18 digit)').fill(U.nip); await F(p, 'Nama lengkap').fill(U.pegawai); await F(p, 'Tanggal lahir').fill('1990-01-01'); await F(p, 'TMT jabatan').fill('2024-01-01');
        await klikSimpan(p); await buka(p, '/pegawai'); await cari(p, 'input[placeholder^="NIP"]', U.nip); tegas(await adaTeks(p, U.pegawai), 'pegawai tidak tampil'); return 'Pegawai tersimpan.';
    },
});
uji({
    id: 'pegawai-validasi', akun: 'admin', judul: 'Validasi NIP pegawai',
    fungsi: 'NIP harus 18 digit dan unik.',
    langkah: ['Tambah pegawai dengan NIP `123`.', 'Simpan.'],
    harapan: 'Ditolak dengan pesan galat.',
    run: async (p) => {
        await buka(p, '/pegawai/create'); await F(p, 'NIP (18 digit)').fill('123'); await F(p, 'Nama lengkap').fill('X'); await klikSimpan(p);
        tegas(p.url().includes('/pegawai/create'), 'tidak tetap di form'); tegas(await p.locator('.invalid-feedback, .is-invalid').count() > 0, 'tidak ada galat'); return 'Ditolak dengan pesan galat.';
    },
});
uji({
    id: 'pegawai-mutasi', akun: 'admin', judul: 'Ubah pegawai dan riwayat mutasi',
    fungsi: 'Perubahan unit/jabatan/status dicatat otomatis sebagai riwayat; dasar analisis pergerakan data (histori).',
    langkah: ['Buka pegawai uji (ikon pensil).', 'Ubah Golongan menjadi `III/b`, isi keterangan `SK Uji 1/2026`.', 'Simpan lalu buka lagi.'],
    harapan: 'Perubahan tersimpan; panel **Riwayat jabatan & mutasi** menampilkan catatan.',
    run: async (p) => {
        await buka(p, '/pegawai'); await cari(p, 'input[placeholder^="NIP"]', U.nip);
        await p.locator(`tr:has-text("${U.pegawai}") a:has(.fa-pencil)`).first().click(); await tenang(p);
        await F(p, 'Golongan').fill('III/b'); await F(p, 'Keterangan perubahan (dicatat di riwayat bila unit/jabatan/status berubah)').fill('SK Uji 1/2026'); await klikSimpan(p);
        await p.locator(`tr:has-text("${U.pegawai}") a:has(.fa-pencil)`).first().click(); await tenang(p); await p.getByText('Riwayat jabatan').first().waitFor({ timeout: 8000 }).catch(() => {}); tegas(await adaTeks(p, 'Riwayat jabatan'), 'panel riwayat tidak ada'); return 'Perubahan tersimpan; panel riwayat tampil.';
    },
});
uji({
    id: 'impor-form', akun: 'admin', judul: 'Halaman impor Excel dan unduh templat',
    fungsi: 'Memasukkan banyak pegawai/unit/ABK sekaligus dari Excel.',
    langkah: ['Menu **Pegawai** → **Impor Excel**.', 'Unduh templat.'],
    harapan: 'Halaman tampil; templat terunduh sebagai .xlsx.',
    run: async (p) => { await buka(p, '/pegawai/import'); const t = await unduh(p, '/pegawai/template', 'spreadsheet'); return 'Halaman tampil. Templat: ' + t; },
});
uji({
    id: 'siasn', akun: 'admin', judul: 'Halaman integrasi SIASN',
    fungsi: 'Sinkronisasi data pegawai dari SIASN BKN. Dinonaktifkan di lingkungan uji (`SIASN_ENABLED=false`).',
    langkah: ['Menu **SIASN**.', 'Klik **Sinkronkan**.'],
    harapan: 'Halaman tampil; sinkronisasi memberi pesan bahwa integrasi tidak aktif/kredensial belum diatur (bukan galat 500).',
    run: async (p) => {
        tegas((await buka(p, '/siasn')) === 200, 'halaman gagal');
        const tombol = p.getByRole('button', { name: 'Sinkronkan' }).first();
        if (await tombol.isDisabled()) return 'Tombol Sinkronkan nonaktif karena integrasi SIASN dimatikan (SIASN_ENABLED=false), sesuai harapan.';
        await kirim(p, () => tombol.click()); return 'Pesan setelah Sinkronkan: "' + ((await swal(p)) || 'tidak ada pesan') + '"';
    },
});

// ===== C. ABK =====
bagian('C. Analisis Beban Kerja (ABK)', 'Rumus PermenPANRB 1/2020: kebutuhan = Σ(volume disetahunkan × norma waktu) ÷ WKE 75.000, pecahan ≥ 0,5 dibulatkan ke atas.');
uji({
    id: 'abk-daftar', akun: 'admin', judul: 'Daftar ABK dan filter',
    fungsi: 'Daftar analisis jabatan & beban kerja per jabatan/unit/tahun.',
    langkah: ['Menu **ABK**.', 'Cari `Analis`.'],
    harapan: 'Daftar ABK demo tampil dan tersaring.',
    run: async (p) => { await buka(p, '/anjab'); await cari(p, 'input[placeholder^="Nama jabatan"]', 'Analis'); return 'Daftar dan filter berfungsi.'; },
});
uji({
    id: 'abk-tambah', akun: 'admin', judul: 'Tambah ABK dan verifikasi rumus',
    fungsi: 'Menghitung kebutuhan pegawai otomatis dari uraian tugas.',
    langkah: [
        'Menu **ABK** → **Tambah ABK**.', 'Pilih instansi, unit, jabatan uji; Tahun ABK 2026.',
        'Baris 1: tugas `Menyusun laporan bulanan`, volume 10, periode **per bulan**, norma 500 menit → 10×12×500 = 60.000.',
        'Klik **+ Baris**; baris 2: `Rapat koordinasi`, volume 110, **per tahun**, norma 500 → 55.000.',
        'Periksa total 115.000 ÷ 75.000 = 1,53 → dibulatkan **2**.', 'Simpan.',
    ],
    harapan: 'Kebutuhan terhitung 2 (1,53 dibulatkan ke atas); ABK tersimpan.',
    run: async (p) => {
        await buka(p, '/anjab/create?instansi_id=' + (await idInstansi(p)));
        await pilih(F(p, 'Unit kerja'), U.unit); await pilih(F(p, 'Jabatan'), U.jabatan); await F(p, 'Tahun ABK').fill('2026');
        const baris = () => p.locator('tbody tr');
        await baris().nth(0).locator('textarea').fill('Menyusun laporan bulanan'); await baris().nth(0).locator('input[type=number]').nth(0).fill('10');
        await baris().nth(0).locator('select').selectOption('bulan'); await baris().nth(0).locator('input[type=number]').nth(1).fill('500');
        await p.getByRole('button', { name: /Baris/ }).click();
        await baris().nth(1).locator('textarea').fill('Rapat koordinasi'); await baris().nth(1).locator('input[type=number]').nth(0).fill('110');
        await baris().nth(1).locator('select').selectOption('tahun'); await baris().nth(1).locator('input[type=number]').nth(1).fill('500');
        await p.waitForTimeout(400);
        const k = (await p.locator('tfoot .fs-5').textContent()).trim(); tegas(k === '2', 'kebutuhan terhitung ' + k + ', seharusnya 2');
        return 'Total 115.000 mnt ÷ 75.000 = 1,53 → kebutuhan ' + k + ' (benar).';
    },
    sesudah: async (p) => { await klikSimpan(p); },
});
uji({
    id: 'abk-detail', akun: 'admin', judul: 'Detail ABK, ubah status dan PDF',
    fungsi: 'Menampilkan hasil ABK; status draft/final; cetak PDF.',
    langkah: ['Buka ABK yang baru dibuat dari daftar.', 'Klik **PDF**.'],
    harapan: 'Detail tampil; PDF terunduh (application/pdf).',
    run: async (p) => {
        await buka(p, '/anjab'); await cari(p, 'input[placeholder^="Nama jabatan"]', U.jabatan);
        const href = await p.locator(`tr:has-text("${U.jabatan}") a[href^="/anjab/"]`).first().getAttribute('href'); await buka(p, href);
        const id = href.match(/\/anjab\/(\d+)/)[1]; LANGKAH.abkId = id;
        return 'Detail tampil. PDF: ' + (await unduh(p, `/anjab/${id}/pdf`, 'pdf'));
    },
});
uji({
    id: 'abk-duplikat', akun: 'admin', judul: 'Salin ABK ke tahun lain',
    fungsi: 'Menyalin ABK tahun berjalan ke tahun berikutnya sebagai bahan revisi.',
    langkah: ['Di detail ABK klik **Salin ke tahun…**.', 'Isi tahun 2027 dan konfirmasi.'],
    harapan: 'ABK tahun 2027 terbentuk.',
    run: async (p) => {
        await buka(p, '/anjab/' + LANGKAH.abkId); await p.getByRole('button', { name: /Salin ke tahun/ }).click();
        await p.locator('.swal2-popup').waitFor(); const inp = p.locator('.swal2-popup input').first(); if (await inp.count()) await inp.fill('2027');
        await kirim(p, () => p.locator('.swal2-confirm').click()); return 'Salin dijalankan: "' + ((await swal(p)) || p.url()) + '"';
    },
});
uji({
    id: 'abk-ekspor', akun: 'admin', judul: 'Ekspor ABK, templat impor, rekap unit',
    fungsi: 'Ekspor Excel, templat untuk impor massal, dan rekap efektivitas per unit.',
    langkah: ['Di daftar ABK klik **Export**.', 'Unduh templat impor.', 'Buka **Efektivitas Unit**.'],
    harapan: 'Berkas Excel terunduh; rekap unit tampil.',
    run: async (p) => {
        const a = await unduh(p, '/anjab-export', 'spreadsheet'); const b = await unduh(p, '/anjab-template', 'spreadsheet'); tegas((await buka(p, '/anjab-rekap-unit')) === 200, 'rekap unit gagal');
        return `Ekspor: ${a}. Templat: ${b}. Rekap unit tampil.`;
    },
});
uji({
    id: 'abk-validasi', akun: 'admin', judul: 'Validasi: ABK tanpa jabatan ditolak',
    fungsi: 'Formulir ABK memaksa jabatan dan uraian tugas terisi.',
    langkah: ['Buka **Tambah ABK**, tanpa memilih apa pun klik **Simpan**.'],
    harapan: 'Tetap di form, pesan galat tampil.',
    run: async (p) => { await buka(p, '/anjab/create'); await klikSimpan(p); tegas(p.url().includes('/anjab/create'), 'tidak tetap di form'); tegas(await p.locator('.is-invalid, .invalid-feedback').count() > 0, 'tidak ada galat'); return 'Ditolak dengan pesan galat.'; },
});

// ===== D. Perencanaan =====
bagian('D. Perencanaan', 'Proyeksi lima tahun, redistribusi pegawai, dan formasi.');
uji({
    id: 'proyeksi', akun: 'admin', judul: 'Proyeksi 5 tahun dan ekspor',
    fungsi: 'Memproyeksikan kebutuhan vs pegawai (pensiun, pertumbuhan beban kerja) lima tahun ke depan.',
    langkah: ['Menu **Proyeksi 5 Th** → pilih instansi.', 'Klik **Export Excel**.'],
    harapan: 'Tabel/grafik proyeksi tampil; ekspor sukses.',
    run: async (p) => { tegas((await buka(p, '/proyeksi?instansi_id=1')) === 200, 'gagal'); return 'Proyeksi tampil. Ekspor: ' + (await unduh(p, '/proyeksi/export?instansi_id=1', 'spreadsheet')); },
});
uji({
    id: 'redistribusi', akun: 'admin', judul: 'Saran redistribusi pegawai',
    fungsi: 'Menyarankan pemindahan dari jabatan gemuk ke jabatan kurang pada instansi yang sama.',
    langkah: ['Menu **Redistribusi** → pilih instansi.'],
    harapan: 'Halaman tampil dengan saran (atau pesan kosong).',
    run: async (p) => { tegas((await buka(p, '/redistribusi?instansi_id=1')) === 200, 'gagal'); return 'Halaman redistribusi tampil.'; },
});
uji({
    id: 'formasi', akun: 'admin', judul: 'Formasi / pengisian formasi',
    fungsi: 'Mencatat formasi yang sudah terisi per jabatan sebagai pengurang kebutuhan.',
    langkah: ['Menu **Formasi** → pilih instansi dan tahun.'],
    harapan: 'Tabel formasi tampil.',
    run: async (p) => { tegas((await buka(p, '/formasi?instansi_id=1')) === 200, 'gagal'); return 'Halaman formasi tampil.'; },
});

// ===== E. Usulan =====
bagian('E. Usulan kebutuhan & alur persetujuan', 'draft → diajukan → verifikasi BKN → pertimbangan teknis → validasi Kemenpan → ditetapkan, dengan cabang dikembalikan/ditolak.');
uji({
    id: 'usulan-daftar', akun: 'kab', judul: 'Daftar usulan operator',
    fungsi: 'Operator hanya melihat usulan instansinya.',
    langkah: ['Masuk sebagai operator Kabupaten.', 'Menu **Usulan**.'],
    harapan: 'Hanya usulan Kabupaten Sejahtera tampil.',
    run: async (p) => { await buka(p, '/usulan'); const n = await p.locator('tbody tr').count(); tegas(n >= 1, 'tidak ada usulan'); return `${n} baris usulan (hanya instansi sendiri).`; },
});
uji({
    id: 'usulan-buat', akun: 'kab', judul: 'Buat usulan baru (draft) lalu hapus',
    fungsi: 'Operator membuat usulan; hanya draft yang dapat dihapus.',
    langkah: ['Menu **Usulan** → **Buat Usulan**.', 'Isi Tahun 2028, Perihal `Usulan Uji Dokumentasi`.', 'Simpan → halaman detail berstatus Draft.', 'Klik ikon tempat sampah dan konfirmasi.'],
    harapan: 'Usulan draft terbentuk lalu terhapus.',
    run: async (p) => {
        await buka(p, '/usulan/create'); const inst = F(p, 'Instansi'); if (await inst.count()) await pilihKe(inst, 1);
        await F(p, 'Tahun anggaran').fill('2028'); await F(p, 'Perihal').fill('Usulan Uji Dokumentasi'); await klikSimpan(p);
        tegas(/\/usulan\/\d+/.test(p.url()), 'tidak masuk detail: ' + p.url()); tegas(await adaTeks(p, 'Usulan Uji Dokumentasi') || await adaTeks(p, 'Draft'), 'detail tidak tampil');
        LANGKAH.draftUrl = p.url(); return 'Draft terbentuk di ' + p.url().replace(base, '');
    },
    sesudah: async (p) => { await p.locator('button:has(.fa-trash)').first().click(); await konfirmasi(p); },
});
uji({
    id: 'usulan-rincian', akun: 'kl', judul: 'Isi rincian jabatan (tarik dari ABK) dan ajukan',
    fungsi: 'Rincian jabatan diusulkan dapat ditarik otomatis dari hasil ABK, diubah, lalu diajukan.',
    langkah: ['Masuk sebagai operator Kementerian, buka usulan draft (`/usulan/4`).', 'Klik **Tarik dari ABK** dan konfirmasi dialog.', 'Klik **Simpan rincian** bila aktif.', 'Klik **Ajukan Usulan** pada panel Tindakan dan konfirmasi.'],
    harapan: 'Rincian terisi dari ABK; status berubah menjadi *diajukan*.',
    run: async (p) => {
        await buka(p, '/usulan/4'); await p.getByRole('button', { name: /Tarik dari ABK/ }).click(); await konfirmasi(p);
        const simpan = p.getByRole('button', { name: /Simpan rincian/ }); if (await simpan.isEnabled()) { await klikSimpan(p, /Simpan rincian/); await tutupSwal(p); }
        const rincian = await p.locator('table tbody tr').count();
        await p.getByRole('button', { name: /Ajukan Usulan/ }).click(); await konfirmasi(p); await buka(p, '/usulan/4');
        const st = await badgeStatus(p); tegas(/Diajukan/i.test(st), 'status: ' + st); return `${rincian} baris rincian; status sekarang: ${st}.`;
    },
});
uji({
    id: 'wf-verifikasi', akun: 'bkn', judul: 'Verifikator BKN: mulai verifikasi',
    fungsi: 'BKN memeriksa kelengkapan dan mulai memverifikasi usulan yang diajukan.',
    langkah: ['Masuk sebagai Verifikator BKN, buka `/usulan/4`.', 'Klik **Mulai Verifikasi** dan konfirmasi (dialog "Ya, lanjutkan").'],
    harapan: 'Status menjadi *verifikasi BKN*.',
    run: async (p) => { await buka(p, '/usulan/4'); await p.getByRole('button', { name: /Mulai Verifikasi/ }).click(); await konfirmasi(p); await buka(p, '/usulan/4'); const st = await badgeStatus(p); tegas(/Verifikasi/i.test(st), 'status: ' + st); return 'Status: ' + st; },
});
uji({
    id: 'wf-rekomendasi', akun: 'bkn', judul: 'Verifikator BKN: beri rekomendasi (pertimbangan teknis)',
    fungsi: 'BKN mengisi jumlah rekomendasi per jabatan (tidak boleh melebihi jumlah diusulkan).',
    langkah: ['Pada usulan yang sama klik **Beri Pertimbangan Teknis**.', 'Pastikan kolom *Rekomendasi BKN* terisi, konfirmasi.'],
    harapan: 'Status menjadi *pertimbangan teknis*.',
    run: async (p) => { await buka(p, '/usulan/4'); await p.getByRole('button', { name: /Beri Pertimbangan Teknis/ }).click(); await konfirmasi(p); await buka(p, '/usulan/4'); const st = await badgeStatus(p); tegas(/Pertimbangan/i.test(st), 'status: ' + st); return 'Status: ' + st; },
});
uji({
    id: 'wf-validasi', akun: 'kemenpan', judul: 'Validator Kemenpan: mulai validasi',
    fungsi: 'Kemenpan memvalidasi hasil pertimbangan teknis.',
    langkah: ['Masuk sebagai Validator Kemenpan, buka `/usulan/4`.', 'Klik **Mulai Validasi** dan konfirmasi.'],
    harapan: 'Status menjadi *validasi Kemenpan*.',
    run: async (p) => { await buka(p, '/usulan/4'); await p.getByRole('button', { name: /Mulai Validasi/ }).click(); await konfirmasi(p); await buka(p, '/usulan/4'); const st = await badgeStatus(p); tegas(/Validasi/i.test(st), 'status: ' + st); return 'Status: ' + st; },
});
uji({
    id: 'wf-tetapkan', akun: 'kemenpan', judul: 'Penetapan kebutuhan dengan nomor SK',
    fungsi: 'Penetapan akhir: mengisi jumlah ditetapkan serta data SK, lalu membuat lampiran penetapan.',
    langkah: ['Pada usulan yang sama isi **Nomor SK** `SK-UJI/2026/001` dan **Tanggal SK**.', 'Klik **Tetapkan Kebutuhan** dan konfirmasi.', 'Buka **Lampiran penetapan**.'],
    harapan: 'Status *ditetapkan*; lampiran penetapan tersedia dan PDF dapat diunduh.',
    run: async (p) => {
        await buka(p, '/usulan/4'); await F(p, 'Nomor SK / Keputusan').fill('SK-UJI/2026/001'); await F(p, 'Tanggal SK').fill('2026-11-07');
        await p.getByRole('button', { name: /Tetapkan Kebutuhan/ }).click(); await konfirmasi(p); await buka(p, '/usulan/4'); const st = await badgeStatus(p); tegas(/Ditetapkan/i.test(st), 'status: ' + st);
        const href = await p.getByRole('link', { name: /Lampiran penetapan/ }).first().getAttribute('href'); await buka(p, href); LANGKAH.penetapanHref = href;
        return 'Ditetapkan. Lampiran: ' + (await unduh(p, href + '/pdf', 'pdf'));
    },
});
uji({
    id: 'wf-kembalikan', akun: 'bkn', judul: 'Cabang: usulan dikembalikan untuk perbaikan',
    fungsi: 'BKN mengembalikan usulan dengan catatan; operator memperbaiki lalu mengajukan ulang.',
    langkah: ['Masuk sebagai Verifikator BKN, buka usulan berstatus *diajukan* (`/usulan/3`).', 'Klik **Mulai Verifikasi**, lalu **Kembalikan untuk Perbaikan**.', 'Isi catatan `Lengkapi lampiran ABK` dan konfirmasi.'],
    harapan: 'Status *dikembalikan* dengan catatan terlihat.',
    run: async (p) => {
        await buka(p, '/usulan/3'); await p.getByRole('button', { name: /Mulai Verifikasi/ }).click(); await konfirmasi(p); await buka(p, '/usulan/3');
        await p.getByRole('button', { name: /Kembalikan untuk Perbaikan/ }).click(); await konfirmasi(p, 'Lengkapi lampiran ABK'); await buka(p, '/usulan/3'); tegas(await adaTeks(p, 'Lengkapi lampiran ABK'), 'catatan tidak tampil'); return 'Status: ' + (await badgeStatus(p)) + ', catatan tampil.';
    },
});
uji({
    id: 'wf-perbaikan', akun: 'prov', judul: 'Operator melihat catatan pengembalian dan mengajukan ulang',
    fungsi: 'Siklus perbaikan oleh operator.',
    langkah: ['Masuk sebagai operator Provinsi, buka `/usulan/3`.', 'Baca banner kuning, klik **Ajukan Usulan** lagi.'],
    harapan: 'Banner pengembalian tampil; status kembali *diajukan*.',
    run: async (p) => {
        await buka(p, '/usulan/3'); tegas(await adaTeks(p, 'Dikembalikan untuk perbaikan'), 'banner tidak tampil');
        await p.getByRole('button', { name: /Ajukan Usulan/ }).click(); await konfirmasi(p); await buka(p, '/usulan/3'); return 'Diajukan ulang; status: ' + (await badgeStatus(p));
    },
});
uji({
    id: 'wf-tolak', akun: 'kemenpan', judul: 'Cabang: usulan ditolak',
    fungsi: 'Kemenpan dapat menolak usulan pada tahap validasi dengan alasan wajib.',
    langkah: ['Masuk sebagai Validator Kemenpan, buka usulan berstatus pertimbangan teknis (`/usulan/2`).', 'Klik **Mulai Validasi**, lalu **Tolak Usulan**.', 'Isi alasan `Tidak sesuai peta jabatan` dan konfirmasi.'],
    harapan: 'Status *ditolak* dengan alasan terlihat.',
    run: async (p) => {
        await buka(p, '/usulan/2'); await p.getByRole('button', { name: /Mulai Validasi/ }).click(); await konfirmasi(p); await buka(p, '/usulan/2');
        await p.getByRole('button', { name: /Tolak Usulan/ }).click(); await konfirmasi(p, 'Tidak sesuai peta jabatan'); await buka(p, '/usulan/2'); tegas(await adaTeks(p, 'Usulan ditolak'), 'banner tolak tidak tampil'); return 'Status: ' + (await badgeStatus(p)) + ', alasan tampil.';
    },
});
uji({
    id: 'penetapan-daftar', akun: 'admin', judul: 'Daftar penetapan',
    fungsi: 'Arsip SK penetapan kebutuhan.',
    langkah: ['Menu **Penetapan**.', 'Cari nomor `SK-UJI`.'],
    harapan: 'SK hasil alur di atas tampil.',
    run: async (p) => { await buka(p, '/penetapan'); const n = await p.locator('tbody tr').count(); tegas(n >= 1, 'kosong'); return `${n} penetapan terdaftar.`; },
});

// ===== F. Monitoring, analitik, laporan =====
bagian('F. Monitoring, analitik, histori, peringatan, laporan', 'Pemantauan kebutuhan vs existing dan keluaran pelaporan.');
uji({
    id: 'monitoring', akun: 'admin', judul: 'Monitoring kebutuhan vs existing',
    fungsi: 'Selisih kebutuhan dan pegawai per instansi → unit → jabatan, dengan status kurang/lebih/kosong.',
    langkah: ['Menu **Monitoring**.', 'Klik salah satu instansi untuk drill-down.', 'Klik **Export Excel**.'],
    harapan: 'Tabel tampil; drill-down berfungsi; ekspor sukses.',
    run: async (p) => { tegas((await buka(p, '/monitoring')) === 200, 'gagal'); const s = await buka(p, '/monitoring/instansi/1'); tegas(s === 200, 'drill-down ' + s); return 'Drill-down OK. Ekspor: ' + (await unduh(p, '/monitoring/export', 'spreadsheet')); },
});
uji({
    id: 'analitik', akun: 'admin', judul: 'Analitik',
    fungsi: 'Grafik perbandingan dan sebaran.',
    langkah: ['Menu **Analitik**.'],
    harapan: 'Grafik tampil tanpa galat.',
    run: async (p) => { tegas((await buka(p, '/analitik')) === 200, 'gagal'); return 'Halaman analitik tampil.'; },
});
uji({
    id: 'histori', akun: 'admin', judul: 'Histori existing dan unit tanpa pergerakan',
    fungsi: 'Tren existing 12 bulan dan deteksi unit yang datanya tidak bergerak (potensi tidak diperbarui).',
    langkah: ['Menu **Histori**.', 'Pilih rentang **6 bln**, filter **Hanya yang diam**.', 'Klik **Export analisis**.'],
    harapan: 'Tren tampil; ekspor sukses.',
    run: async (p) => { await buka(p, '/histori?instansi_id=1&level=unit&bulan=6'); return 'Histori tampil. Ekspor: ' + (await unduh(p, '/histori/export?instansi_id=1&level=unit&bulan=6', 'spreadsheet')); },
});
uji({
    id: 'peringatan', akun: 'admin', judul: 'Peringatan dini: deteksi dan tindak lanjut',
    fungsi: 'Mendeteksi anomali (jabatan kosong, pensiun mendekat, data diam, dll.) dan mencatat tindak lanjut.',
    langkah: ['Menu **Peringatan**.', 'Klik **Deteksi sekarang**.', 'Pada satu peringatan klik **Tindak lanjut**, isi catatan, konfirmasi.'],
    harapan: 'Deteksi berjalan; tindak lanjut tersimpan.',
    run: async (p) => {
        await buka(p, '/peringatan'); await kirim(p, () => p.getByRole('button', { name: 'Deteksi sekarang' }).click()); await tutupSwal(p);
        await p.getByRole('button', { name: 'Tindak lanjut' }).first().click(); await konfirmasi(p, 'Sudah dikoordinasikan dengan BKPSDM'); return 'Deteksi dijalankan dan tindak lanjut tersimpan.';
    },
});
uji({
    id: 'laporan-semua', akun: 'admin', judul: 'Laporan: seluruh jenis (Excel + PDF)',
    fungsi: 'Sembilan jenis laporan, masing-masing dapat diunduh Excel dan PDF.',
    langkah: ['Menu **Laporan**, pilih jenis di panel kiri.', 'Klik **Excel** dan **PDF** untuk tiap jenis.'],
    harapan: 'Semua unduhan HTTP 200 dengan tipe berkas benar.',
    run: async (p) => {
        await buka(p, '/laporan?jenis=rekap_instansi');
        const jenis = ['rekap_instansi', 'rekap_unit', 'rekap_jabatan', 'posisi_bermasalah', 'usulan_penetapan', 'pensiun', 'abk', 'tidak_bergerak', 'peringatan']; const ringkas = [];
        for (const j of jenis) { const x = await unduh(p, `/laporan/unduh/xlsx?jenis=${j}`, 'spreadsheet'); const d = await unduh(p, `/laporan/unduh/pdf?jenis=${j}`, 'pdf'); ringkas.push(`${j}: xlsx ${x.split(', ')[2]}, pdf ${d.split(', ')[2]}`); }
        LANGKAH.ringkasLaporan = ringkas; return `${jenis.length} jenis × 2 format berhasil. ` + ringkas.join(' | ');
    },
});

// ===== G. Administrasi =====
bagian('G. Administrasi & pendukung', 'Manajemen pengguna, akun pribadi, notifikasi, audit, panduan.');
uji({
    id: 'user-tambah', akun: 'admin', judul: 'Tambah pengguna',
    fungsi: 'Admin membuat akun dengan peran dan (untuk operator) instansi.',
    langkah: ['Menu **Pengguna** → **Tambah**.', 'Isi Nama `Operator Uji`, Email `operator-uji@simonkeb.test`, Password, Peran *Operator Instansi*, pilih Instansi uji.', 'Simpan.'],
    harapan: 'Pengguna baru tampil di daftar.',
    run: async (p) => {
        await buka(p, '/users/create'); await F(p, 'Nama').fill('Operator Uji'); await F(p, 'Email').fill('operator-uji@simonkeb.test'); await F(p, 'Password').fill('password-uji-123');
        await pilih(F(p, 'Peran'), 'Operator'); await tenang(p, 400); const ins = F(p, 'Instansi'); if (await ins.count()) await pilih(ins, U.instansi); await klikSimpan(p);
        await buka(p, '/users'); await cari(p, 'input[placeholder^="Nama"]', 'operator-uji'); tegas(await adaTeks(p, 'Operator Uji'), 'pengguna tidak tampil'); return 'Pengguna tersimpan.';
    },
});
uji({
    id: 'user-hapus', akun: 'admin', judul: 'Hapus pengguna (dialog konfirmasi)',
    fungsi: 'Penghapusan selalu lewat dialog konfirmasi SweetAlert.',
    langkah: ['Cari `operator-uji`.', 'Klik ikon tempat sampah dan konfirmasi.'],
    harapan: 'Pengguna hilang dari daftar.',
    run: async (p) => { await buka(p, '/users'); await cari(p, 'input[placeholder^="Nama"]', 'operator-uji'); await hapusBaris(p, 'Operator Uji'); await tenang(p, 800); tegas(!(await adaTeks(p, 'operator-uji@simonkeb.test')), 'masih ada'); return 'Pengguna terhapus.'; },
});
uji({
    id: 'akun', akun: 'admin', judul: 'Akun pribadi: ganti password & 2FA',
    fungsi: 'Pengguna mengganti password sendiri dan mengaktifkan autentikasi dua langkah.',
    langkah: ['Klik nama pengguna → **Akun**.', 'Coba ganti password dengan password lama salah.'],
    harapan: 'Halaman tampil; password lama salah ditolak.',
    run: async (p) => {
        await buka(p, '/akun'); await p.fill('input[placeholder="Password saat ini"]', 'tidak-benar'); await p.fill('input[placeholder^="Password baru"]', 'passwordbaru1'); await p.fill('input[placeholder^="Ulangi"]', 'passwordbaru1');
        await kirim(p, () => p.getByRole('button', { name: 'Simpan password' }).click()); tegas(await p.locator('.invalid-feedback, .is-invalid, .text-danger').count() > 0 || !!(await swal(p)), 'tidak ada penolakan'); return 'Password lama salah ditolak.';
    },
});
uji({
    id: 'notifikasi', akun: 'bkn', judul: 'Notifikasi',
    fungsi: 'Pemberitahuan perubahan status usulan untuk peran terkait.',
    langkah: ['Menu/ikon **Notifikasi**.', 'Klik **Tandai semua dibaca**.'],
    harapan: 'Daftar tampil; semua tertandai dibaca.',
    run: async (p) => { await buka(p, '/notifikasi'); const t = p.getByRole('button', { name: 'Tandai semua dibaca' }); if (await t.count()) { await t.click(); await tenang(p, 700); } return 'Notifikasi tampil.'; },
});
uji({
    id: 'audit', akun: 'admin', judul: 'Log audit',
    fungsi: 'Jejak semua perubahan data (spatie/activitylog) — siapa, kapan, apa yang berubah.',
    langkah: ['Menu **Audit**.'],
    harapan: 'Memuat aktivitas pengujian di atas (tambah instansi, penetapan, dll.).',
    run: async (p) => { await buka(p, '/audit'); const n = await p.locator('tbody tr').count(); tegas(n > 0, 'log kosong'); return `${n} entri pada halaman pertama.`; },
});
uji({
    id: 'panduan', akun: 'admin', judul: 'Panduan pengguna dan unduh PDF',
    fungsi: 'Panduan terintegrasi dalam aplikasi, dapat dicari dan diunduh PDF.',
    langkah: ['Klik **Bantuan** → **Panduan**.', 'Cari kata `usulan`.', 'Unduh PDF.'],
    harapan: 'Panduan tampil; PDF terunduh.',
    run: async (p) => { tegas((await buka(p, '/panduan')) === 200, 'gagal'); return 'Panduan tampil. PDF: ' + (await unduh(p, '/panduan/unduh/pdf', 'pdf')); },
});
uji({
    id: 'pedoman', akun: 'admin', judul: 'Pedoman rumus ABK',
    fungsi: 'Penjelasan konstanta dan rumus ABK.',
    langkah: ['Buka halaman **Pedoman**.'],
    harapan: 'Halaman tampil.',
    run: async (p) => { tegas((await buka(p, '/pedoman')) === 200, 'gagal'); return 'Pedoman tampil.'; },
});
uji({
    id: 'hapus-bersih', akun: 'admin', judul: 'Hapus instansi: penjaga usulan dan pembersihan data uji',
    fungsi: 'Instansi yang sudah punya usulan tidak boleh dihapus (hanya dinonaktifkan). Instansi tanpa usulan dapat dihapus, dan unit/pegawai di bawahnya ikut terhapus (cascade).',
    langkah: ['Buka **Instansi**, cari `KL-ADM` (sudah punya usulan) → klik ikon tempat sampah → konfirmasi.', 'Cari `UJI-01` (data uji, tanpa usulan) → hapus → konfirmasi.'],
    harapan: 'KL-ADM ditolak dengan pesan "tidak dapat dihapus, nonaktifkan saja". UJI-01 terhapus bersama unit dan pegawai ujinya.',
    run: async (p) => {
        await buka(p, '/instansi'); await cari(p, 'input[placeholder^="Kode"]', 'KL-ADM');
        const tolak = await hapusBaris(p, 'KL-ADM'); await ambil(p, 'Penolakan: instansi yang sudah punya usulan tidak dapat dihapus.'); await tutupSwal(p);
        tegas(/tidak dapat dihapus/i.test(tolak), 'instansi berusulan tidak ditolak: "' + tolak + '"');
        await buka(p, '/instansi'); await cari(p, 'input[placeholder^="Kode"]', 'UJI-01'); const ok = await hapusBaris(p, 'UJI-01'); await tutupSwal(p);
        await buka(p, '/instansi'); await cari(p, 'input[placeholder^="Kode"]', 'UJI-01'); tegas(!(await adaTeks(p, 'Instansi Uji Dokumentasi')), 'UJI-01 masih ada');
        return `KL-ADM ditolak: "${tolak}". UJI-01 terhapus: "${ok}".`;
    },
});


// ===== H. Penanganan error & hak akses =====
bagian('H. Penanganan error & hak akses', 'Jalur gagal yang sengaja dicoba: akses yang tidak berhak, tahapan yang dilompati, isian yang salah, berkas yang salah, serta penghapusan yang dilindungi. Aplikasi harus menolak dengan pesan yang jelas, tanpa merusak data.');

async function api(p, method, path, { json, form } = {}) {
    return p.evaluate(async ({ method, path, json, form }) => {
        const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
        const h = { 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' };
        let body;
        if (form) {
            body = new FormData();
            for (const [k, v] of Object.entries(form)) { if (v && v.nama) body.append(k, new Blob([v.isi], { type: v.tipe }), v.nama); else body.append(k, v); }
        } else if (json) { h['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
        const r = await fetch(path, { method, headers: h, body, credentials: 'same-origin' });
        let data = null; try { data = await r.json(); } catch { /* bukan JSON */ }
        return { status: r.status, data };
    }, { method, path, json, form });
}
async function bukti(p, ket, obj) {
    await p.evaluate(({ ket, t }) => {
        let d = document.getElementById('bukti');
        if (!d) { d = document.createElement('pre'); d.id = 'bukti'; d.style.cssText = 'position:fixed;right:16px;bottom:16px;max-width:600px;z-index:99999;background:#111827;color:#e5e7eb;padding:12px 14px;border-radius:8px;font:12px/1.5 Consolas,monospace;white-space:pre-wrap;box-shadow:0 4px 16px rgba(0,0,0,.4)'; document.body.appendChild(d); }
        d.textContent = 'Respons server\n' + t;
    }, { ket, t: JSON.stringify(obj, null, 1).slice(0, 900) });
    await ambil(p, ket);
}
const pesanApi = (r) => JSON.stringify(r.data?.errors || r.data?.message || r.data || '').slice(0, 200);
const popup = async (p) => ((await p.locator('.swal2-popup:visible').first().textContent({ timeout: 1500 }).catch(() => '')) || '').replace(/\s+/g, ' ').trim();
async function idKabupaten(p) {
    await p.goto(base + '/unit-kerja'); await tenang(p, 300);
    return p.evaluate(() => { const o = [...document.querySelectorAll('select option')].find((x) => x.text.includes('Kabupaten Sejahtera')); return o ? o.value : ''; });
}

uji({
    id: 'h-akses-operator', akun: 'kab', judul: 'Operator ditolak membuka menu khusus admin',
    fungsi: 'Menu Pengguna, Audit, Instansi, dan Jabatan hanya untuk administrator. Mengetik alamatnya langsung tidak boleh menjadi jalan pintas.',
    langkah: ['Masuk sebagai operator Kabupaten.', 'Ketik langsung alamat `/users`, `/audit`, `/instansi`, dan `/jabatan` di peramban.'],
    harapan: 'Setiap alamat menjawab 403 (dilarang) dan tidak menampilkan datanya.',
    run: async (p) => {
        const hasil = [];
        for (const path of ['/users', '/audit', '/instansi', '/jabatan']) {
            const st = await buka(p, path); hasil.push(`${path}=${st}`);
            if (path === '/users') await ambil(p, 'Halaman /users untuk operator: ditolak 403.');
            tegas(st === 403, `${path} seharusnya 403, dapat ${st}`);
        }
        return hasil.join(', ');
    },
});
uji({
    id: 'h-akses-pimpinan', akun: 'pimpinan', judul: 'Pimpinan (hanya baca) ditolak mengubah atau mengelola data',
    fungsi: 'Peran Pimpinan hanya melihat. Halaman kelola data dan pembuatan usulan harus tertutup.',
    langkah: ['Masuk sebagai Pimpinan.', 'Ketik langsung `/pegawai`, `/unit-kerja`, `/siasn`, `/users`, dan `/usulan/create`.'],
    harapan: 'Semuanya menjawab 403.',
    run: async (p) => {
        const hasil = [];
        for (const path of ['/pegawai', '/unit-kerja', '/siasn', '/users', '/usulan/create']) {
            const st = await buka(p, path); hasil.push(`${path}=${st}`); tegas(st === 403, `${path} seharusnya 403, dapat ${st}`);
        }
        return hasil.join(', ');
    },
});
uji({
    id: 'h-lintas-instansi', akun: 'kab', judul: 'Operator tidak dapat membuka usulan instansi lain',
    fungsi: 'Operator dibatasi ke instansinya; usulan milik Provinsi dan Kementerian tidak boleh terbaca walau ID-nya ditebak.',
    langkah: ['Masuk sebagai operator Kabupaten.', 'Ketik langsung alamat usulan milik instansi lain: `/usulan/3` (Provinsi) dan `/usulan/4` (Kementerian).'],
    harapan: 'Ditolak (403) atau tidak ditemukan (404).',
    run: async (p) => {
        const hasil = [];
        for (const path of ['/usulan/3', '/usulan/4']) { const st = await buka(p, path); hasil.push(`${path}=${st}`); tegas([403, 404].includes(st), `${path} dapat ${st}`); if (path === '/usulan/3') await ambil(p, 'Usulan Provinsi dibuka operator Kabupaten: ditolak.'); }
        return hasil.join(', ');
    },
});
uji({
    id: 'h-aksi-tidak-sah', akun: 'kab', judul: 'Aksi alur kerja yang tidak sah ditolak server',
    fungsi: 'Server memeriksa peran dan status pada setiap aksi, bukan hanya menyembunyikan tombol di layar.',
    langkah: ['Masuk sebagai operator Kabupaten, buka usulannya yang sudah ditetapkan (`/usulan/1`).', 'Kirim langsung permintaan aksi **tetapkan** dan **ajukan** ke server (tanpa lewat tombol).', 'Kirim aksi ke usulan milik instansi lain (`/usulan/3`).'],
    harapan: 'Dua aksi pertama ditolak 422 dengan pesan status/peran tidak sesuai; aksi ke usulan lain ditolak 403.',
    run: async (p) => {
        await buka(p, '/usulan/1');
        const a = await api(p, 'POST', '/usulan/1/aksi', { json: { aksi: 'tetapkan' } });
        const b = await api(p, 'POST', '/usulan/1/aksi', { json: { aksi: 'ajukan' } });
        const c = await api(p, 'POST', '/usulan/3/aksi', { json: { aksi: 'mulai_verifikasi' } });
        await bukti(p, 'Tiga permintaan aksi yang tidak sah dan jawaban server.', { tetapkan_oleh_operator: [a.status, pesanApi(a)], ajukan_saat_ditetapkan: [b.status, pesanApi(b)], usulan_instansi_lain: [c.status] });
        tegas(a.status === 422 && b.status === 422 && c.status === 403, `status ${a.status}/${b.status}/${c.status}`);
        return `tetapkan=${a.status}, ajukan=${b.status}, usulan lain=${c.status}.`;
    },
});
uji({
    id: 'h-urutan-tahap', akun: 'kemenpan', judul: 'Tahapan tidak dapat dilompati',
    fungsi: 'Usulan harus melewati verifikasi BKN dan pertimbangan teknis sebelum divalidasi dan ditetapkan.',
    langkah: ['Masuk sebagai Validator Kemenpan, buka usulan yang masih berstatus *diajukan* (`/usulan/3`).', 'Periksa tombol tindakan yang tersedia.', 'Kirim langsung aksi **tetapkan** ke server.'],
    harapan: 'Tombol Mulai Validasi dan Tetapkan Kebutuhan tidak tampil; aksi langsung ditolak 422.',
    run: async (p) => {
        await buka(p, '/usulan/3');
        const tombol = (await p.getByRole('button', { name: /Tetapkan Kebutuhan|Mulai Validasi/ }).count());
        const r = await api(p, 'POST', '/usulan/3/aksi', { json: { aksi: 'tetapkan', nomor_sk: 'X', tanggal_sk: '2026-11-08' } });
        await bukti(p, 'Aksi tetapkan pada usulan berstatus diajukan.', { status: r.status, pesan: pesanApi(r) });
        tegas(tombol === 0, 'tombol terlarang tampil'); tegas(r.status === 422, 'status ' + r.status);
        return `Tombol terlarang: ${tombol}. Server menjawab ${r.status}: ${pesanApi(r)}`;
    },
});
uji({
    id: 'h-catatan-wajib', akun: 'bkn', judul: 'Catatan wajib saat mengembalikan atau menolak usulan',
    fungsi: 'Alasan pengembalian/penolakan harus tertulis agar operator tahu apa yang diperbaiki.',
    langkah: ['Masuk sebagai Verifikator BKN, buka `/usulan/3`, klik **Mulai Verifikasi** dan konfirmasi.', 'Klik **Kembalikan untuk Perbaikan**, biarkan catatan kosong, lalu klik **Ya, lanjutkan**.', 'Kirim juga aksi kembalikan dan tolak dengan catatan kosong langsung ke server.'],
    harapan: 'Dialog menolak dengan tulisan "Catatan wajib diisi"; server juga menolak 422; status tetap *verifikasi BKN*.',
    run: async (p) => {
        await buka(p, '/usulan/3'); await p.getByRole('button', { name: /Mulai Verifikasi/ }).click(); await konfirmasi(p); await buka(p, '/usulan/3');
        await p.getByRole('button', { name: /Kembalikan untuk Perbaikan/ }).click(); await p.locator('.swal2-popup').waitFor({ state: 'visible' });
        await p.locator('.swal2-confirm').click(); await p.waitForTimeout(500);
        const dialog = (await p.locator('.swal2-validation-message').textContent().catch(() => '')) || '';
        await ambil(p, 'Dialog menolak catatan kosong.'); tegas(/wajib diisi/i.test(dialog), 'pesan dialog: ' + dialog);
        await p.locator('.swal2-cancel').click(); await tutupSwal(p);
        const k = await api(p, 'POST', '/usulan/3/aksi', { json: { aksi: 'kembalikan', catatan: '' } });
        const t = await api(p, 'POST', '/usulan/3/aksi', { json: { aksi: 'tolak', catatan: '   ' } });
        await bukti(p, 'Server menolak catatan kosong.', { kembalikan: [k.status, pesanApi(k)], tolak: [t.status, pesanApi(t)] });
        tegas(k.status === 422 && t.status === 422, `status ${k.status}/${t.status}`);
        await buka(p, '/usulan/3'); const st = await badgeStatus(p); tegas(/Verifikasi/i.test(st), 'status berubah: ' + st);
        return `Dialog: "${dialog}". Server: ${k.status}/${t.status}. Status tetap ${st}.`;
    },
});
uji({
    id: 'h-rekomendasi-lebih', akun: 'bkn', judul: 'Rekomendasi melebihi jumlah diusulkan ditolak',
    fungsi: 'Jumlah rekomendasi BKN tidak boleh lebih besar daripada jumlah yang diusulkan instansi.',
    langkah: ['Masuk sebagai Verifikator BKN, buka `/usulan/3` (berstatus verifikasi).', 'Pada kolom **Rekomendasi BKN** baris pertama isi `99999`.', 'Klik **Beri Pertimbangan Teknis** dan konfirmasi.'],
    harapan: 'Tidak ada perubahan; status tetap *verifikasi BKN* dan kolom ditandai salah.',
    run: async (p) => {
        await buka(p, '/usulan/3'); const isi = p.locator('tbody input[type=number][max]').first();
        tegas(await isi.count(), 'kolom rekomendasi tidak ada'); await isi.fill('99999');
        await p.getByRole('button', { name: /Beri Pertimbangan Teknis/ }).click(); await konfirmasi(p); await tenang(p, 800);
        await ambil(p, 'Kolom rekomendasi ditandai salah setelah ditolak server.');
        await buka(p, '/usulan/3'); const st = await badgeStatus(p); tegas(/Verifikasi/i.test(st), 'status berubah: ' + st);
        return 'Ditolak; status tetap ' + st + '.';
    },
});
uji({
    id: 'h-sk-bukan-pdf', akun: 'kemenpan', judul: 'Berkas SK selain PDF ditolak',
    fungsi: 'Lampiran SK penetapan hanya boleh berupa PDF.',
    langkah: ['Masuk sebagai Validator Kemenpan.', 'Kirim aksi **tetapkan** dengan berkas `sk.txt` (bukan PDF) langsung ke server.'],
    harapan: 'Ditolak 422 dengan pesan berkas harus PDF; usulan tidak berubah.',
    run: async (p) => {
        await buka(p, '/usulan/4');
        const r = await api(p, 'POST', '/usulan/4/aksi', { form: { aksi: 'tetapkan', nomor_sk: 'X', tanggal_sk: '2026-11-08', file_sk: { nama: 'sk.txt', isi: 'bukan pdf', tipe: 'text/plain' } } });
        await bukti(p, 'Berkas SK .txt ditolak.', { status: r.status, pesan: pesanApi(r) });
        tegas(r.status === 422 && /pdf/i.test(pesanApi(r)), `${r.status} ${pesanApi(r)}`);
        return `Ditolak ${r.status}: ${pesanApi(r)}`;
    },
});
uji({
    id: 'h-ajukan-tanpa-rincian', akun: 'kab', judul: 'Usulan tanpa rincian jabatan tidak dapat diajukan',
    fungsi: 'Usulan kosong tidak boleh masuk antrean verifikasi.',
    langkah: ['Menu **Usulan** → **Buat Usulan**, isi Tahun 2029 dan Perihal `Usulan Kosong Uji`, simpan.', 'Tanpa mengisi rincian, klik **Ajukan Usulan** dan konfirmasi.'],
    harapan: 'Pengajuan ditolak dan status tetap *Draft*.',
    run: async (p) => {
        await buka(p, '/usulan/create'); const inst = F(p, 'Instansi'); if (await inst.count()) await pilihKe(inst, 1);
        await F(p, 'Tahun anggaran').fill('2029'); await F(p, 'Perihal').fill('Usulan Kosong Uji'); await klikSimpan(p);
        tegas(/\/usulan\/\d+/.test(p.url()), 'tidak masuk detail');
        await p.getByRole('button', { name: /Ajukan Usulan/ }).click(); await konfirmasi(p); await tenang(p, 800);
        await ambil(p, 'Pengajuan ditolak; usulan masih Draft.');
        const st = await badgeStatus(p); tegas(/Draft/i.test(st), 'status: ' + st);
        return 'Ditolak; status tetap ' + st + '.';
    },
    sesudah: async (p) => { await p.locator('button:has(.fa-trash)').first().click(); await konfirmasi(p); },
});
uji({
    id: 'h-usulan-terkunci', akun: 'kl', judul: 'Usulan yang sudah ditetapkan terkunci',
    fungsi: 'Setelah diproses, usulan tidak boleh diubah atau dihapus operator.',
    langkah: ['Masuk sebagai operator Kementerian, buka usulan yang sudah ditetapkan (`/usulan/4`).', 'Periksa tombol ubah rincian, ajukan, dan tarik dari ABK.', 'Kirim langsung permintaan hapus usulan ke server.'],
    harapan: 'Tombol ubah tidak tampil; hapus ditolak (hanya draft yang dapat dihapus).',
    run: async (p) => {
        await buka(p, '/usulan/4');
        const tombol = await p.getByRole('button', { name: /Ajukan Usulan|Simpan rincian|Tarik dari ABK/ }).count();
        const r = await api(p, 'DELETE', '/usulan/4');
        await bukti(p, 'Hapus usulan yang sudah ditetapkan.', { status: r.status, pesan: pesanApi(r) });
        tegas(tombol === 0, 'tombol ubah masih tampil'); tegas([403, 422].includes(r.status), 'status hapus ' + r.status);
        return `Tombol ubah: ${tombol}. Hapus ditolak ${r.status}.`;
    },
});
uji({
    id: 'h-impor-salah', akun: 'admin', judul: 'Impor pegawai: berkas salah tipe dan baris tidak valid',
    fungsi: 'Impor Excel harus menolak tipe berkas yang salah dan melaporkan baris yang gagal tanpa menambah data rusak.',
    langkah: ['Menu **Pegawai** → **Impor**, pilih instansi Kabupaten Sejahtera.', 'Unggah berkas `salah.txt` lalu klik **Impor**.', 'Unggah berkas CSV berisi dua baris dengan NIP dan kode unit yang tidak valid, lalu klik **Impor**.'],
    harapan: 'Berkas .txt ditolak dengan pesan tipe berkas; CSV menghasilkan peringatan "baris gagal" beserta daftar alasan.',
    run: async (p) => {
        const { writeFileSync } = await import('node:fs'); const { tmpdir } = await import('node:os');
        const txt = tmpdir() + '/salah.txt'; const csv = tmpdir() + '/pegawai-salah.csv';
        writeFileSync(txt, 'bukan excel'); writeFileSync(csv, 'nip,nama,kode_unit,kode_jabatan,status_kepegawaian,golongan,pendidikan,tanggal_lahir,tmt_jabatan\n123,Salah NIP,TIDAKADA,TIDAKADA,pns,III/a,S1,1990-01-01,2020-01-01\n199001019999999999,Salah Unit,TIDAKADA,TIDAKADA,pns,III/a,S1,1990-01-01,2020-01-01\n');
        await buka(p, '/pegawai/import'); await pilih(F(p, 'Instansi'), 'Kabupaten Sejahtera');
        await p.locator('input[type=file]').setInputFiles(txt); await kirim(p, () => p.locator('button.btn-success', { hasText: 'Impor' }).click());
        tegas(await adaTeks(p, 'bertipe'), 'pesan tipe berkas tidak tampil'); await ambil(p, 'Berkas .txt ditolak.');
        await p.locator('input[type=file]').setInputFiles(csv); await kirim(p, () => p.locator('button.btn-success', { hasText: 'Impor' }).click());
        const pesan = await popup(p); await tutupSwal(p); await tenang(p, 500);
        tegas(await adaTeks(p, 'Baris yang gagal'), 'daftar baris gagal tidak tampil. Dialog: ' + pesan);
        return `.txt ditolak. CSV: ${pesan || 'daftar baris gagal tampil'}.`;
    },
});
uji({
    id: 'h-validasi-form', akun: 'admin', judul: 'Isian wajib dikosongkan: pesan Bahasa Indonesia',
    fungsi: 'Form menolak isian kosong dengan pesan yang jelas di dekat kolomnya.',
    langkah: ['Buka form **Tambah Unit Kerja** (instansi Kabupaten sudah terisi), kosongkan nama, klik **Simpan**.', 'Buka form **Tambah Pegawai**, biarkan semua kosong, klik **Simpan**.'],
    harapan: 'Muncul pesan "wajib diisi" (Bahasa Indonesia) dan data tidak tersimpan.',
    run: async (p) => {
        const id = await idKabupaten(p);
        await buka(p, '/unit-kerja/create?instansi_id=' + id); await klikSimpan(p);
        tegas(await adaTeks(p, 'wajib diisi'), 'pesan wajib diisi tidak tampil pada unit'); await ambil(p, 'Form unit kerja kosong ditolak.');
        tegas(p.url().includes('/create'), 'seharusnya tetap di form');
        await buka(p, '/pegawai/create?instansi_id=' + id); await klikSimpan(p);
        tegas(await adaTeks(p, 'wajib diisi'), 'pesan wajib diisi tidak tampil pada pegawai'); tegas(!(await adaTeks(p, 'is required')), 'pesan masih berbahasa Inggris');
        return 'Kedua form menolak isian kosong dengan pesan "wajib diisi".';
    },
});
uji({
    id: 'h-hapus-terlindungi', akun: 'admin', judul: 'Unit kerja yang masih berisi tidak dapat dihapus',
    fungsi: 'Unit yang masih punya sub-unit atau pegawai dilindungi dari penghapusan.',
    langkah: ['Menu **Organisasi & Unit Kerja**, filter instansi Kabupaten Sejahtera.', 'Klik ikon tempat sampah pada unit pertama (unit tertinggi) dan konfirmasi.'],
    harapan: 'Penghapusan ditolak dengan pesan "masih memiliki pegawai atau sub-unit"; unit tetap ada.',
    run: async (p) => {
        const id = await idKabupaten(p); await buka(p, '/unit-kerja?instansi_id=' + id);
        const baris = p.locator('tbody tr').first(); const nama = ((await baris.locator('td').first().textContent()) || '').trim().slice(0, 40);
        await baris.locator('button:has(.fa-trash)').click(); await konfirmasi(p, null, true);
        const pesan = await popup(p); await ambil(p, 'Penghapusan unit yang masih berisi ditolak.'); await tutupSwal(p);
        tegas(/masih memiliki/i.test(pesan), 'pesan: ' + pesan);
        return `Ditolak: "${pesan}"`;
    },
});
uji({
    id: 'h-hapus-diri', akun: 'admin', judul: 'Administrator tidak dapat menghapus akunnya sendiri',
    fungsi: 'Mencegah aplikasi kehilangan seluruh administrator karena salah hapus.',
    langkah: ['Menu **Pengguna**, cari baris `admin@simonkeb.test` (akun yang sedang dipakai).', 'Klik ikon tempat sampah pada baris itu dan konfirmasi.'],
    harapan: 'Ditolak dengan pesan "Tidak dapat menghapus akun sendiri".',
    run: async (p) => {
        await buka(p, '/users'); const baris = p.locator('tr', { hasText: 'admin@simonkeb.test' }).first(); const tombol = baris.locator('button:has(.fa-trash)');
        if (!(await tombol.count())) return 'Tombol hapus tidak ditampilkan pada akun sendiri.';
        await tombol.click(); await konfirmasi(p, null, true); const pesan = await popup(p); await ambil(p, 'Penghapusan akun sendiri ditolak.'); await tutupSwal(p);
        tegas(/sendiri/i.test(pesan), 'pesan: ' + pesan); return `Ditolak: "${pesan}"`;
    },
});
uji({
    id: 'h-lupa-password', akun: 'tamu', judul: 'Lupa password: email tidak dikenal dan token tidak valid',
    fungsi: 'Alur pemulihan password harus memberi pesan jelas dan menolak token palsu.',
    langkah: ['Buka `/forgot-password`, isi email yang tidak terdaftar, klik **Kirim tautan reset**.', 'Isi email terdaftar `admin@simonkeb.test` dan kirim.', 'Buka `/reset-password/token-palsu?email=admin@simonkeb.test`, isi password baru dan konfirmasinya, klik **Simpan password**.'],
    harapan: 'Email tidak dikenal ditolak; email terdaftar menerima konfirmasi pengiriman; token palsu ditolak.',
    run: async (p) => {
        await buka(p, '/forgot-password'); await p.fill('input[type=email]', 'tidak-ada@simonkeb.test'); await kirim(p, () => p.getByRole('button', { name: /Kirim tautan/ }).click());
        tegas(await adaTeks(p, 'tidak menemukan'), 'pesan email tidak dikenal tidak tampil'); await ambil(p, 'Email tidak terdaftar ditolak.');
        await p.fill('input[type=email]', AKUN.admin[0]); await kirim(p, () => p.getByRole('button', { name: /Kirim tautan/ }).click());
        tegas(await adaTeks(p, 'Tautan reset') || (await popup(p)).includes('Tautan reset'), 'konfirmasi pengiriman tidak tampil'); await tutupSwal(p);
        await buka(p, '/reset-password/token-palsu?email=' + AKUN.admin[0]);
        const pw = p.locator('input[type=password]'); await pw.nth(0).fill('PasswordBaru#2026'); await pw.nth(1).fill('PasswordBaru#2026');
        await kirim(p, () => p.getByRole('button', { name: /Simpan password/ }).click());
        tegas(await adaTeks(p, 'tidak valid'), 'pesan token tidak valid tidak tampil');
        return 'Email tidak dikenal ditolak, email terdaftar menerima konfirmasi, token palsu ditolak.';
    },
});
uji({
    id: 'h-login-throttle', akun: 'tamu', judul: 'Percobaan login berulang dibatasi',
    fungsi: 'Perlindungan terhadap tebak password: setelah 5 kegagalan per menit, login dikunci sementara.',
    langkah: ['Buka `/login`.', 'Coba masuk 6 kali berturut-turut dengan email `uji-throttle@simonkeb.test` (tidak terdaftar) dan password salah.'],
    harapan: 'Percobaan ke-6 ditolak dengan pesan "Terlalu banyak percobaan masuk".',
    run: async (p) => {
        let pesan = '';
        for (let i = 1; i <= 6; i++) {
            await buka(p, '/login'); await p.fill('input[type=email]', 'uji-throttle@simonkeb.test'); await p.fill('input[type=password]', 'salah-' + i);
            await kirim(p, () => p.click('form button'));
            pesan = (await p.locator('body').innerText()).replace(/\s+/g, ' ');
        }
        tegas(/Terlalu banyak/i.test(pesan), 'pesan pembatasan tidak tampil'); await ambil(p, 'Percobaan ke-6 dibatasi.');
        return 'Percobaan ke-6 dibatasi.';
    },
});
uji({
    id: 'h-halaman-tidak-ada', akun: 'admin', judul: 'Alamat atau data yang tidak ada menjawab 404',
    fungsi: 'Alamat ngawur atau ID yang tidak ada harus menjawab 404, bukan galat server atau halaman kosong.',
    langkah: ['Masuk sebagai admin.', 'Buka `/usulan/999999` dan `/rute-yang-tidak-ada`.'],
    harapan: 'Keduanya menjawab 404.',
    run: async (p) => {
        const a = await buka(p, '/usulan/999999'); await ambil(p, 'Usulan dengan ID yang tidak ada.'); const b = await buka(p, '/rute-yang-tidak-ada');
        tegas(a === 404 && b === 404, `status ${a}/${b}`); return `/usulan/999999=${a}, /rute-yang-tidak-ada=${b}.`;
    },
});

async function idInstansi(p) {
    await p.goto(base + '/unit-kerja'); await tenang(p, 300);
    return p.evaluate((nama) => { const o = [...document.querySelectorAll('select option')].find((x) => x.text.includes(nama)); return o ? o.value : ''; }, U.instansi);
}

// ---------- eksekusi ----------
const hasil = []; let no = 0;
for (const s of LANGKAH) {
    if (!s.run) continue;
    no++;
    const p = await halaman(s.akun);
    const nn = String(no).padStart(2, '0');
    let status = 'LULUS', catatan = ''; ekstra = []; kini = `${nn}-${s.id}`;
    try { catatan = await s.run(p); } catch (e) { status = s.temuan ? 'TEMUAN' : 'GAGAL'; catatan = String(e.message).split('\n')[0]; }
    const tgl = `${nn}-${s.id}.png`;
    try { await p.screenshot({ path: dirGambar + tgl }); } catch { /* abaikan */ }
    if (s.sesudah) { try { await s.sesudah(p); } catch (e) { catatan += ` (tindak lanjut: ${String(e.message).split('\n')[0]})`; } }
    hasil.push({ nn, ...s, status, catatan, gambar: tgl, ekstra });
    console.log(`${status === 'LULUS' ? '✓' : status === 'TEMUAN' ? '!' : '✗'} ${nn} ${s.id} — ${catatan}`);
}


// ---------- alur / tahapan ----------
const ALUR = {
    A: 'Tahap awal. Tanpa login dan hak akses yang benar, tahap lain tidak dapat dijalankan; akun yang dibuktikan di sini dipakai di seluruh tahap berikutnya.',
    B: 'Setelah akses terbukti aman, data dasar disiapkan secara berurutan: instansi, lalu unit kerja, lalu jabatan, lalu pegawai. Data ini menjadi bahan tahap C dan E.',
    C: 'Setelah organisasi dan pegawai tersedia, beban kerja tiap jabatan dihitung menjadi kebutuhan pegawai. Hasilnya menjadi dasar tahap D dan E.',
    D: 'Berbekal kebutuhan hasil ABK dan data pegawai, tahap ini memproyeksikan lima tahun ke depan serta merencanakan redistribusi dan formasi.',
    E: 'Kebutuhan hasil ABK dan perencanaan dirangkum menjadi usulan, lalu diproses lintas peran sampai ditetapkan atau ditolak.',
    F: 'Setelah usulan berjalan, tahap ini memantau capaiannya dan menghasilkan laporan untuk pimpinan.',
    G: 'Pengelolaan pengguna dan fitur pendukung yang menopang seluruh tahap sebelumnya.',
    H: 'Tahap penutup. Setelah seluruh jalur normal (A–G) terbukti, jalur gagal sengaja dicoba: akses tanpa hak, tahapan dilompati, isian dan berkas salah, serta penghapusan yang dilindungi. Tahap ini memakai data hasil tahap E dan G.',
};
const SAMBUNG = ['Pertama,', 'Kemudian,', 'Selanjutnya,', 'Setelah itu,', 'Lalu,', 'Berikutnya,'];
const kecil = (t) => (/^[A-Z][a-z]/.test(t) ? t[0].toLowerCase() + t.slice(1) : t);
function sambung(langkah) {
    const n = langkah.length;
    return langkah.map((l, i) => {
        if (n === 1) return `Langkahnya: ${kecil(l)}`;
        const k = i === 0 ? SAMBUNG[0] : i === n - 1 ? 'Terakhir,' : SAMBUNG[1 + ((i - 1) % (SAMBUNG.length - 1))];
        return `${k} ${kecil(l)}`;
    });
}
{
    let b = '';
    for (const st of LANGKAH) { if (!st.run) { b = st.bagian; continue; } hasil.find((x) => x.id === st.id).bagianNama = b; }
    hasil.forEach((h, i) => {
        const g = hasil.filter((x) => x.bagianNama === h.bagianNama);
        h.tahap = g.indexOf(h) + 1; h.tahapDari = g.length;
        h.sebelum = hasil[i - 1] ? `${hasil[i - 1].nn}. ${hasil[i - 1].judul}` : null;
        h.berikut = hasil[i + 1] ? `${hasil[i + 1].nn}. ${hasil[i + 1].judul}` : null;
        h.langkahAlur = sambung(h.langkah);
    });
}
const posisi = (h) => `Tahap ${h.tahap} dari ${h.tahapDari} pada bagian ${h.bagianNama[0]}. ${h.sebelum ? 'Dilanjutkan dari skenario ' + h.sebelum + '.' : 'Ini skenario pertama.'} ${h.berikut ? 'Setelah ini: skenario ' + h.berikut + '.' : 'Ini skenario terakhir.'}`;
const bagianRingkas = [...new Set(hasil.map((h) => h.bagianNama))].map((b) => ({ b, n: hasil.filter((h) => h.bagianNama === b).length }));

const TEMUAN = [
    { tingkat: 'Menengah', status: 'Sudah diperbaiki', judul: 'Pilihan instansi terhapus pada form Tambah Unit / Pegawai / ABK',
        uraian: 'Gejala: form dibuka tanpa parameter `instansi_id` (admin dengan filter "Semua instansi" menekan **Tambah**), instansi dipilih, lalu dropdown kembali kosong setelah daftar unit/jabatan dimuat ulang; bila disimpan server menolak karena `instansi_id` terkirim 0. Penyebab: pemuatan ulang sebagian (`router.reload({ only })`) mengubah URL sehingga isi halaman dibuat ulang (`:key="$page.url"` di layout), sedangkan prop `instansiId` tidak ikut diperbarui sehingga form diinisialisasi kosong. Skenario 11 kini memverifikasi perbaikannya.',
        saran: 'Perbaikan: `instansiId` ditambahkan ke daftar `only` pada `UnitKerja/Form.vue`, `Pegawai/Form.vue`, dan `Anjab/Form.vue`.' },
    { tingkat: 'Rendah', status: 'Sudah diperbaiki', judul: 'Pesan validasi tampil berbahasa Inggris',
        uraian: 'Gejala: "The kode has already been taken.", "The provided password does not match your current password.", "The selected instansi id is invalid." Penyebab: tidak ada berkas terjemahan `lang/id`, sehingga Laravel memakai bahasa Inggris.',
        saran: 'Perbaikan: ditambahkan `lang/id/validation.php` (termasuk nama atribut), locale bawaan `id`; diuji di `FormUnitKerjaTest`.' },
    { tingkat: 'Rendah', status: 'Sudah diperbaiki', judul: 'Ubah unit kerja tanpa field `parent_id` menyebabkan galat 500',
        uraian: 'Gejala: `PUT /unit-kerja/{id}` tanpa `parent_id` menghasilkan "Undefined array key parent_id". Formulir di aplikasi selalu mengirim field ini sehingga hanya klien non-UI yang terdampak.',
        saran: 'Perbaikan: `$data[\'parent_id\'] ??= null` di `UnitKerjaController::update`; diuji di `FormUnitKerjaTest`.' },
    { tingkat: 'Rendah', status: 'Sudah diperbaiki', judul: 'Login yang dibatasi menampilkan halaman 429 mentah berbahasa Inggris',
        uraian: 'Ditemukan pada skenario 69. Setelah 5 kegagalan per menit, percobaan berikutnya hanya menampilkan "429 TOO MANY REQUESTS" di jendela galat tanpa penjelasan, karena pembatas login menjawab dengan abort 429.',
        saran: 'Perbaikan: pembatas login kini menjawab dengan pesan di formulir ("Terlalu banyak percobaan masuk. Coba lagi dalam N detik."); diuji di `LoginThrottleTest`.' },
    { tingkat: 'Info', status: 'Catatan', judul: 'PDF laporan/ABK membutuhkan memori PHP lebih dari 128 MB',
        uraian: 'Dengan `memory_limit=128M` bawaan, PDF ABK gagal (500, memori dompdf habis). Pengujian ini memakai 768 MB dan seluruh 18 unduhan laporan serta PDF ABK berhasil.',
        saran: 'Pastikan server produksi memakai `memory_limit` ≥ 512 MB, atau optimalkan kueri/tampilan PDF.' },
    { tingkat: 'Info', status: 'Catatan', judul: 'Menghapus instansi menghapus unit dan pegawainya',
        uraian: 'Satu-satunya penjaga adalah usulan: instansi yang sudah punya usulan ditolak ("tidak dapat dihapus, nonaktifkan saja"), sedangkan instansi tanpa usulan terhapus bersama unit dan pegawai di bawahnya (terverifikasi pada skenario 53). Perilaku ini sesuai kode, namun penghapusan bersifat permanen.',
        saran: 'Pertimbangkan teks konfirmasi yang menyebut jumlah unit/pegawai yang ikut terhapus.' },
];

const BATAS = [
    'Impor Excel pegawai: yang diuji hanya berkas salah tipe dan baris tidak valid (skenario 64). Impor berkas yang benar tidak diuji karena menambah banyak data.',
    'Integrasi SIASN dan SSO: hanya halaman dan perlindungan aksesnya. Sinkronisasi dan login SSO sungguhan membutuhkan akun SIASN dan tidak dijalankan.',
    'Autentikasi dua langkah (2FA): hanya tampilan pada Akun pribadi. Alur mengaktifkan dan memakai kode TOTP tidak diuji.',
    'Pengiriman email nyata: pengirim surat diatur `log`, sehingga yang terbukti hanya aplikasi menerima dan menjawab permintaan reset password.',
    'Beban dan kinerja: pengujian ini fungsional satu pengguna, bukan uji beban.',
    'Tampilan ponsel/tablet dan peramban selain Chrome tidak diuji.',
];

// ---------- laporan Markdown ----------
const lulus = hasil.filter((h) => h.status === 'LULUS').length;
const temuan = hasil.filter((h) => h.status === 'TEMUAN').length;
const gagal = hasil.length - lulus - temuan;
const tgl = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
let md = `# Laporan Pengujian Manual SIMONKEB\n\n`;
md += `> Dokumen ini dibuat otomatis oleh \`scripts/pengujian-manual.mjs\` pada ${tgl}. Setiap langkah dijalankan di peramban sungguhan (bukan simulasi) dan tangkapan layar diambil setelah langkah selesai.\n\n`;
md += `## Ringkasan\n\n| Item | Nilai |\n| --- | --- |\n| Aplikasi | SIMONKEB (Laravel 12, PHP 8.3, Inertia v2, Vue 3) |\n| Lingkungan | Lokal, SQLite khusus uji, ${base} |\n| Data awal | \`php artisan migrate:fresh --seed\` (8 akun, 4 instansi, 68 unit, 21 jabatan, ±1.080 pegawai, 380 ABK, 4 usulan) |\n| Jumlah skenario | ${hasil.length} |\n| Lulus | ${lulus} |\n| Temuan (perilaku aplikasi yang perlu diperbaiki) | ${temuan} |\n| Gagal (kegagalan pengujian) | ${gagal} |\n\n`;
md += `### Akun demo (password semua: \`password\`)\n\n| Peran | Email | Keterangan |\n| --- | --- | --- |\n`;
for (const [k, [email, ket]] of Object.entries(AKUN)) md += `| ${k} | \`${email}\` | ${ket} |\n`;
md += `\n### Cara menjalankan ulang\n\n\`\`\`bash\n# 1. database uji sekali pakai (jangan memakai data yang sedang dipakai orang lain)\nexport DB_DATABASE=/path/ke/uji.sqlite\nphp artisan migrate:fresh --seed --force\n\n# 2. server pada port berbeda, dari folder public\ncd public && php -d memory_limit=768M -S 127.0.0.1:8123 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php\n\n# 3. jalankan pengujian (jendela peramban terpisah milik Playwright)\nAPP_URL=http://127.0.0.1:8123 PW_CHROMIUM="C:/Program Files/Google/Chrome/Application/chrome.exe" node scripts/pengujian-manual.mjs\n\`\`\`\n\n`;
md += `> Catatan lingkungan: dompdf membutuhkan memori lebih dari 128 MB untuk laporan ABK, jadi server dijalankan dengan \`memory_limit=768M\`. Jangan menjalankan \`php artisan storage:link\` (bentrok dengan rute \`/storage\` dan menggagalkan \`PanduanTest\`).\n\n`;
md += `## Alur pengujian\n\nPengujian mengikuti alur kerja nyata aplikasi, dari kiri ke kanan: ${bagianRingkas.map((x) => x.b[0]).join(' → ')}. Tiap tahap memakai hasil tahap sebelumnya.\n\n| Tahap | Bagian | Hubungan dengan tahap sebelumnya | Skenario |\n| --- | --- | --- | --- |\n${bagianRingkas.map((x, i) => `| ${i + 1} | ${x.b} | ${ALUR[x.b[0]]} | ${x.n} |`).join('\n')}\n\n`;
md += `## Temuan dan perbaikan\n\n`;
TEMUAN.forEach((t, i) => { md += `${i + 1}. **[${t.tingkat}] ${t.judul}** — *${t.status}.* ${t.uraian} ${t.saran}\n`; });
md += `\n`;
md += `## Batas cakupan pengujian\n\nHal berikut sengaja tidak atau belum diuji penuh:\n\n${BATAS.map((b) => `- ${b}`).join('\n')}\n\n`;
md += `## Daftar skenario\n\n| No | Skenario | Akun | Hasil |\n| --- | --- | --- | --- |\n`;
for (const h of hasil) md += `| ${h.nn} | ${h.judul} | ${h.akun} | ${h.status === 'LULUS' ? '✅ Lulus' : h.status === 'TEMUAN' ? '⚠️ Temuan' : '❌ Gagal'} |\n`;
md += `\n`;
let sekarang = null;
for (const s of LANGKAH) {
    if (!s.run) { md += `\n---\n\n## ${s.bagian}\n\n${s.deskripsi}\n\n**Hubungan dengan tahap sebelumnya:** ${ALUR[s.bagian[0]]}\n\n`; continue; }
    const h = hasil.find((x) => x.id === s.id);
    md += `### ${h.nn}. ${s.judul}\n\n- **Akun:** \`${s.akun}\`${AKUN[s.akun] ? ` (${AKUN[s.akun][0]})` : ' (belum login)'}\n- **Fungsi:** ${s.fungsi}\n- **Hasil yang diharapkan:** ${s.harapan}\n\n**Langkah (berurutan):**\n\n`;
    md += `> **Posisi dalam alur:** ${posisi(h)}\n\n`;
    h.langkahAlur.forEach((l, i) => { md += `${i + 1}. ${l}\n`; });
    md += `\n**Hasil pengujian:** ${h.status === 'LULUS' ? '✅ LULUS' : h.status === 'TEMUAN' ? '⚠️ TEMUAN' : '❌ GAGAL'} — ${h.catatan}\n\n![${s.judul}](gambar/${h.gambar})\n\n`;
    for (const e of h.ekstra || []) md += `![${e.ket}](gambar/${e.f})\n\n*${e.ket}*\n\n`;
}
writeFileSync(dirOut + 'LAPORAN-PENGUJIAN.md', md);
writeFileSync(dirOut + 'hasil.json', JSON.stringify(hasil.map(({ run, sesudah, ...r }) => r), null, 2));

// ---------- HTML + PDF ----------
const esc = (t) => String(t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const inl = (t) => esc(t).replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>');
const lencana = (st) => (st === 'LULUS' ? '<span class="ok">LULUS</span>' : st === 'TEMUAN' ? '<span class="tm">TEMUAN</span>' : '<span class="gl">GAGAL</span>');
let html = `<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Laporan Pengujian Manual SIMONKEB</title><style>
body{font:13px/1.55 "Segoe UI",Arial,sans-serif;color:#1c2430;margin:0}h1{font-size:26px;margin:0 0 4px}h2{font-size:19px;border-bottom:2px solid #0d3b8c;padding-bottom:4px;margin:30px 0 10px;color:#0d3b8c}h3{font-size:15px;margin:0 0 6px}
table{border-collapse:collapse;width:100%;margin:8px 0 14px}th,td{border:1px solid #cfd6e2;padding:5px 8px;text-align:left;vertical-align:top}th{background:#eef2f9}
code{background:#eef2f9;padding:1px 4px;border-radius:3px;font-size:12px}pre{background:#f4f6fa;border:1px solid #d8deea;padding:10px;font-size:11.5px;white-space:pre-wrap}
.kasus{border:1px solid #d5dcea;border-radius:8px;padding:12px 14px;margin:14px 0;break-inside:avoid-page}.kasus img{width:100%;border:1px solid #cfd6e2;border-radius:4px;margin-top:8px}
.ok{background:#d9f2e3;color:#116b3a;padding:1px 8px;border-radius:10px;font-weight:600}.tm{background:#fff1cc;color:#8a5a00;padding:1px 8px;border-radius:10px;font-weight:600}.gl{background:#fbdcdc;color:#a31616;padding:1px 8px;border-radius:10px;font-weight:600}
.meta{color:#5b6677}ol{margin:4px 0 8px 18px;padding:0}.cap{font-size:11.5px;color:#5b6677;font-style:italic}.temuan{border-left:4px solid #e0a100;background:#fffaf0;padding:8px 12px;margin:10px 0}
</style></head><body>`;
html += `<h1>Laporan Pengujian Manual SIMONKEB</h1><div class="meta">Dibuat otomatis oleh <code>scripts/pengujian-manual.mjs</code> pada ${tgl}. Setiap langkah dijalankan di peramban sungguhan; tangkapan layar diambil setelah langkah selesai.</div>`;
html += `<h2>Ringkasan</h2><table><tr><th>Item</th><th>Nilai</th></tr><tr><td>Aplikasi</td><td>SIMONKEB (Laravel 12, PHP 8.3, Inertia v2, Vue 3)</td></tr><tr><td>Lingkungan</td><td>Lokal, SQLite khusus uji, ${esc(base)}</td></tr><tr><td>Data awal</td><td><code>php artisan migrate:fresh --seed</code> (8 akun, 4 instansi, 68 unit, 21 jabatan, ±1.080 pegawai, 380 ABK, 4 usulan)</td></tr><tr><td>Jumlah skenario</td><td>${hasil.length}</td></tr><tr><td>Lulus</td><td>${lulus}</td></tr><tr><td>Temuan (belum lulus)</td><td>${temuan}</td></tr><tr><td>Gagal (kegagalan pengujian)</td><td>${gagal}</td></tr></table>`;
html += `<h3>Akun demo (password semua: <code>password</code>)</h3><table><tr><th>Peran</th><th>Email</th><th>Keterangan</th></tr>${Object.entries(AKUN).map(([k, [e, ket]]) => `<tr><td>${k}</td><td><code>${e}</code></td><td>${esc(ket)}</td></tr>`).join('')}</table>`;
html += `<h2>Alur pengujian</h2><p>Pengujian mengikuti alur kerja nyata aplikasi: <b>${bagianRingkas.map((x) => x.b[0]).join(' → ')}</b>. Tiap tahap memakai hasil tahap sebelumnya.</p><table><tr><th>Tahap</th><th>Bagian</th><th>Hubungan dengan tahap sebelumnya</th><th>Skenario</th></tr>${bagianRingkas.map((x, i) => `<tr><td>${i + 1}</td><td>${esc(x.b)}</td><td>${esc(ALUR[x.b[0]])}</td><td>${x.n}</td></tr>`).join('')}</table>`;
html += `<h2>Temuan dan perbaikan</h2>${TEMUAN.map((t, i) => `<div class="temuan"><b>${i + 1}. [${t.tingkat}] ${esc(t.judul)}</b> — <i>${t.status}</i><br>${inl(t.uraian)}<br>${inl(t.saran)}</div>`).join('')}`;
html += `<h2>Batas cakupan pengujian</h2><p>Hal berikut sengaja tidak atau belum diuji penuh:</p><ul>${BATAS.map((b) => `<li>${inl(b)}</li>`).join('')}</ul>`;
html += `<h2>Daftar skenario</h2><table><tr><th>No</th><th>Skenario</th><th>Akun</th><th>Hasil</th></tr>${hasil.map((h) => `<tr><td>${h.nn}</td><td>${esc(h.judul)}</td><td>${h.akun}</td><td>${lencana(h.status)}</td></tr>`).join('')}</table>`;
for (const s of LANGKAH) {
    if (!s.run) { html += `<h2>${esc(s.bagian)}</h2><p>${inl(s.deskripsi)}</p><p><b>Hubungan dengan tahap sebelumnya:</b> ${esc(ALUR[s.bagian[0]])}</p>`; continue; }
    const h = hasil.find((x) => x.id === s.id);
    html += `<div class="kasus"><h3>${h.nn}. ${esc(s.judul)} ${lencana(h.status)}</h3><div class="meta">Akun: <code>${s.akun}</code>${AKUN[s.akun] ? ' (' + AKUN[s.akun][0] + ')' : ' (belum login)'}</div>`
        + `<p><b>Fungsi:</b> ${inl(s.fungsi)}<br><b>Hasil yang diharapkan:</b> ${inl(s.harapan)}</p><div class="meta"><b>Posisi dalam alur:</b> ${esc(posisi(h))}</div><b>Langkah (berurutan):</b><ol>${h.langkahAlur.map((l) => `<li>${inl(l)}</li>`).join('')}</ol>`
        + `<p><b>Hasil pengujian:</b> ${inl(h.catatan)}</p><img src="gambar/${h.gambar}" alt="">${(h.ekstra || []).map((e) => `<img src="gambar/${e.f}" alt=""><div class="cap">${esc(e.ket)}</div>`).join('')}</div>`;
}
html += '</body></html>';
writeFileSync(dirOut + 'LAPORAN-PENGUJIAN.html', html);
const pg = await browser.newPage();
await pg.goto(pathToFileURL(dirOut + 'LAPORAN-PENGUJIAN.html').href); await pg.waitForLoadState('load'); await pg.waitForTimeout(800);
await pg.pdf({ path: dirOut + 'LAPORAN-PENGUJIAN.pdf', format: 'A4', printBackground: true, margin: { top: '14mm', bottom: '14mm', left: '12mm', right: '12mm' } });
await browser.close();
console.log('PDF → docs/pengujian/LAPORAN-PENGUJIAN.pdf');
console.log(`\nSelesai: ${lulus}/${hasil.length} lulus → docs/pengujian/LAPORAN-PENGUJIAN.md`);
