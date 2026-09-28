<?php

namespace App\Http\Controllers;

use Spatie\Activitylog\Models\Activity;

/** Log audit perubahan data master, pegawai, ABK, dan pengguna. */
class AuditController extends Controller
{
    public function index()
    {
        $datas = Activity::with('causer:id,name')
            ->when(request('log'), fn ($q, $l) => $q->where('log_name', $l))
            ->when(request('event'), fn ($q, $e) => $q->where('event', $e))
            ->when(request('q'), fn ($q, $s) => $q->where('properties', 'like', "%{$s}%"))
            ->latest()->paginate(25)->withQueryString();

        $datas->getCollection()->transform(fn (Activity $a) => [
            'id' => $a->id,
            'log_name' => $a->log_name,
            'event' => $a->event,
            'description' => $a->description,
            'subject' => class_basename((string) $a->subject_type).' #'.$a->subject_id,
            'causer' => $a->causer?->name ?? 'sistem',
            'perubahan' => $a->properties->only(['attributes', 'old'])->toArray(),
            'created_at' => $a->created_at,
        ]);

        return inertia('Audit/Index', [
            'datas' => $datas,
            'filters' => request()->only('log', 'event', 'q'),
            'logOptions' => Activity::query()->distinct()->pluck('log_name'),
        ]);
    }
}
