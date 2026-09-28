<?php

namespace App\Http\Controllers;

use App\Support\Referensi;

class PedomanController extends Controller
{
    public function __invoke()
    {
        return inertia('Pedoman/Index', [
            'wke' => Referensi::WAKTU_KERJA_EFEKTIF,
            'horizon' => Referensi::HORIZON_PROYEKSI_TAHUN,
        ]);
    }
}
