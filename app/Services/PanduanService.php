<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Support\Referensi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Output\RenderedContentInterface;

/**
 * Panduan pengguna berbasis berkas markdown di docs/panduan.
 *
 * Setiap berkas memiliki front matter:
 *   judul, ringkas, urutan, peran (daftar peran atau "semua"), menu (daftar path aplikasi).
 *
 * Penanda {{nama}} di dalam teks diganti dengan isi yang dibangkitkan dari kode/konfigurasi
 * sehingga panduan selalu sesuai dengan aplikasi yang berjalan (lihat blokDinamis()).
 */
class PanduanService
{
    public const DIREKTORI = 'docs/panduan';

    /** @return Collection<int, array> daftar halaman (tanpa isi), terurut */
    public function daftar(): Collection
    {
        return collect(Cache::remember('panduan.daftar.'.$this->sidik(), 3600, fn () => collect(File::files(base_path(self::DIREKTORI)))
            ->filter(fn ($f) => $f->getExtension() === 'md')
            ->map(function ($f) {
                $meta = $this->parse(File::get($f->getPathname()))->getFrontMatter() ?? [];

                return [
                    'slug' => $f->getFilenameWithoutExtension(),
                    'judul' => $meta['judul'] ?? $f->getFilenameWithoutExtension(),
                    'ringkas' => $meta['ringkas'] ?? null,
                    'urutan' => (int) ($meta['urutan'] ?? 99),
                    'bagian' => $meta['bagian'] ?? 'Umum',
                    'peran' => (array) ($meta['peran'] ?? ['semua']),
                    'menu' => (array) ($meta['menu'] ?? []),
                ];
            })
            ->sortBy('urutan')->values()->all()));
    }

    public function halaman(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug) || ! File::exists($path = base_path(self::DIREKTORI."/{$slug}.md"))) {
            return null;
        }

        $meta = $this->daftar()->firstWhere('slug', $slug);
        $markdown = $this->isiDinamis(File::get($path));
        $html = (string) $this->parse($markdown)->getContent();

        // daftar isi dari judul bagian (h2)
        preg_match_all('/<h2[^>]*id="([^"]+)"[^>]*>(.*?)<\/h2>/s', $html, $m, PREG_SET_ORDER);

        return $meta + [
            'html' => $html,
            'toc' => array_map(fn ($x) => ['id' => $x[1], 'judul' => trim(strip_tags($x[2]))], $m),
            'diperbarui' => date('Y-m-d', File::lastModified($path)),
        ];
    }

    /** Peta path menu -> slug panduan, untuk tombol bantuan kontekstual. */
    public function petaMenu(): array
    {
        return $this->daftar()->flatMap(fn ($p) => collect($p['menu'])->mapWithKeys(fn ($m) => [$m => $p['slug']]))->all();
    }

    /** Pencarian teks sederhana di seluruh panduan. */
    public function cari(string $q): Collection
    {
        $q = mb_strtolower(trim($q));
        if (mb_strlen($q) < 3) {
            return collect();
        }

        return $this->daftar()->map(function ($p) use ($q) {
            $teks = mb_strtolower(strip_tags($this->halaman($p['slug'])['html']));
            $pos = mb_strpos($teks, $q);

            return $pos === false ? null : $p + [
                'cuplikan' => '…'.trim(mb_substr($teks, max(0, $pos - 70), 180)).'…',
            ];
        })->filter()->values();
    }

    /** Versi & catatan perubahan terbaru dari docs/panduan/perubahan.md (judul "## vX.Y.Z — tanggal"). */
    public function versiTerbaru(): ?string
    {
        $path = base_path(self::DIREKTORI.'/perubahan.md');
        if (! File::exists($path)) {
            return null;
        }

        return preg_match('/^## v?([0-9][0-9A-Za-z.\-]*)/m', File::get($path), $m) ? $m[1] : null;
    }

    public function isiDinamis(string $markdown): string
    {
        $blok = $this->blokDinamis();

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => array_key_exists($m[1], $blok)
            ? $blok[$m[1]]()
            : "> ⚠ Penanda `{$m[1]}` tidak dikenal.", $markdown);
    }

    /** Penanda yang tersedia di panduan. Setiap penanda dibangkitkan dari kode yang berjalan. */
    public function blokDinamis(): array
    {
        return [
            'versi' => fn () => $this->versiTerbaru() ?? '-',
            'tabel_peran' => fn () => $this->tabel(['Peran', 'Kode'], array_map(fn (Role $r) => [$r->label(), '`'.$r->value.'`'], Role::cases())),
            'akses_menu' => fn () => $this->aksesMenu(),
            'status_usulan' => fn () => $this->tabel(['Status', 'Kode', 'Tahap'], array_map(
                fn (UsulanStatus $s) => [$s->label(), '`'.$s->value.'`', $s->step() ?: '–'], UsulanStatus::cases())),
            'aksi_usulan' => fn () => $this->tabel(['Aksi', 'Dari status', 'Menjadi', 'Dapat dilakukan oleh'], collect(UsulanWorkflow::TRANSITIONS)
                ->map(fn ($t, $aksi) => [
                    UsulanWorkflow::LABELS[$aksi],
                    implode(', ', array_map(fn ($s) => $s->label(), $t[0])),
                    $t[1]->label(),
                    implode(', ', array_map(fn ($r) => $r->label(), $t[2])),
                ])->values()->all()),
            'aturan_peringatan' => fn () => $this->tabel(['Kode', 'Peringatan'], collect(PeringatanService::ATURAN)
                ->map(fn ($l, $k) => ['`'.$k.'`', $l])->values()->all()),
            'ambang_peringatan' => fn () => $this->tabel(['Pengaturan', 'Nilai saat ini'], collect(config('simonkeb.peringatan'))
                ->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all()),
            'jenis_laporan' => fn () => $this->daftarBullet(LaporanService::JENIS),
            'jenis_jabatan' => fn () => $this->daftarBullet(Referensi::JENIS_JABATAN),
            'informasi_jabatan' => fn () => $this->daftarBullet(array_column(Referensi::INFORMASI_JABATAN, 'label')),
            'konstanta_abk' => fn () => $this->tabel(['Konstanta', 'Nilai'], [
                ['Waktu kerja efektif', number_format(Referensi::WAKTU_KERJA_EFEKTIF, 0, ',', '.').' menit/tahun ('.(Referensi::WAKTU_KERJA_EFEKTIF / 60).' jam)'],
                ...collect(Referensi::PERIODE_PER_TAHUN)->map(fn ($f, $p) => ['Pengali volume '.Referensi::PERIODE_LABEL[$p], '× '.$f])->values()->all(),
                ['Horizon proyeksi & pensiun', Referensi::HORIZON_PROYEKSI_TAHUN.' tahun'],
            ]),
            'kategori_pej' => fn () => $this->tabel(['Nilai EJ/EU', 'Kategori'], array_map(
                fn ($v) => [$v[0], Referensi::pej($v[1])['nilai'].' — '.Referensi::pej($v[1])['label']],
                [['> 1,00', 1.01], ['0,90 – 1,00', 0.95], ['0,70 – 0,89', 0.8], ['0,50 – 0,69', 0.6], ['< 0,50', 0.3]])),
            'prioritas_usulan' => fn () => $this->daftarBullet(Referensi::PRIORITAS),
        ];
    }

    /** Matriks menu × peran dibaca langsung dari Sidebar.vue. */
    public function menuSidebar(): array
    {
        $src = File::get(resource_path('js/Components/Sidebar.vue'));
        $semua = array_map(fn (Role $r) => $r->value, Role::cases());
        preg_match_all("/\{\s*label: '([^']+)', href: '([^']+)',[^}]*roles: (ALL|\[[^\]]*\])/", $src, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => [
            'label' => $x[1],
            'href' => $x[2],
            'roles' => $x[3] === 'ALL' ? $semua : (preg_match_all("/'([a-z_]+)'/", $x[3], $r) ? $r[1] : []),
        ], $m);
    }

    private function aksesMenu(): string
    {
        $roles = Role::cases();

        return $this->tabel(
            ['Menu', ...array_map(fn (Role $r) => $r->label(), $roles)],
            array_map(fn ($menu) => [$menu['label'], ...array_map(fn (Role $r) => in_array($r->value, $menu['roles'], true) ? '✓' : '–', $roles)], $this->menuSidebar())
        );
    }

    private function tabel(array $kolom, array $baris): string
    {
        $esc = fn ($v) => str_replace(['|', "\n"], ['\|', ' '], (string) $v);

        return "\n| ".implode(' | ', array_map($esc, $kolom))." |\n|".str_repeat(' --- |', count($kolom))."\n"
            .implode("\n", array_map(fn ($r) => '| '.implode(' | ', array_map($esc, $r)).' |', $baris))."\n";
    }

    private function daftarBullet(array $items): string
    {
        return "\n".implode("\n", array_map(fn ($v) => '- '.$v, array_values($items)))."\n";
    }

    private function parse(string $markdown): RenderedContentWithFrontMatter|RenderedContentInterface
    {
        $env = new Environment([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['symbol' => '', 'insert' => 'after', 'id_prefix' => '', 'fragment_prefix' => '', 'apply_id_to_heading' => true, 'min_heading_level' => 2, 'max_heading_level' => 3],
        ]);
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new GithubFlavoredMarkdownExtension);
        $env->addExtension(new FrontMatterExtension);
        $env->addExtension(new HeadingPermalinkExtension);

        return (new MarkdownConverter($env))->convert($markdown);
    }

    /** Sidik berkas panduan, agar cache otomatis kedaluwarsa ketika berkas berubah. */
    private function sidik(): string
    {
        return md5(collect(File::files(base_path(self::DIREKTORI)))->map(fn ($f) => $f->getFilename().$f->getMTime())->implode('|'));
    }
}
