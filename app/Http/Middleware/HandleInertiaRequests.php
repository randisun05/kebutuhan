<?php

namespace App\Http\Middleware;

use App\Enums\UsulanStatus;
use App\Models\Peringatan;
use App\Models\Usulan;
use App\Services\PanduanService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'session' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
                'warning' => fn () => $request->session()->get('warning'),
                'importErrors' => fn () => $request->session()->get('importErrors'),
            ],
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'role_label' => $user->role_label,
                    'instansi_id' => $user->instansi_id,
                    'instansi' => $user->instansi?->only(['id', 'kode', 'nama']),
                ] : null,
            ],
            // jumlah usulan yang menunggu tindakan user (badge sidebar)
            'inbox' => fn () => $user ? $this->inboxCount($user) : 0,
            // jumlah peringatan terbuka tingkat kritis/tinggi (badge sidebar)
            'peringatan' => fn () => $user ? Peringatan::whereIn('status', ['aktif'])
                ->whereIn('tingkat', ['kritis', 'tinggi'])
                ->when($user->isScopedToInstansi(), fn ($q) => $q->where('instansi_id', $user->instansi_id))
                ->count() : 0,
            // panduan: peta menu -> halaman panduan & penanda ada catatan perubahan baru
            'panduan' => fn () => $user ? (function () use ($user) {
                $svc = app(PanduanService::class);
                $versi = $svc->versiTerbaru();

                return [
                    'peta' => $svc->petaMenu(),
                    'versi' => $versi,
                    'baru' => $versi && $user->panduan_versi_dibaca !== $versi,
                ];
            })() : null,
            'notifikasi' => fn () => $user ? [
                'unread' => $user->unreadNotifications()->count(),
                'items' => $user->notifications()->limit(6)->get()->map(fn ($n) => [
                    'id' => $n->id, 'data' => $n->data, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at,
                ]),
            ] : null,
        ]);
    }

    private function inboxCount($user): int
    {
        $statuses = match ($user->role?->value) {
            'verifikator_bkn' => [UsulanStatus::Diajukan, UsulanStatus::VerifikasiBkn],
            'validator_kemenpan' => [UsulanStatus::PertimbanganTeknis, UsulanStatus::ValidasiKemenpan],
            'operator_instansi' => [UsulanStatus::Dikembalikan],
            'admin' => [UsulanStatus::Diajukan, UsulanStatus::VerifikasiBkn, UsulanStatus::PertimbanganTeknis, UsulanStatus::ValidasiKemenpan],
            default => [],
        };

        if (! $statuses) {
            return 0;
        }

        return Usulan::whereIn('status', $statuses)
            ->when($user->isScopedToInstansi(), fn ($q) => $q->where('instansi_id', $user->instansi_id))
            ->count();
    }
}
