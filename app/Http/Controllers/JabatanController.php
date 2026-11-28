<?php

namespace App\Http\Controllers;

use App\Models\AnjabAbk;
use App\Models\Jabatan;
use App\Models\JfJabatanPokok;
use App\Models\Pegawai;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JabatanController extends Controller
{
    public function index()
    {
        $datas = Jabatan::query()->with('jfJabatanPokok:id,nama', 'jfJenjang:id,nama')
            ->when(request('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$s}%")->orWhere('kode', 'like', "%{$s}%")))
            ->when(request('jenis'), fn ($q, $j) => $q->where('jenis', $j))
            ->orderBy('jenis')->orderBy('nama')
            ->paginate(20)->withQueryString();

        return inertia('Jabatan/Index', [
            'datas' => $datas,
            'filters' => request()->only('q', 'jenis'),
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
        ]);
    }

    public function create()
    {
        return inertia('Jabatan/Form', ['data' => null, 'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN), 'jfOptions' => $this->jfOptions()]);
    }

    public function store(Request $request)
    {
        Jabatan::create($this->validated($request));

        return redirect()->route('jabatan.index')->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function edit(Jabatan $jabatan)
    {
        return inertia('Jabatan/Form', ['data' => $jabatan, 'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN), 'jfOptions' => $this->jfOptions()]);
    }

    public function update(Request $request, Jabatan $jabatan)
    {
        $jabatan->update($this->validated($request, $jabatan));

        return redirect()->route('jabatan.index')->with('success', 'Jabatan berhasil diperbarui.');
    }

    public function destroy(Jabatan $jabatan)
    {
        $used = Pegawai::where('jabatan_id', $jabatan->id)->exists()
            || AnjabAbk::where('jabatan_id', $jabatan->id)->exists();

        if ($used) {
            return back()->with('error', 'Jabatan sudah dipakai pada data pegawai/ABK. Nonaktifkan saja.');
        }

        $jabatan->delete();

        return back()->with('success', 'Jabatan berhasil dihapus.');
    }

    private function validated(Request $request, ?Jabatan $jabatan = null): array
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('jabatans')->ignore($jabatan)],
            'siasn_jabatan_id' => 'nullable|string|max:64',
            'estimasi_biaya_tahunan' => 'nullable|integer|min:0',
            'nama' => 'required|string|max:255',
            'jenis' => ['required', Rule::in(array_keys(Referensi::JENIS_JABATAN))],
            'kategori' => 'nullable|in:keahlian,keterampilan',
            'jenjang' => 'nullable|string|max:50',
            'kelas_jabatan' => 'nullable|integer|min:1|max:17',
            'bup' => 'required|integer|min:50|max:70',
            'kualifikasi_pendidikan' => 'nullable|string|max:255',
            'jf_jabatan_pokok_id' => 'nullable|exists:jf_jabatan_pokok,id',
            'jf_jenjang_id' => 'nullable|exists:jf_jenjang,id',
            'is_active' => 'boolean',
        ]);

        // kaitan ke master JF harus lengkap (jabatan pokok + jenjang) dan jenjangnya harus berlaku untuk jabatan pokok itu
        $pokok = $data['jf_jabatan_pokok_id'] ?? null;
        $jenjang = $data['jf_jenjang_id'] ?? null;
        if (($pokok === null) !== ($jenjang === null)) {
            throw ValidationException::withMessages(['jf_jenjang_id' => 'Isi jabatan pokok dan jenjang JF sekaligus, atau kosongkan keduanya.']);
        }
        if ($pokok !== null && ! JfJabatanPokok::findOrFail($pokok)->jenjangs()->where('jf_jenjang.id', $jenjang)->exists()) {
            throw ValidationException::withMessages(['jf_jenjang_id' => 'Jenjang itu tidak berlaku untuk jabatan pokok yang dipilih.']);
        }
        if ($data['jenis'] !== 'fungsional') {
            $data['jf_jabatan_pokok_id'] = $data['jf_jenjang_id'] = null; // hanya jabatan fungsional yang dapat dikaitkan ke JF
        }

        return $data;
    }

    /** Pilihan jabatan pokok JF beserta jenjang yang berlaku, untuk form jabatan. */
    private function jfOptions(): array
    {
        return JfJabatanPokok::with(['jenjangs:id,nama', 'pembina:id,nama'])->where('is_active', true)->orderBy('urutan')->get()
            ->map(fn ($j) => ['id' => $j->id, 'nama' => $j->nama, 'pembina' => $j->pembina?->nama, 'jenjang' => $j->jenjangs->map(fn ($x) => ['id' => $x->id, 'nama' => $x->nama])->values()])->all();
    }
}
