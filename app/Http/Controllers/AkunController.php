<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AkunController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return inertia('Akun/Index', [
            'twoFactorEnabled' => ! is_null($user->two_factor_secret),
            'twoFactorConfirmed' => ! is_null($user->two_factor_confirmed_at),
            'wajib2fa' => in_array($user->role?->value, config('simonkeb.wajib_2fa'), true),
        ]);
    }
}
