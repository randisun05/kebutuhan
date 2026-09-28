/**
 * Membuat ulang tangkapan layar panduan pengguna (public/img/panduan/*.png).
 *
 * Prasyarat: aplikasi berjalan dengan data demo (php artisan migrate:fresh --seed && php artisan serve).
 * Pemakaian: npm run panduan:gambar            (default http://127.0.0.1:8000)
 *            APP_URL=http://host:port npm run panduan:gambar
 *            PW_CHROMIUM=/path/chromium npm run panduan:gambar
 */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const base = process.env.APP_URL || 'http://127.0.0.1:8000';
const out = new URL('../public/img/panduan/', import.meta.url).pathname;
mkdirSync(out, { recursive: true });

// nama berkas -> [halaman, akun, aksi opsional sebelum tangkap]
const GAMBAR = {
    login: ['/login', null],
    dashboard: ['/dashboard', 'admin'],
    'unit-kerja': ['/unit-kerja?instansi_id=1', 'admin'],
    'peta-jabatan': ['/peta-jabatan?instansi_id=1', 'admin'],
    siasn: ['/siasn', 'admin'],
    'abk-form': ['/anjab/1/edit', 'admin'],
    proyeksi: ['/proyeksi?instansi_id=1', 'admin'],
    usulan: ['/usulan/3', 'bkn'],
    monitoring: ['/monitoring', 'admin'],
    analitik: ['/analitik', 'admin'],
    histori: ['/histori?instansi_id=1&level=unit&bulan=6', 'admin'],
    peringatan: ['/peringatan', 'admin'],
    laporan: ['/laporan?jenis=rekap_instansi', 'admin'],
};
const AKUN = { admin: 'admin@simonkeb.test', bkn: 'bkn@simonkeb.test' };

const browser = await chromium.launch(process.env.PW_CHROMIUM ? { executablePath: process.env.PW_CHROMIUM } : {});
const konteks = {};

async function halaman(akun) {
    if (!konteks[akun]) {
        const ctx = await browser.newContext({ viewport: { width: 1366, height: 820 }, deviceScaleFactor: 1 });
        const page = await ctx.newPage();
        if (akun) {
            await page.goto(base + '/login');
            await page.fill('input[type=email]', AKUN[akun]);
            await page.fill('input[type=password]', 'password');
            await Promise.all([page.waitForURL('**/dashboard'), page.click('form button')]);
        }
        konteks[akun] = page;
    }
    return konteks[akun];
}

for (const [nama, [path, akun]] of Object.entries(GAMBAR)) {
    const page = await halaman(akun);
    await page.goto(base + path);
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(800); // animasi grafik
    await page.screenshot({ path: `${out}${nama}.png` });
    console.log(`✓ ${nama}.png`);
}

await browser.close();
