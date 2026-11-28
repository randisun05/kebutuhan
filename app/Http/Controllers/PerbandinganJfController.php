<?php

namespace App\Http\Controllers;

use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\RekomendasiJf;
use App\Services\PerbandinganJfService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Perbandingan referensi per instansi × jabatan pokok × jenjang: rekomendasi BKN, penetapan KemenPANRB, ABK, dan bezetting
 * (saat rekomendasi, pegawai SIMONKEB, API Dashboard) berdampingan tanpa saling membatasi. Pengguna pembina hanya melihat
 * pembinanya; operator instansi hanya instansinya (yang aliasnya dipetakan ke instansi aplikasi).
 */
class PerbandinganJfController extends Controller
{
    private const PER_HALAMAN = 25;

    public function index(PerbandinganJfService $service)
    {
        $user = request()->user();
        $pembinaOptions = InstansiPembina::where('is_active', true)->when($user->isScopedToPembina(), fn ($q) => $q->where('id', $user->pembina_id))->orderBy('nama')->get();
        $diminta = request()->integer('pembina_id');
        $pembinaId = $pembinaOptions->contains('id', $diminta) ? $diminta : $pembinaOptions->first()?->id;

        $sumber = $pembinaId ? $service->sumber($pembinaId) : collect();
        $semua = $pembinaId ? $service->baris($pembinaId) : collect();
        if ($user->isScopedToInstansi()) {
            $semua = $semua->filter(fn ($b) => $b['instansi_lokal_id'] === $user->instansi_id)->values();
        }

        $baris = $semua
            ->when(request('q'), fn ($c, $q) => $c->filter(fn ($b) => str_contains(mb_strtolower($b['instansi']), mb_strtolower($q))))
            ->when(request('jabatan'), fn ($c, $j) => $c->filter(fn ($b) => $b['jabatan'] === $j))
            ->when(request('berjauhan') === 'kebutuhan', fn ($c) => $c->filter(fn ($b) => $b['berjauhan']['kebutuhan']))
            ->when(request('berjauhan') === 'bezetting', fn ($c) => $c->filter(fn ($b) => $b['berjauhan']['bezetting']))
            ->values();

        $halaman = max(1, request()->integer('page', 1));
        $datas = (new LengthAwarePaginator($baris->forPage($halaman, self::PER_HALAMAN)->values(), $baris->count(), self::PER_HALAMAN, $halaman,
            ['path' => request()->url(), 'query' => request()->except('page')]))->toArray();

        return inertia('Perbandingan/Index', [
            'datas' => $datas,
            'sumber' => $sumber->map(fn ($s) => ['kode' => $s->kode, 'nama' => $s->nama, 'jenis' => $s->jenis, 'adapter' => $s->adapter])->values(),
            'pembinaOptions' => $pembinaOptions->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama])->values(),
            'pembinaId' => $pembinaId,
            'filters' => request()->only('q', 'jabatan', 'berjauhan') + ['pembina_id' => $pembinaId],
            'jabatanOptions' => $pembinaId ? JfJabatanPokok::where('pembina_id', $pembinaId)->where('is_active', true)->orderBy('urutan')->pluck('nama') : [],
            'ringkasan' => [
                'baris' => $semua->count(),
                'berjauhan_kebutuhan' => $semua->filter(fn ($b) => $b['berjauhan']['kebutuhan'])->count(),
                'berjauhan_bezetting' => $semua->filter(fn ($b) => $b['berjauhan']['bezetting'])->count(),
            ],
            'ambang' => (int) config('simonkeb.perbandingan.ambang_beda_persen'),
        ]);
    }

    public function show(RekomendasiJf $rekomendasi, PerbandinganJfService $service)
    {
        $user = request()->user();
        $rekomendasi->load('alias.refInstansi:id,nama,instansi_id', 'jabatanPokok');
        abort_unless($user->canAccessPembina($rekomendasi->jabatanPokok->pembina_id), 403, 'Data ini bukan hak instansi pembina Anda.');
        if ($user->isScopedToInstansi()) {
            abort_unless($rekomendasi->alias->refInstansi?->instansi_id === $user->instansi_id, 403, 'Data ini bukan milik instansi Anda.');
        }

        return inertia('Perbandingan/Show', [
            'data' => ['id' => $rekomendasi->id, 'instansi' => $rekomendasi->alias->nama, 'jabatan' => $rekomendasi->jabatanPokok->nama, 'pokja' => $rekomendasi->pokja,
                'dipetakan' => $rekomendasi->alias->ref_instansi_id !== null, 'instansi_ref' => $rekomendasi->alias->refInstansi?->nama],
            'r' => $service->rincian($rekomendasi),
            'ambang' => (int) config('simonkeb.perbandingan.ambang_beda_persen'),
        ]);
    }
}
