<?php

namespace App\Http\Controllers;

use App\Models\InstansiAlias;
use App\Models\RefInstansiBkn;
use Illuminate\Http\Request;

/**
 * Alias nama instansi (khusus administrator): satu instansi referensi BKN dapat punya banyak penulisan nama,
 * sehingga instansi yang berganti nama atau ditulis berbeda di sumber data tetap menyatu.
 */
class AliasInstansiController extends Controller
{
    public function index()
    {
        $datas = InstansiAlias::with('refInstansi:id,nama')->withCount('rekomendasis')
            ->when(request('q'), fn ($q, $s) => $q->where('nama', 'like', "%{$s}%"))
            ->when(request()->boolean('belum_cocok'), fn ($q) => $q->whereNull('ref_instansi_id'))
            ->orderByRaw('CASE WHEN ref_instansi_id IS NULL THEN 0 ELSE 1 END')->orderBy('nama')
            ->paginate(25)->withQueryString();

        return inertia('AliasInstansi/Index', [
            'datas' => $datas,
            'filters' => request()->only('q', 'belum_cocok'),
            'ringkasan' => ['total' => InstansiAlias::count(), 'belum_cocok' => InstansiAlias::whereNull('ref_instansi_id')->count()],
            'refOptions' => RefInstansiBkn::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function simpan(Request $request, ?InstansiAlias $alias = null)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:255',
            'ref_instansi_id' => 'nullable|exists:ref_instansi_bkn,id',
            'jenis_instansi' => 'nullable|string|max:16',
        ]);
        $normal = InstansiAlias::normalisasi($data['nama']);
        if ($normal === '') {
            return back()->withErrors(['nama' => 'Nama instansi tidak valid.']);
        }
        $bentrok = InstansiAlias::where('nama_normal', $normal)->when($alias, fn ($q) => $q->where('id', '!=', $alias->id))->exists();
        if ($bentrok) {
            return back()->withErrors(['nama' => 'Alias dengan nama itu sudah ada.']);
        }

        if ($alias) {
            $alias->update($data + ['nama_normal' => $normal]);
        } else {
            InstansiAlias::create($data + ['nama_normal' => $normal, 'asal' => 'manual']);
        }

        return back()->with('success', 'Alias instansi disimpan.');
    }

    public function hapus(InstansiAlias $alias)
    {
        if ($alias->rekomendasis()->exists()) {
            return back()->with('error', 'Alias sudah dipakai pada data rekomendasi dan tidak dapat dihapus. Petakan ke instansi yang benar saja.');
        }
        $alias->delete();

        return back()->with('success', 'Alias dihapus.');
    }
}
