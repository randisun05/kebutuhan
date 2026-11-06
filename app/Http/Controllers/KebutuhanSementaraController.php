<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Imports\KebutuhanSementaraImport;
use App\Models\Instansi;
use App\Models\KebutuhanSementara;
use App\Models\UnitKerja;
use App\Services\KebutuhanSementaraService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Kebutuhan sementara per posisi, agar kebutuhan, usulan, proyeksi, dan redistribusi tetap dapat
 * dihitung selama posisi belum memiliki ABK final. Otomatis tidak dipakai lagi setelah ABK difinalkan.
 */
class KebutuhanSementaraController extends Controller
{
    public function simpan(Request $request)
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

        KebutuhanSementara::updateOrCreate($kunci, [
            'instansi_id' => $unit->instansi_id,
            'jumlah' => $data['jumlah'],
            'dasar' => $data['dasar'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $adaAbk = DB::table('anjab_abks')->where($kunci)->where('status', 'final')->exists();

        return $adaAbk
            ? back()->with('info', 'Kebutuhan sementara disimpan, tetapi posisi ini sudah memiliki ABK final sehingga yang dipakai tetap hasil ABK.')
            : back()->with('success', 'Kebutuhan sementara disimpan dan langsung dipakai di monitoring, usulan, dan proyeksi.');
    }

    /** Isi kebutuhan sementara dari jumlah pegawai existing (seluruh instansi atau satu unit). */
    public function isiDariExisting(Request $request, KebutuhanSementaraService $service)
    {
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
            'sub' => 'nullable|boolean',
        ]);
        $this->authorizeInstansi((int) $data['instansi_id']);

        $unitIds = null;
        if (! empty($data['unit_kerja_id'])) {
            $unit = UnitKerja::findOrFail($data['unit_kerja_id']);
            abort_unless($unit->instansi_id === (int) $data['instansi_id'], 403, 'Unit bukan milik instansi ini.');
            $unitIds = ($data['sub'] ?? true) ? UnitKerja::descendantIds($unit->id) : [$unit->id];
        }

        $hasil = $service->isiDariExisting((int) $data['instansi_id'], $unitIds, $request->user()->id);
        $pesan = "Kebutuhan sementara dari existing: {$hasil['dibuat']} posisi diisi, {$hasil['dilewati_abk']} dilewati (sudah ada ABK final), {$hasil['dilewati_ada']} dilewati (sudah ada kebutuhan sementara).";

        return back()->with($hasil['dibuat'] ? 'success' : 'info', $pesan);
    }

    public function import(Request $request)
    {
        $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);
        $this->authorizeInstansi($request->integer('instansi_id'));

        $import = new KebutuhanSementaraImport($request->integer('instansi_id'), $request->user()->id);
        Excel::import($import, $request->file('file'));

        $pesan = "Impor kebutuhan sementara: {$import->created} ditambahkan, {$import->updated} diperbarui, {$import->deleted} dihapus."
            .($import->adaAbk ? " {$import->adaAbk} posisi sudah punya ABK final sehingga yang dipakai tetap hasil ABK." : '')
            .($import->errors ? ' '.count($import->errors).' baris gagal.' : '');

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
}
