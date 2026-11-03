<?php

namespace App\Http\Controllers;

use App\Models\KebutuhanSementara;
use App\Models\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
