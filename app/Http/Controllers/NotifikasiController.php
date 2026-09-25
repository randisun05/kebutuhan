<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        return inertia('Notifikasi/Index', [
            'datas' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Tandai dibaca lalu buka tautan notifikasi. */
    public function open(Request $request, string $id)
    {
        $notif = $request->user()->notifications()->findOrFail($id);
        $notif->markAsRead();

        return redirect($notif->data['url'] ?? '/notifikasi');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
