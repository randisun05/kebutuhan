<?php

namespace App\Http\Controllers;

use App\Enums\UsulanStatus;
use App\Models\UsulanDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pelacakan pengisian formasi yang telah ditetapkan (hasil seleksi CASN/PPPK yang sudah diangkat).
 * Sisa formasi belum terisi ikut dihitung di monitoring dan proyeksi.
 */
class FormasiController extends Controller
{
    public function index()
    {
        $base = UsulanDetail::query()
            ->whereHas('usulan', fn ($q) => $q->where('status', UsulanStatus::Ditetapkan)
                ->when(request()->user()->isScopedToInstansi(), fn ($w) => $w->where('instansi_id', request()->user()->instansi_id))
                ->when($this->selectedInstansiId(), fn ($w, $i) => $w->where('instansi_id', $i))
                ->when(request('tahun'), fn ($w, $t) => $w->where('tahun', $t)))
            ->where('jumlah_ditetapkan', '>', 0)
            ->when(request('status') === 'belum', fn ($q) => $q->whereColumn('jumlah_terisi', '<', 'jumlah_ditetapkan'))
            ->when(request('status') === 'penuh', fn ($q) => $q->whereColumn('jumlah_terisi', '>=', 'jumlah_ditetapkan'));

        $ringkasan = (clone $base)->selectRaw('COALESCE(SUM(jumlah_ditetapkan),0) as ditetapkan, COALESCE(SUM(jumlah_terisi),0) as terisi')->first();

        $datas = (clone $base)
            ->with(['usulan:id,nomor,tahun,instansi_id,jenis_asn', 'usulan.instansi:id,nama', 'unitKerja:id,nama', 'jabatan:id,nama', 'usulan.penetapan:id,usulan_id,nomor_sk'])
            ->orderByDesc('usulan_id')->orderBy('unit_kerja_id')
            ->paginate(25)->withQueryString();

        return inertia('Formasi/Index', [
            'datas' => $datas,
            'filters' => request()->only('tahun', 'status', 'instansi_id'),
            'instansiOptions' => $this->instansiOptions(),
            'ringkasan' => ['ditetapkan' => (int) $ringkasan->ditetapkan, 'terisi' => (int) $ringkasan->terisi],
            'canEdit' => in_array(request()->user()->role?->value, ['admin', 'operator_instansi'], true),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(in_array($request->user()->role?->value, ['admin', 'operator_instansi'], true), 403);

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:usulan_details,id',
            'items.*.jumlah_terisi' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $detail = UsulanDetail::with('usulan')->findOrFail($item['id']);
                $this->authorizeInstansi($detail->usulan->instansi_id);
                abort_unless($detail->usulan->status === UsulanStatus::Ditetapkan, 422, 'Formasi belum ditetapkan.');

                if ($item['jumlah_terisi'] > $detail->jumlah_ditetapkan) {
                    throw ValidationException::withMessages([
                        'items' => "Jumlah terisi {$detail->jabatan?->nama} melebihi formasi yang ditetapkan ({$detail->jumlah_ditetapkan}).",
                    ]);
                }
                if ((int) $item['jumlah_terisi'] !== (int) $detail->jumlah_terisi) {
                    $detail->update(['jumlah_terisi' => $item['jumlah_terisi'], 'terisi_updated_at' => now()]);
                }
            }
        });

        return back()->with('success', 'Data pengisian formasi disimpan.');
    }
}
