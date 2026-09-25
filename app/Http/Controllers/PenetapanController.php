<?php

namespace App\Http\Controllers;

use App\Models\Penetapan;
use Barryvdh\DomPDF\Facade\Pdf;

class PenetapanController extends Controller
{
    public function index()
    {
        $datas = $this->scoped(Penetapan::query())
            ->with(['instansi:id,kode,nama', 'usulan:id,nomor,perihal,jenis_asn'])
            ->when($this->selectedInstansiId(), fn ($q, $i) => $q->where('instansi_id', $i))
            ->when(request('tahun'), fn ($q, $t) => $q->where('tahun', $t))
            ->when(request('q'), fn ($q, $s) => $q->where('nomor_sk', 'like', "%{$s}%"))
            ->latest('tanggal_sk')
            ->paginate(15)->withQueryString();

        return inertia('Penetapan/Index', [
            'datas' => $datas,
            'filters' => request()->only('tahun', 'q', 'instansi_id'),
            'instansiOptions' => $this->instansiOptions(),
            'rekap' => $this->scoped(Penetapan::query())
                ->selectRaw('tahun, COUNT(*) as jumlah_sk, SUM(total_ditetapkan) as total')
                ->groupBy('tahun')->orderByDesc('tahun')->limit(6)->get(),
        ]);
    }

    /** Lampiran penetapan kebutuhan (siap cetak). */
    public function show(Penetapan $penetapan)
    {
        $this->authorizeInstansi($penetapan->instansi_id);

        $penetapan->load([
            'instansi', 'penetap:id,name',
            'usulan.details' => fn ($q) => $q->where('jumlah_ditetapkan', '>', 0)
                ->with(['unitKerja:id,nama', 'jabatan:id,nama,jenis'])->orderBy('unit_kerja_id'),
        ]);

        return inertia('Penetapan/Show', ['data' => $penetapan]);
    }

    /** Lampiran keputusan penetapan kebutuhan dalam PDF. */
    public function pdf(Penetapan $penetapan)
    {
        $this->authorizeInstansi($penetapan->instansi_id);
        $penetapan->load([
            'instansi', 'penetap:id,name',
            'usulan.details' => fn ($q) => $q->where('jumlah_ditetapkan', '>', 0)
                ->with(['unitKerja:id,nama', 'jabatan:id,nama,jenis'])->orderBy('unit_kerja_id'),
        ]);

        return Pdf::loadView('pdf.penetapan', ['p' => $penetapan])
            ->setPaper('a4')->download('penetapan-'.str($penetapan->nomor_sk)->slug().'.pdf');
    }
}
