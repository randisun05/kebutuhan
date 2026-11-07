<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Imports\KebutuhanSementaraImport;
use App\Models\Instansi;
use App\Models\KebutuhanSementara;
use App\Models\KebutuhanSementaraBatch;
use App\Models\UnitKerja;
use App\Services\KebutuhanSementaraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Kebutuhan sementara per posisi, agar kebutuhan, usulan, proyeksi, dan redistribusi tetap dapat
 * dihitung selama posisi belum memiliki ABK final. Otomatis tidak dipakai lagi setelah ABK difinalkan.
 */
class KebutuhanSementaraController extends Controller
{
    public function simpan(Request $request, KebutuhanSementaraService $service)
    {
        $data = $request->validate([
            'unit_kerja_id' => 'required|exists:unit_kerjas,id',
            'jabatan_id' => 'required|exists:jabatans,id',
            'jumlah' => 'required|integer|min:0|max:10000',
            'dasar' => 'nullable|string|max:255',
        ]);
        $unit = UnitKerja::findOrFail($data['unit_kerja_id']);
        $this->authorizeInstansi($unit->instansi_id);

        $kunci = ['unit_kerja_id' => $unit->id, 'jabatan_id' => $data['jabatan_id']];
        if ((int) $data['jumlah'] === 0) {
            KebutuhanSementara::where($kunci)->first()?->delete();

            return back()->with('success', 'Kebutuhan sementara dihapus.');
        }

        $status = $service->statusBaru($unit->instansi_id, $request->user());
        KebutuhanSementara::updateOrCreate($kunci, [
            'instansi_id' => $unit->instansi_id,
            'jumlah' => $data['jumlah'],
            'dasar' => $data['dasar'] ?? null,
            'status' => $status,
            'disetujui_by' => $status === 'disetujui' ? $request->user()->id : null,
            'disetujui_at' => $status === 'disetujui' ? now() : null,
            'created_by' => $request->user()->id,
        ]);

        if ($status === 'menunggu') {
            return back()->with('info', 'Kebutuhan sementara disimpan dan menunggu persetujuan administrator; belum dipakai di perhitungan.');
        }

        $adaAbk = DB::table('anjab_abks')->where($kunci)->where('status', 'final')->exists();

        return $adaAbk
            ? back()->with('info', 'Disimpan, tetapi posisi ini sudah memiliki ABK final sehingga yang dipakai tetap hasil ABK.')
            : back()->with('success', 'Kebutuhan sementara disimpan dan langsung dipakai.');
    }

    /** Pratinjau pengisian massal (tidak menulis apa pun). */
    public function pratinjau(Request $request, KebutuhanSementaraService $service)
    {
        [$instansiId, $unitIds, $sumber] = $this->parameterIsi($request);

        return response()->json($service->pratinjau($instansiId, $unitIds, $sumber));
    }

    /** Isi kebutuhan sementara dari existing atau formasi ditetapkan (seluruh instansi atau satu unit). */
    public function isiDariExisting(Request $request, KebutuhanSementaraService $service)
    {
        [$instansiId, $unitIds, $sumber] = $this->parameterIsi($request);

        $hasil = $service->isi($instansiId, $unitIds, $request->user(), $sumber);
        $pesan = 'Dari '.strtolower(KebutuhanSementaraService::SUMBER[$sumber]).": {$hasil['dibuat']} posisi diisi, {$hasil['dilewati_abk']} dilewati (ber-ABK final), {$hasil['dilewati_ada']} dilewati (sudah terisi)."
            .($hasil['status'] === 'menunggu' && $hasil['dibuat'] ? ' Menunggu persetujuan administrator.' : '');

        return back()->with($hasil['dibuat'] ? 'success' : 'info', $pesan);
    }

    /** Batalkan satu pengisian massal. */
    public function batal(KebutuhanSementaraBatch $batch, KebutuhanSementaraService $service)
    {
        $this->authorizeInstansi($batch->instansi_id);
        abort_if($batch->dibatalkan_at, 422, 'Pengisian ini sudah dibatalkan.');

        $hasil = $service->batalkan($batch);

        return back()->with('success', "Pengisian dibatalkan: {$hasil['dihapus']} posisi dihapus".($hasil['dipertahankan'] ? ", {$hasil['dipertahankan']} dipertahankan karena sudah diubah" : '').'.');
    }

    /** Setujui atau tolak isian yang menunggu persetujuan (administrator). */
    public function persetujuan(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'aksi' => ['required', Rule::in(['setujui', 'tolak'])],
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $query = KebutuhanSementara::where('instansi_id', $data['instansi_id'])->where('status', 'menunggu')
            ->when(! empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']));
        $jumlah = (clone $query)->count();

        if ($data['aksi'] === 'setujui') {
            $query->update(['status' => 'disetujui', 'disetujui_by' => $request->user()->id, 'disetujui_at' => now()]);
        } else {
            $query->get()->each->delete();
        }

        return back()->with('success', $data['aksi'] === 'setujui' ? "{$jumlah} isian disetujui dan mulai dipakai." : "{$jumlah} isian ditolak dan dihapus.");
    }

    public function import(Request $request, KebutuhanSementaraService $service)
    {
        $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);
        $this->authorizeInstansi($request->integer('instansi_id'));

        $import = new KebutuhanSementaraImport(
            $request->integer('instansi_id'), $request->user()->id, $service->statusBaru($request->integer('instansi_id'), $request->user())
        );
        Excel::import($import, $request->file('file'));

        $pesan = "Impor kebutuhan sementara: {$import->created} ditambahkan, {$import->updated} diperbarui, {$import->deleted} dihapus."
            .($import->adaAbk ? " {$import->adaAbk} posisi sudah punya ABK final sehingga yang dipakai tetap hasil ABK." : '')
            .($import->errors ? ' '.count($import->errors).' baris gagal.' : '')
            .($import->status === 'menunggu' && ($import->created + $import->updated) ? ' Menunggu persetujuan administrator.' : '');

        return back()->with($import->errors ? 'warning' : 'success', $pesan)
            ->with('importErrors', array_slice($import->errors, 0, 100));
    }

    public function template()
    {
        return Excel::download(new ArrayExport(
            ['kode_unit', 'kode_jabatan', 'jumlah', 'dasar'],
            [['UK-010', 'JF-ASDM-1', 5, 'Peta jabatan 2024']]
        ), 'template-impor-kebutuhan-sementara.xlsx');
    }

    /** @return array{0: int, 1: ?array, 2: string} instansi, daftar unit (null = semua), sumber */
    private function parameterIsi(Request $request): array
    {
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
            'sub' => 'nullable|boolean',
            'sumber' => ['nullable', Rule::in(array_keys(KebutuhanSementaraService::SUMBER))],
        ]);
        $this->authorizeInstansi((int) $data['instansi_id']);

        $unitIds = null;
        if (! empty($data['unit_kerja_id'])) {
            $unit = UnitKerja::findOrFail($data['unit_kerja_id']);
            abort_unless($unit->instansi_id === (int) $data['instansi_id'], 403, 'Unit bukan milik instansi ini.');
            $unitIds = ($data['sub'] ?? true) ? UnitKerja::descendantIds($unit->id) : [$unit->id];
        }

        return [(int) $data['instansi_id'], $unitIds, $data['sumber'] ?? 'existing'];
    }
}
