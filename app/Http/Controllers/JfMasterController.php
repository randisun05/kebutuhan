<?php

namespace App\Http\Controllers;

use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\KebutuhanFakta;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manajemen master JF MASN (khusus administrator): jabatan pokok, jenjang beserta jenjang yang berlaku per jabatan,
 * dan sumber angka. Seluruhnya data, bukan kode, agar JF/jenjang/sumber baru cukup ditambahkan lewat menu ini.
 */
class JfMasterController extends Controller
{
    public function index()
    {
        return inertia('JfMaster/Index', [
            'jabatan' => JfJabatanPokok::with('jenjangs')->withCount('rekomendasis')->orderBy('urutan')->orderBy('nama')->get(),
            'jenjang' => JfJenjang::orderBy('rumpun')->orderBy('urutan')->get()->map(fn ($j) => $j->toArray() + [
                'dipakai' => KebutuhanFakta::where('jenjang_id', $j->id)->exists() || DB::table('jf_jabatan_jenjang')->where('jenjang_id', $j->id)->exists(),
            ]),
            'sumber' => SumberData::orderBy('urutan')->get()->map(fn ($s) => $s->toArray() + ['jumlah_fakta' => KebutuhanFakta::where('sumber_id', $s->id)->count()]),
            'opsi' => [
                'rumpun' => collect(JfJabatanPokok::RUMPUN)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'jenis' => collect(SumberData::JENIS)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'tingkat' => collect(SumberData::TINGKAT)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'sifat' => collect(SumberData::SIFAT)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'adapter' => collect(SumberData::ADAPTER)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            ],
        ]);
    }

    // ---- Jabatan pokok ----
    public function simpanJabatan(Request $request, ?JfJabatanPokok $jabatanPokok = null)
    {
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:32', Rule::unique('jf_jabatan_pokok', 'kode')->ignore($jabatanPokok)],
            'nama' => ['required', 'string', 'max:255', Rule::unique('jf_jabatan_pokok', 'nama')->ignore($jabatanPokok)],
            'rumpun' => ['required', Rule::in(array_keys(JfJabatanPokok::RUMPUN))],
            'urutan' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string|max:255',
        ]);
        $data['urutan'] ??= 0;
        $jabatanPokok ? $jabatanPokok->update($data) : JfJabatanPokok::create($data);

        return back()->with('success', 'Jabatan pokok disimpan.');
    }

    public function hapusJabatan(JfJabatanPokok $jabatanPokok)
    {
        if (RekomendasiJf::where('jabatan_pokok_id', $jabatanPokok->id)->exists()) {
            return back()->with('error', 'Jabatan pokok sudah dipakai pada data rekomendasi. Nonaktifkan saja.');
        }
        $jabatanPokok->jenjangs()->detach();
        $jabatanPokok->delete();

        return back()->with('success', 'Jabatan pokok dihapus.');
    }

    /** Tetapkan jenjang yang berlaku untuk satu jabatan beserta posisi kolomnya pada blok angka Excel. */
    public function syncJenjang(Request $request, JfJabatanPokok $jabatanPokok)
    {
        $data = $request->validate([
            'jenjang' => 'present|array',
            'jenjang.*.jenjang_id' => 'required|integer|distinct|exists:jf_jenjang,id',
            'jenjang.*.urutan_kolom' => 'required|integer|distinct|min:1|max:12',
        ]);
        $sync = collect($data['jenjang'])->mapWithKeys(fn ($j) => [$j['jenjang_id'] => ['urutan_kolom' => $j['urutan_kolom']]])->all();

        // jenjang yang sudah punya angka untuk jabatan ini tidak boleh dilepas begitu saja
        $dilepas = $jabatanPokok->jenjangs()->pluck('jf_jenjang.id')->diff(array_keys($sync));
        $terpakai = KebutuhanFakta::whereIn('jenjang_id', $dilepas)
            ->whereHas('rekomendasi', fn ($q) => $q->where('jabatan_pokok_id', $jabatanPokok->id))->exists();
        if ($terpakai) {
            return back()->with('error', 'Ada jenjang yang sudah memiliki angka untuk jabatan ini dan tidak dapat dilepas.');
        }

        DB::transaction(function () use ($jabatanPokok, $sync) {
            // lepas dulu agar tidak bentrok pada kunci unik urutan_kolom saat posisi ditukar
            $jabatanPokok->jenjangs()->detach();
            $jabatanPokok->jenjangs()->attach($sync);
        });

        return back()->with('success', 'Jenjang jabatan disimpan.');
    }

    // ---- Jenjang ----
    public function simpanJenjang(Request $request, ?JfJenjang $jenjang = null)
    {
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:24', Rule::unique('jf_jenjang', 'kode')->ignore($jenjang)],
            'nama' => 'required|string|max:48',
            'rumpun' => ['required', Rule::in(array_keys(JfJabatanPokok::RUMPUN))],
            'urutan' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'boolean',
        ]);
        $data['urutan'] ??= 0;
        $jenjang ? $jenjang->update($data) : JfJenjang::create($data);

        return back()->with('success', 'Jenjang disimpan.');
    }

    public function hapusJenjang(JfJenjang $jenjang)
    {
        if (KebutuhanFakta::where('jenjang_id', $jenjang->id)->exists() || DB::table('jf_jabatan_jenjang')->where('jenjang_id', $jenjang->id)->exists()) {
            return back()->with('error', 'Jenjang masih dipakai (pada jabatan atau data angka). Lepas dari jabatan atau nonaktifkan.');
        }
        $jenjang->delete();

        return back()->with('success', 'Jenjang dihapus.');
    }

    // ---- Sumber data ----
    public function simpanSumber(Request $request, ?SumberData $sumber = null)
    {
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:32', Rule::unique('sumber_data', 'kode')->ignore($sumber)],
            'nama' => 'required|string|max:255',
            'jenis' => ['required', Rule::in(array_keys(SumberData::JENIS))],
            'tingkat' => ['required', Rule::in(array_keys(SumberData::TINGKAT))],
            'sifat' => ['required', Rule::in(array_keys(SumberData::SIFAT))],
            'adapter' => ['required', Rule::in(array_keys(SumberData::ADAPTER))],
            'urutan' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string|max:255',
        ]);
        $data['urutan'] ??= 0;

        // kode dipakai sebagai acuan di profil impor dan data lama; mengubahnya setelah dipakai akan memutus acuan itu
        if ($sumber && $sumber->kode !== $data['kode'] && KebutuhanFakta::where('sumber_id', $sumber->id)->exists()) {
            return back()->withErrors(['kode' => 'Kode sumber yang sudah memiliki data tidak dapat diubah.']);
        }
        $sumber ? $sumber->update($data) : SumberData::create($data);

        return back()->with('success', 'Sumber data disimpan.');
    }

    public function hapusSumber(SumberData $sumber)
    {
        if (KebutuhanFakta::where('sumber_id', $sumber->id)->exists()) {
            return back()->with('error', 'Sumber sudah memiliki data angka. Nonaktifkan saja.');
        }
        $sumber->delete();

        return back()->with('success', 'Sumber data dihapus.');
    }
}
