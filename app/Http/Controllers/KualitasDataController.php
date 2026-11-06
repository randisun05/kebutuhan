<?php

namespace App\Http\Controllers;

use App\Services\KualitasDataService;

class KualitasDataController extends Controller
{
    public function index(KualitasDataService $service)
    {
        $instansiOptions = $this->instansiOptions();
        $instansiId = $this->selectedInstansiId() ?? $instansiOptions->first()?->id;
        if ($instansiId) {
            $this->authorizeInstansi((int) $instansiId);
        }

        $pemeriksaan = $instansiId ? $service->periksa((int) $instansiId) : [];

        return inertia('KualitasData/Index', [
            'instansiOptions' => $instansiOptions,
            'instansiId' => $instansiId,
            'pemeriksaan' => $pemeriksaan,
            'totalTemuan' => collect($pemeriksaan)->sum('jumlah'),
        ]);
    }
}
