<?php

namespace App\Services\Dashboard;

use App\Models\InstansiAlias;
use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfImpor;
use App\Models\SumberData;
use App\Services\KebutuhanFaktaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menulis hasil API Dashboard ke kebutuhan_fakta untuk sumber ber-adapter "dashboard" milik satu pembina.
 * Jabatan dan jenjang dicocokkan dengan master (kode atau nama); yang tidak cocok dilaporkan, tidak dibuat otomatis
 * agar master tetap terkurasi. Alias instansi dicocokkan lewat ID SIASN/kode/nama, dan dibuat bila belum ada.
 */
class DashboardJfSyncService
{
    public function __construct(private DashboardJfClient $klien, private KebutuhanFaktaService $fakta) {}

    /** @return array{impor_id: ?int, baris: int, fakta_baru: int, alias_baru: int, header_baru: int, tak_cocok: array<string, int>} */
    public function jalankan(InstansiPembina $pembina, ?string $per = null, bool $uji = false): array
    {
        $sumber = SumberData::where('pembina_id', $pembina->id)->where('adapter', 'dashboard')->first();
        if (! $sumber) {
            throw new DashboardJfException("Pembina {$pembina->kode} belum punya sumber data ber-adapter \"dashboard\" (menu Referensi JF → Sumber Data).");
        }

        $hasil = $this->klien->ambil((string) $pembina->kode, $per);
        $tglData = $this->tanggal($hasil['per']);

        $jabatan = $this->indeks(JfJabatanPokok::where('pembina_id', $pembina->id)->get());
        $jenjang = $this->indeks(JfJenjang::all());
        $refSiasn = RefInstansiBkn::pluck('id', 'siasn_id')->all();
        $refKode = RefInstansiBkn::whereNotNull('kode')->pluck('id', 'kode')->all();
        $aliasAda = InstansiAlias::pluck('id', 'nama_normal')->all();

        $tak = [];
        $catat = function (string $jenis, ?string $nilai) use (&$tak) {
            $k = $jenis.': '.($nilai ?? '(kosong)');
            $tak[$k] = ($tak[$k] ?? 0) + 1;
        };

        // satu nilai per instansi × jabatan × jenjang; baris ganda dijumlahkan
        $agregat = [];
        foreach ($hasil['baris'] as $r) {
            $jab = $jabatan[$this->kunci($r['jabatan'])] ?? null;
            $jen = $jenjang[$this->kunci($r['jenjang'])] ?? null;
            if (! $jab) {
                $catat('jabatan tidak dikenal', $r['jabatan']);
            }
            if (! $jen) {
                $catat('jenjang tidak dikenal', $r['jenjang']);
            }
            if ($r['jumlah'] === null) {
                $catat('jumlah kosong', $r['jabatan']);
            }
            $nama = $r['instansi_nama'];
            if (! $jab || ! $jen || $r['jumlah'] === null || blank($nama)) {
                if (blank($nama)) {
                    $catat('nama instansi kosong', $r['jabatan']);
                }

                continue;
            }
            $normal = InstansiAlias::normalisasi($nama);
            $ref = $refSiasn[$r['instansi_id']] ?? $refKode[$r['instansi_kode']] ?? null;
            $k = $normal.'|'.$jab->id.'|'.$jen->id;
            $agregat[$k] ??= ['normal' => $normal, 'nama' => $nama, 'ref' => $ref, 'jab' => $jab->id, 'jen' => $jen->id, 'n' => 0];
            $agregat[$k]['n'] += max(0, $r['jumlah']);
        }

        if ($uji) {
            return ['impor_id' => null, 'baris' => count($hasil['baris']), 'fakta_baru' => 0, 'alias_baru' => 0, 'header_baru' => 0, 'tak_cocok' => $tak, 'siap' => count($agregat)];
        }

        return DB::transaction(function () use ($agregat, $hasil, $sumber, $aliasAda, $tak, $tglData, $pembina) {
            $impor = RekomendasiJfImpor::create(['nama_berkas' => 'API Dashboard ('.$pembina->kode.')', 'jumlah_baris' => count($hasil['baris']), 'peringatan' => array_slice(array_keys($tak), 0, 300)]);
            $faktaBaru = $aliasBaru = $headerBaru = 0;
            foreach ($agregat as $a) {
                $alias = isset($aliasAda[$a['normal']]) ? InstansiAlias::find($aliasAda[$a['normal']]) : null;
                if (! $alias) {
                    $alias = InstansiAlias::create(['nama' => $a['nama'], 'nama_normal' => $a['normal'], 'ref_instansi_id' => $a['ref'], 'asal' => 'dashboard']);
                    $aliasAda[$a['normal']] = $alias->id;
                    $aliasBaru++;
                } elseif (! $alias->ref_instansi_id && $a['ref']) {
                    $alias->update(['ref_instansi_id' => $a['ref']]);
                }
                $rekom = RekomendasiJf::firstOrCreate(['alias_id' => $alias->id, 'jabatan_pokok_id' => $a['jab']]);
                $headerBaru += $rekom->wasRecentlyCreated ? 1 : 0;
                $faktaBaru += $this->fakta->simpan($rekom, $sumber->id, $a['jen'], $a['n'], $impor->id, $tglData);
            }
            $impor->update(['dibuat' => $headerBaru, 'fakta_baru' => $faktaBaru]);

            return ['impor_id' => $impor->id, 'baris' => count($hasil['baris']), 'fakta_baru' => $faktaBaru, 'alias_baru' => $aliasBaru, 'header_baru' => $headerBaru, 'tak_cocok' => $tak];
        });
    }

    /** kode dan nama (huruf kecil, tanpa tanda baca) sebagai kunci cocok */
    private function indeks($koleksi): array
    {
        $out = [];
        foreach ($koleksi as $m) {
            $out[$this->kunci($m->nama)] ??= $m;
            $out[$this->kunci($m->kode)] ??= $m;
        }

        return $out;
    }

    private function kunci(?string $v): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower((string) $v)));
    }

    private function tanggal(?string $per): ?string
    {
        if (! $per) {
            return null;
        }
        try {
            return Carbon::parse($per)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
