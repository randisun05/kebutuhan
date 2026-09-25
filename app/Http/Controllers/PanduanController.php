<?php

namespace App\Http\Controllers;

use App\Services\PanduanService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PanduanController extends Controller
{
    public function __construct(private PanduanService $panduan) {}

    public function index(Request $request)
    {
        $peran = $request->user()->role?->value;

        return inertia('Panduan/Index', [
            'halaman' => $this->panduan->daftar()->map(fn ($p) => $p + [
                'untuk_saya' => in_array('semua', $p['peran'], true) || in_array($peran, $p['peran'], true),
            ])->values(),
            'q' => $request->query('q'),
            'hasilCari' => fn () => $request->filled('q') ? $this->panduan->cari((string) $request->query('q')) : [],
            'versi' => $this->panduan->versiTerbaru(),
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $halaman = $this->panduan->halaman($slug);
        abort_unless($halaman, 404);

        // membuka catatan perubahan = menandai versi terbaru sudah dibaca
        if ($slug === 'perubahan' && ($versi = $this->panduan->versiTerbaru())) {
            $request->user()->forceFill(['panduan_versi_dibaca' => $versi])->saveQuietly();
        }

        $daftar = $this->panduan->daftar()->values();
        $i = $daftar->search(fn ($p) => $p['slug'] === $slug);

        return inertia('Panduan/Show', [
            'halaman' => $halaman,
            'sebelum' => $i > 0 ? $daftar[$i - 1] : null,
            'sesudah' => $daftar[$i + 1] ?? null,
            'daftar' => $daftar->map(fn ($p) => Arr::only($p, ['slug', 'judul', 'bagian'])),
        ]);
    }

    /** Seluruh panduan dalam satu PDF (selalu sesuai versi aplikasi yang berjalan). */
    public function pdf()
    {
        $halaman = $this->panduan->daftar()->map(fn ($p) => $this->panduan->halaman($p['slug']));

        return Pdf::loadView('pdf.panduan', ['halaman' => $halaman, 'versi' => $this->panduan->versiTerbaru()])
            ->setPaper('a4')->download('panduan-simonkeb-v'.$this->panduan->versiTerbaru().'.pdf');
    }
}
