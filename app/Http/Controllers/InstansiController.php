<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstansiController extends Controller
{
    public function index()
    {
        $datas = Instansi::query()
            ->when(request('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$s}%")->orWhere('kode', 'like', "%{$s}%")))
            ->when(request('jenis'), fn ($q, $j) => $q->where('jenis', $j))
            ->withCount(['unitKerjas', 'pegawais' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('nama')
            ->paginate(15)->withQueryString();

        return inertia('Instansi/Index', [
            'datas' => $datas,
            'filters' => request()->only('q', 'jenis'),
            'jenisOptions' => Referensi::options(Referensi::JENIS_INSTANSI),
        ]);
    }

    public function create()
    {
        return inertia('Instansi/Form', ['data' => null, 'jenisOptions' => Referensi::options(Referensi::JENIS_INSTANSI)]);
    }

    public function store(Request $request)
    {
        Instansi::create($this->validated($request));

        return redirect()->route('instansi.index')->with('success', 'Instansi berhasil ditambahkan.');
    }

    public function edit(Instansi $instansi)
    {
        return inertia('Instansi/Form', ['data' => $instansi, 'jenisOptions' => Referensi::options(Referensi::JENIS_INSTANSI)]);
    }

    public function update(Request $request, Instansi $instansi)
    {
        $instansi->update($this->validated($request, $instansi));

        return redirect()->route('instansi.index')->with('success', 'Instansi berhasil diperbarui.');
    }

    public function destroy(Instansi $instansi)
    {
        if ($instansi->usulans()->exists()) {
            return back()->with('error', 'Instansi yang sudah memiliki usulan tidak dapat dihapus. Nonaktifkan saja.');
        }

        $instansi->delete();

        return back()->with('success', 'Instansi berhasil dihapus.');
    }

    private function validated(Request $request, ?Instansi $instansi = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('instansis')->ignore($instansi)],
            'nama' => 'required|string|max:255',
            'jenis' => ['required', Rule::in(array_keys(Referensi::JENIS_INSTANSI))],
            'provinsi' => 'nullable|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);
    }
}
