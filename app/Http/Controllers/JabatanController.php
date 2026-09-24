<?php

namespace App\Http\Controllers;

use App\Models\AnjabAbk;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JabatanController extends Controller
{
    public function index()
    {
        $datas = Jabatan::query()
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
        return inertia('Jabatan/Form', ['data' => null, 'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN)]);
    }

    public function store(Request $request)
    {
        Jabatan::create($this->validated($request));

        return redirect()->route('jabatan.index')->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function edit(Jabatan $jabatan)
    {
        return inertia('Jabatan/Form', ['data' => $jabatan, 'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN)]);
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
        return $request->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('jabatans')->ignore($jabatan)],
            'nama' => 'required|string|max:255',
            'jenis' => ['required', Rule::in(array_keys(Referensi::JENIS_JABATAN))],
            'kategori' => 'nullable|in:keahlian,keterampilan',
            'jenjang' => 'nullable|string|max:50',
            'kelas_jabatan' => 'nullable|integer|min:1|max:17',
            'bup' => 'required|integer|min:50|max:70',
            'kualifikasi_pendidikan' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);
    }
}
