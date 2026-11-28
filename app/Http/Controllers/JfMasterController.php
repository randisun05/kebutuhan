<?php

namespace App\Http\Controllers;

use App\Models\InstansiPembina;
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
        $user = request()->user();
        $pembinaOptions = InstansiPembina::when($user->isScopedToPembina(), fn ($q) => $q->where('id', $user->pembina_id))->orderBy('nama')->get();
        $diminta = request()->integer('pembina_id');
        $pembinaId = $pembinaOptions->contains('id', $diminta) ? $diminta : $pembinaOptions->first()?->id;

        return inertia('JfMaster/Index', [
            'pembinaOptions' => $pembinaOptions->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama])->values(),
            'pembinaId' => $pembinaId,
            'bolehAdmin' => $user->isAdmin(),
            'pembina' => $user->isAdmin() ? InstansiPembina::withCount(['jabatanPokok', 'sumber', 'profil', 'users'])->orderBy('nama')->get() : [],
            'jabatan' => JfJabatanPokok::where('pembina_id', $pembinaId)->with('jenjangs')->withCount('rekomendasis')->orderBy('urutan')->orderBy('nama')->get(),
            'jenjang' => JfJenjang::orderBy('rumpun')->orderBy('urutan')->get()->map(fn ($j) => $j->toArray() + [
                'dipakai' => KebutuhanFakta::where('jenjang_id', $j->id)->exists() || DB::table('jf_jabatan_jenjang')->where('jenjang_id', $j->id)->exists(),
            ]),
            'sumber' => SumberData::where('pembina_id', $pembinaId)->orderBy('urutan')->get()->map(fn ($s) => $s->toArray() + ['jumlah_fakta' => KebutuhanFakta::where('sumber_id', $s->id)->count()]),
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
        $pembinaId = $this->pembinaUntukSimpan($request, $jabatanPokok?->pembina_id);
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:32', Rule::unique('jf_jabatan_pokok', 'kode')->ignore($jabatanPokok)],
            'nama' => ['required', 'string', 'max:255', Rule::unique('jf_jabatan_pokok', 'nama')->ignore($jabatanPokok)],
            'rumpun' => ['required', Rule::in(array_keys(JfJabatanPokok::RUMPUN))],
            'urutan' => 'nullable|integer|min:0|max:1000',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string|max:255',
        ]);
        $data['urutan'] ??= 0;
        $jabatanPokok ? $jabatanPokok->update($data) : JfJabatanPokok::create($data + ['pembina_id' => $pembinaId]);

        return back()->with('success', 'Jabatan pokok disimpan.');
    }

    public function hapusJabatan(JfJabatanPokok $jabatanPokok)
    {
        $this->pastikanAkses($jabatanPokok->pembina_id);
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
        $this->pastikanAkses($jabatanPokok->pembina_id);
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
        $this->khususAdmin();
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
        $this->khususAdmin();
        if (KebutuhanFakta::where('jenjang_id', $jenjang->id)->exists() || DB::table('jf_jabatan_jenjang')->where('jenjang_id', $jenjang->id)->exists()) {
            return back()->with('error', 'Jenjang masih dipakai (pada jabatan atau data angka). Lepas dari jabatan atau nonaktifkan.');
        }
        $jenjang->delete();

        return back()->with('success', 'Jenjang dihapus.');
    }

    // ---- Sumber data ----
    public function simpanSumber(Request $request, ?SumberData $sumber = null)
    {
        $pembinaId = $this->pembinaUntukSimpan($request, $sumber?->pembina_id);
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:32', Rule::unique('sumber_data', 'kode')->where('pembina_id', $pembinaId)->ignore($sumber)],
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

        // asal selain impor: jenisnya tertentu dan hanya satu sumber per pembina (agar tidak ada kolom ganda)
        $adapter = $data['adapter'];
        if ($adapter !== 'fakta') {
            if ($data['jenis'] !== SumberData::JENIS_ADAPTER[$adapter]) {
                return back()->withErrors(['jenis' => 'Asal nilai ini hanya untuk sumber berjenis '.SumberData::JENIS_ADAPTER[$adapter].'.']);
            }
            $ganda = SumberData::where('pembina_id', $pembinaId)->where('adapter', $adapter)->when($sumber, fn ($q) => $q->where('id', '!=', $sumber->id))->exists();
            if ($ganda) {
                return back()->withErrors(['adapter' => 'Pembina ini sudah memiliki sumber dengan asal nilai tersebut.']);
            }
        }

        // kode dipakai sebagai acuan di profil impor dan data lama; mengubahnya setelah dipakai akan memutus acuan itu
        if ($sumber && $sumber->kode !== $data['kode'] && KebutuhanFakta::where('sumber_id', $sumber->id)->exists()) {
            return back()->withErrors(['kode' => 'Kode sumber yang sudah memiliki data tidak dapat diubah.']);
        }
        $sumber ? $sumber->update($data) : SumberData::create($data + ['pembina_id' => $pembinaId]);

        return back()->with('success', 'Sumber data disimpan.');
    }

    public function hapusSumber(SumberData $sumber)
    {
        $this->pastikanAkses($sumber->pembina_id);
        if (KebutuhanFakta::where('sumber_id', $sumber->id)->exists()) {
            return back()->with('error', 'Sumber sudah memiliki data angka. Nonaktifkan saja.');
        }
        $sumber->delete();

        return back()->with('success', 'Sumber data dihapus.');
    }

    // ---- Instansi pembina (khusus administrator) ----
    public function simpanPembina(Request $request, ?InstansiPembina $pembina = null)
    {
        $this->khususAdmin();
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:32', Rule::unique('instansi_pembina', 'kode')->ignore($pembina)],
            'nama' => ['required', 'string', 'max:255', Rule::unique('instansi_pembina', 'nama')->ignore($pembina)],
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string|max:255',
        ]);
        $pembina ? $pembina->update($data) : InstansiPembina::create($data);

        return back()->with('success', 'Instansi pembina disimpan.');
    }

    public function hapusPembina(InstansiPembina $pembina)
    {
        $this->khususAdmin();
        if ($pembina->jabatanPokok()->exists() || $pembina->sumber()->exists() || $pembina->profil()->exists() || $pembina->users()->exists()) {
            return back()->with('error', 'Instansi pembina masih memiliki jabatan, sumber, profil impor, atau pengguna. Nonaktifkan saja.');
        }
        $pembina->delete();

        return back()->with('success', 'Instansi pembina dihapus.');
    }

    private function khususAdmin(): void
    {
        abort_unless(request()->user()->isAdmin(), 403, 'Hanya administrator yang dapat mengelola bagian ini.');
    }

    /** Pastikan pengguna boleh mengelola data milik pembina tertentu (pengguna pembina hanya pembinanya sendiri). */
    private function pastikanAkses(?int $pembinaId): void
    {
        abort_unless(request()->user()->canAccessPembina($pembinaId), 403, 'Data ini bukan hak instansi pembina Anda.');
    }

    /**
     * Pembina pemilik data yang disimpan. Pengguna pembina dipaksa ke pembinanya sendiri; administrator memilih lewat pembina_id.
     * Pemilik data yang sudah ada tidak dapat diganti.
     */
    private function pembinaUntukSimpan(Request $request, ?int $pemilikLama): int
    {
        $user = $request->user();
        if ($pemilikLama !== null) {
            $this->pastikanAkses($pemilikLama);

            return $pemilikLama;
        }
        if ($user->isPembina()) {
            return (int) $user->pembina_id;
        }
        $request->validate(['pembina_id' => 'required|exists:instansi_pembina,id']);

        return $request->integer('pembina_id');
    }
}
