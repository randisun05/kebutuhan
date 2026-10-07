<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\Peringatan;
use App\Models\UnitKerja;
use App\Services\Ai\AiException;
use App\Services\Ai\AsistenAi;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint JSON fitur AI (Gemini). Hasil hanya saran dan disimpan di ai_riwayat; permintaan
 * berikutnya memakai hasil tersimpan kecuali dikirim baru=1 (tombol "Buat ulang").
 */
class AiController extends Controller
{
    public function __construct(private AsistenAi $ai) {}

    /** Draf uraian tugas & informasi jabatan untuk form ABK. */
    public function draftAnjab(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role?->value, ['admin', 'operator_instansi'], true), 403);

        $data = $request->validate([
            'jabatan_id' => 'required|exists:jabatans,id',
            'unit_kerja_id' => 'nullable|exists:unit_kerjas,id',
            'catatan' => 'nullable|string|max:500',
            'baru' => 'boolean',
        ]);

        $unit = isset($data['unit_kerja_id']) ? UnitKerja::with('instansi:id,nama')->find($data['unit_kerja_id']) : null;
        if ($unit) {
            $this->authorizeInstansi($unit->instansi_id);
        }

        return $this->jawab(fn () => $this->ai->draftAnjab(Jabatan::findOrFail($data['jabatan_id']), $unit, $data['catatan'] ?? null, $request->boolean('baru')));
    }

    /** Analisis naratif monitoring satu instansi. */
    public function analisisMonitoring(Request $request, Instansi $instansi): JsonResponse
    {
        $this->authorizeInstansi($instansi->id);
        $filters = array_filter(['jenis' => $request->string('jenis')->limit(40, '')->toString(), 'q' => $request->string('q')->limit(100, '')->toString()]);

        return $this->jawab(fn () => $this->ai->analisisMonitoring($instansi, $filters, $request->boolean('baru')));
    }

    /** Saran tindak lanjut sebuah peringatan dini. */
    public function saranPeringatan(Request $request, Peringatan $peringatan): JsonResponse
    {
        $this->authorizeInstansi($peringatan->instansi_id);

        return $this->jawab(fn () => $this->ai->saranPeringatan($peringatan, $request->boolean('baru')));
    }

    private function jawab(Closure $proses): JsonResponse
    {
        try {
            return response()->json($proses());
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }
}
