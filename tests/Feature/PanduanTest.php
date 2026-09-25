<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\LaporanService;
use App\Services\PanduanService;
use App\Services\PeringatanService;
use App\Services\UsulanWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/**
 * Penjaga agar panduan pengguna selalu mengikuti aplikasi:
 * test ini GAGAL bila ada menu baru tanpa panduan, penanda dinamis rusak,
 * gambar hilang, atau tautan antarhalaman panduan putus.
 */
class PanduanTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_setiap_menu_aplikasi_memiliki_halaman_panduan(): void
    {
        $svc = app(PanduanService::class);
        $peta = $svc->petaMenu();
        $menu = collect($svc->menuSidebar())->pluck('href')->reject(fn ($h) => $h === '/panduan');

        $this->assertGreaterThan(15, $menu->count(), 'Menu sidebar tidak terbaca dari Sidebar.vue.');

        $tanpaPanduan = $menu->reject(fn ($h) => isset($peta[$h]))->values()->all();
        $this->assertSame([], $tanpaPanduan,
            'Menu berikut belum dijelaskan di panduan. Tambahkan path-nya ke front matter "menu" pada berkas docs/panduan/*.md: '.implode(', ', $tanpaPanduan));
    }

    public function test_semua_halaman_panduan_valid(): void
    {
        $svc = app(PanduanService::class);
        $slugs = $svc->daftar()->pluck('slug');

        foreach ($slugs as $slug) {
            $h = $svc->halaman($slug);
            $this->assertNotNull($h, "Halaman {$slug} gagal dibaca.");
            $this->assertNotEmpty($h['judul'], "Front matter 'judul' kosong pada {$slug}.");
            $this->assertStringNotContainsString('tidak dikenal', $h['html'], "Penanda dinamis tidak dikenal di {$slug}.");
            $this->assertDoesNotMatchRegularExpression('/\{\{\s*[a-z_]+\s*\}\}/', $h['html'], "Penanda tidak terganti di {$slug}.");

            foreach ($h['peran'] as $peran) {
                $this->assertContains($peran, ['semua', ...array_map(fn (Role $r) => $r->value, Role::cases())], "Peran '{$peran}' tidak dikenal di {$slug}.");
            }

            preg_match_all('/src="\/(img\/panduan\/[^"]+)"/', $h['html'], $img);
            foreach ($img[1] as $path) {
                $this->assertFileExists(public_path($path), "Gambar {$path} di {$slug} tidak ada. Jalankan: npm run panduan:gambar");
            }

            preg_match_all('/href="\/panduan\/([a-z0-9-]+)"/', $h['html'], $link);
            foreach ($link[1] as $target) {
                $this->assertContains($target, $slugs->all(), "Tautan /panduan/{$target} di {$slug} tidak menuju halaman yang ada.");
            }
        }
    }

    public function test_bagian_otomatis_mengikuti_kode(): void
    {
        $html = app(PanduanService::class)->halaman('13-peringatan')['html'];
        foreach (PeringatanService::ATURAN as $label) {
            $this->assertStringContainsString(e($label), $html);
        }
        $this->assertStringContainsString((string) config('simonkeb.peringatan.gemuk_persen'), $html);

        $html = app(PanduanService::class)->halaman('14-laporan')['html'];
        foreach (LaporanService::JENIS as $label) {
            $this->assertStringContainsString(e($label), $html);
        }

        $html = app(PanduanService::class)->halaman('08-usulan')['html'];
        foreach (UsulanWorkflow::LABELS as $label) {
            $this->assertStringContainsString(e($label), $html);
        }
    }

    /** Folder di public/ yang bernama sama dengan rute membuat web server menyajikan folder, bukan halaman. */
    public function test_tidak_ada_folder_publik_yang_menimpa_rute(): void
    {
        $segmen = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => explode('/', trim($r->uri(), '/'))[0])->filter()->unique();

        foreach (File::directories(public_path()) as $dir) {
            $this->assertNotContains(basename($dir), $segmen->all(), 'Folder public/'.basename($dir).' bertabrakan dengan rute /'.basename($dir).'.');
        }
    }

    public function test_catatan_perubahan_memiliki_versi(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', (string) app(PanduanService::class)->versiTerbaru());
    }

    public function test_halaman_panduan_bantuan_dan_penanda_pembaruan(): void
    {
        $user = $this->user(Role::OperatorInstansi, $this->instansi());
        $versi = app(PanduanService::class)->versiTerbaru();

        $this->actingAs($user)->get('/dashboard')->assertInertia(fn ($p) => $p
            ->where('panduan.baru', true)
            ->where('panduan.peta./usulan', '08-usulan'));

        $this->get('/panduan')->assertOk();
        $this->get('/panduan?q=finalkan')->assertOk()->assertInertia(fn ($p) => $p->has('hasilCari', fn ($h) => $h->etc()));
        $this->get('/panduan/06-abk')->assertOk();
        $this->get('/panduan/tidak-ada')->assertNotFound();
        $this->get('/panduan/..%2F..%2F.env')->assertNotFound();

        $this->get('/panduan/perubahan')->assertOk();
        $this->assertSame($versi, $user->fresh()->panduan_versi_dibaca);
        $this->get('/dashboard')->assertInertia(fn ($p) => $p->where('panduan.baru', false));

        $this->get('/panduan/unduh/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
