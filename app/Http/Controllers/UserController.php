<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\InstansiPembina;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $datas = User::with('instansi:id,nama', 'pembina:id,nama')
            ->when(request('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%")))
            ->when(request('role'), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')
            ->paginate(15)->withQueryString();

        return inertia('Users/Index', [
            'datas' => $datas,
            'filters' => request()->only('q', 'role'),
            'roleOptions' => Role::options(),
        ]);
    }

    public function create()
    {
        return inertia('Users/Form', $this->formProps(null));
    }

    public function store(Request $request)
    {
        User::create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function edit(User $user)
    {
        return inertia('Users/Form', $this->formProps($user));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is(request()->user())) {
            // cegah admin mengunci dirinya sendiri
            unset($data['role'], $data['is_active']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->is(request()->user())) {
            return back()->with('error', 'Tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }

    private function formProps(?User $user): array
    {
        return [
            'data' => $user,
            'roleOptions' => Role::options(),
            'instansiOptions' => $this->instansiOptions(),
            'pembinaOptions' => InstansiPembina::where('is_active', true)->orderBy('nama')->get(['id', 'nama']),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'nip' => ['nullable', 'digits:18', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['required', Rule::enum(Role::class)],
            'instansi_id' => ['nullable', 'required_if:role,operator_instansi', 'exists:instansis,id'],
            'pembina_id' => ['nullable', 'required_if:role,pembina', 'exists:instansi_pembina,id'],
            'is_active' => 'boolean',
        ]);

        // cakupan pembina hanya berlaku untuk peran pembina
        if (($data['role'] ?? null) !== Role::Pembina->value) {
            $data['pembina_id'] = null;
        }

        return $data;
    }
}
