<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Models\Usulan;
use App\Models\UsulanDetail;
use App\Services\SaranUsulanService;
use App\Services\UsulanWorkflow;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsulanController extends Controller
{
    public function __construct(private UsulanWorkflow $workflow) {}

    public function index()
    {
        $user = request()->user();
        $inbox = request()->boolean('inbox');

        $inboxStatuses = match ($user->role?->value) {
            'verifikator_bkn' => [UsulanStatus::Diajukan, UsulanStatus::VerifikasiBkn],
            'validator_kemenpan' => [UsulanStatus::PertimbanganTeknis, UsulanStatus::ValidasiKemenpan],
            'operator_instansi' => [UsulanStatus::Draft, UsulanStatus::Dikembalikan],
            default => [UsulanStatus::Diajukan, UsulanStatus::VerifikasiBkn, UsulanStatus::PertimbanganTeknis, UsulanStatus::ValidasiKemenpan],
        };

        $datas = $this->scoped(Usulan::query())
            ->with('instansi:id,kode,nama')
            ->withSum('details as total_usul', 'jumlah_usul')
            ->withSum('details as total_rekomendasi', 'jumlah_rekomendasi')
            ->withSum('details as total_ditetapkan', 'jumlah_ditetapkan')
            ->when($inbox, fn ($q) => $q->whereIn('status', $inboxStatuses))
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('tahun'), fn ($q, $t) => $q->where('tahun', $t))
            ->when($this->selectedInstansiId(), fn ($q, $i) => $q->where('instansi_id', $i))
            ->when(request('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nomor', 'like', "%{$s}%")->orWhere('perihal', 'like', "%{$s}%")))
            ->latest('updated_at')
            ->paginate(15)->withQueryString();

        return inertia('Usulan/Index', [
            'datas' => $datas,
            'filters' => request()->only('status', 'tahun', 'q', 'instansi_id', 'inbox'),
            'statusOptions' => UsulanStatus::options(),
            'instansiOptions' => $this->instansiOptions(),
            'canCreate' => $user->hasRole(Role::Admin, Role::OperatorInstansi),
        ]);
    }

    public function create()
    {
        abort_unless($this->canManage(), 403);

        return inertia('Usulan/Form', [
            'data' => null,
            'instansiOptions' => $this->instansiOptions(),
            'instansiId' => $this->selectedInstansiId(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canManage(), 403);
        $data = $this->validated($request);

        $usulan = DB::transaction(function () use ($request, $data) {
            $usulan = Usulan::create($data + ['status' => UsulanStatus::Draft, 'created_by' => $request->user()->id]);
            $usulan->logs()->create([
                'user_id' => $request->user()->id, 'aksi' => 'buat', 'ke_status' => UsulanStatus::Draft->value,
                'catatan' => 'Usulan dibuat.',
            ]);

            return $usulan;
        });

        return redirect()->route('usulan.show', $usulan)->with('success', 'Usulan dibuat. Lengkapi rincian jabatan yang diusulkan.');
    }

    public function show(Usulan $usulan)
    {
        $this->authorizeInstansi($usulan->instansi_id);
        $user = request()->user();

        $usulan->load([
            'instansi:id,kode,nama',
            'pembuat:id,name',
            'details' => fn ($q) => $q->with(['unitKerja:id,nama', 'jabatan:id,nama,jenis,kualifikasi_pendidikan,estimasi_biaya_tahunan'])->orderBy('prioritas')->orderBy('unit_kerja_id'),
            'logs.user:id,name,role',
            'penetapan.penetap:id,name',
        ]);

        $editable = $usulan->status->editable() && $this->canManage();

        return inertia('Usulan/Show', [
            'data' => $usulan,
            // pemeriksaan rincian terhadap kondisi terkini (hanya informasi, tidak memblokir alur)
            // setelah ditetapkan/ditolak formasinya sendiri sudah masuk perhitungan, jadi tidak diperiksa lagi
            'pemeriksaan' => fn () => in_array($usulan->status, [UsulanStatus::Ditetapkan, UsulanStatus::Ditolak], true)
                ? null : app(SaranUsulanService::class)->periksa($usulan),
            'step' => $usulan->status->step(),
            'actions' => $this->workflow->availableActions($user, $usulan),
            'editable' => $editable,
            'prioritasOptions' => Referensi::PRIORITAS,
            'unitOptions' => $editable ? UnitKerja::flatTree($usulan->instansi_id) : [],
            'jabatanOptions' => $editable ? Jabatan::where('is_active', true)->orderBy('jenis')->orderBy('nama')->get(['id', 'nama', 'jenis']) : [],
        ]);
    }

    public function edit(Usulan $usulan)
    {
        $this->guardEditable($usulan);

        return inertia('Usulan/Form', [
            'data' => $usulan,
            'instansiOptions' => $this->instansiOptions(),
            'instansiId' => $usulan->instansi_id,
        ]);
    }

    public function update(Request $request, Usulan $usulan)
    {
        $this->guardEditable($usulan);
        $data = $this->validated($request, $usulan);
        unset($data['instansi_id']);

        if ($request->hasFile('surat_pengantar') && $usulan->surat_pengantar) {
            Storage::delete($usulan->surat_pengantar);
        }

        $usulan->update(array_filter($data, fn ($v, $k) => $k !== 'surat_pengantar' || $v, ARRAY_FILTER_USE_BOTH));

        return redirect()->route('usulan.show', $usulan)->with('success', 'Usulan berhasil diperbarui.');
    }

    public function destroy(Usulan $usulan)
    {
        $this->guardEditable($usulan);
        abort_unless($usulan->status === UsulanStatus::Draft, 422, 'Hanya usulan draft yang dapat dihapus.');

        if ($usulan->surat_pengantar) {
            Storage::delete($usulan->surat_pengantar);
        }
        $usulan->delete();

        return redirect()->route('usulan.index')->with('success', 'Usulan berhasil dihapus.');
    }

    /**
     * Tarik rincian otomatis: untuk setiap posisi yang kekurangan, jumlah usul = saran sistem
     * (lihat SaranUsulanService: kekurangan + pensiun − formasi belum terisi − usulan lain yang
     * sedang diproses − redistribusi bila dipilih). Kebutuhan dari ABK final yang berlaku untuk
     * tahun usulan, atau kebutuhan sementara bila belum ada ABK.
     */
    public function tarikAbk(Request $request, Usulan $usulan, SaranUsulanService $saran)
    {
        $this->guardEditable($usulan);
        $redistribusi = $request->boolean('redistribusi', true);

        $posisi = $saran->hitung($usulan->instansi_id, $usulan->tahun, $redistribusi, $usulan->id)
            ->filter(fn ($p) => $p['saran'] > 0);
        $kualifikasi = Jabatan::whereIn('id', $posisi->pluck('jabatan_id'))->pluck('kualifikasi_pendidikan', 'id');

        DB::transaction(function () use ($posisi, $usulan, $kualifikasi) {
            foreach ($posisi as $p) {
                $usulan->details()->updateOrCreate(
                    ['unit_kerja_id' => $p['unit_kerja_id'], 'jabatan_id' => $p['jabatan_id']],
                    $this->snapshot($p) + [
                        'jumlah_usul' => $p['saran'],
                        // jabatan yang sama sekali belum terisi diprioritaskan
                        'prioritas' => $p['existing'] === 0 ? 1 : ($p['existing'] - $p['pensiun'] < $p['kebutuhan'] / 2 ? 1 : 2),
                        'kualifikasi_pendidikan' => $kualifikasi[$p['jabatan_id']] ?? null,
                        'keterangan' => $p['redistribusi_masuk'] ? Str::limit("Sudah dikurangi {$p['redistribusi_masuk']} pegawai pindahan dari {$p['redistribusi_dari']}", 255) : null,
                    ]
                );
            }
        });

        $count = $posisi->count();

        return back()->with($count ? 'success' : 'info', $count
            ? "{$count} rincian jabatan ditarik dari kebutuhan (ABK final/kebutuhan sementara)."
            : 'Tidak ada jabatan yang perlu diusulkan: kekurangan sudah tertutup oleh formasi, usulan lain, atau redistribusi.');
    }

    public function storeDetail(Request $request, Usulan $usulan, SaranUsulanService $saran)
    {
        $this->guardEditable($usulan);

        $data = $request->validate([
            'unit_kerja_id' => ['required', Rule::exists('unit_kerjas', 'id')->where('instansi_id', $usulan->instansi_id)],
            'jabatan_id' => ['required', 'exists:jabatans,id',
                Rule::unique('usulan_details')->where('usulan_id', $usulan->id)->where('unit_kerja_id', $request->integer('unit_kerja_id'))],
            'jumlah_usul' => 'required|integer|min:1|max:10000',
            'prioritas' => 'nullable|integer|in:1,2,3',
            'kualifikasi_pendidikan' => 'nullable|string|max:255',
            'keterangan' => 'nullable|string|max:255',
        ], ['jabatan_id.unique' => 'Jabatan pada unit ini sudah ada di rincian usulan.']);

        // rincian manual tetap boleh walaupun posisi belum punya kebutuhan; selisihnya ditandai di halaman usulan
        $p = $saran->hitung($usulan->instansi_id, $usulan->tahun, false, $usulan->id)->get($data['unit_kerja_id'].'-'.$data['jabatan_id']);
        $usulan->details()->create($data + ($p ? $this->snapshot($p) : []));

        $pesan = 'Rincian jabatan ditambahkan.';
        if (! $p || $p['sumber'] === null) {
            return back()->with('warning', $pesan.' Posisi ini belum memiliki ABK final maupun kebutuhan sementara.');
        }

        return $data['jumlah_usul'] > $p['saran']
            ? back()->with('warning', $pesan." Jumlah usul melebihi saran sistem ({$p['saran']}).")
            : back()->with('success', $pesan);
    }

    /** Kolom snapshot kondisi posisi saat rincian disusun. */
    private function snapshot(array $p): array
    {
        return [
            'kebutuhan_abk' => $p['kebutuhan'],
            'sumber_kebutuhan' => $p['sumber'],
            'existing' => $p['existing'],
            'proyeksi_pensiun' => $p['pensiun'],
            'formasi_belum_terisi' => $p['formasi'],
            'usulan_proses' => $p['usulan_proses'],
            'redistribusi_masuk' => $p['redistribusi_masuk'],
        ];
    }

    public function updateDetails(Request $request, Usulan $usulan)
    {
        $this->guardEditable($usulan);

        $data = $request->validate([
            'details' => 'required|array',
            'details.*.id' => ['required', Rule::exists('usulan_details', 'id')->where('usulan_id', $usulan->id)],
            'details.*.jumlah_usul' => 'required|integer|min:0|max:10000',
            'details.*.prioritas' => 'required|integer|in:1,2,3',
            'details.*.kualifikasi_pendidikan' => 'nullable|string|max:255',
            'details.*.keterangan' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['details'] as $row) {
                UsulanDetail::whereKey($row['id'])->update(collect($row)->except('id')->all());
            }
        });

        return back()->with('success', 'Rincian usulan disimpan.');
    }

    public function destroyDetail(Usulan $usulan, UsulanDetail $detail)
    {
        $this->guardEditable($usulan);
        abort_unless((int) $detail->usulan_id === (int) $usulan->id, 404);
        $detail->delete();

        return back()->with('success', 'Rincian dihapus.');
    }

    /** Jalankan aksi alur kerja (ajukan, verifikasi, pertimbangan teknis, validasi, tetapkan, kembalikan, tolak). */
    public function action(Request $request, Usulan $usulan)
    {
        $this->authorizeInstansi($usulan->instansi_id);

        $request->validate([
            'aksi' => ['required', Rule::in(array_keys(UsulanWorkflow::TRANSITIONS))],
            'catatan' => 'nullable|string|max:2000',
            'details' => 'nullable|array',
            'details.*' => 'nullable|integer|min:0',
            'nomor_sk' => 'nullable|string|max:100',
            'tanggal_sk' => 'nullable|date',
            'keterangan' => 'nullable|string|max:2000',
            'file_sk' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $payload = $request->only('catatan', 'details', 'nomor_sk', 'tanggal_sk', 'keterangan');
        if ($request->input('aksi') === 'tetapkan' && $request->hasFile('file_sk')) {
            $payload['file_sk'] = $request->file('file_sk')->store('penetapan');
        }

        $aksi = $request->input('aksi');
        $this->workflow->transition($request->user(), $usulan, $aksi, $payload);

        return redirect()->route('usulan.show', $usulan)->with('success', UsulanWorkflow::LABELS[$aksi].' berhasil.');
    }

    /** Unduh berkas usulan (surat pengantar / SK penetapan) dengan pengecekan hak akses. */
    public function berkas(Usulan $usulan, string $jenis)
    {
        $this->authorizeInstansi($usulan->instansi_id);

        $path = match ($jenis) {
            'surat-pengantar' => $usulan->surat_pengantar,
            'sk' => $usulan->penetapan?->file_sk,
            default => null,
        };

        abort_unless($path && Storage::exists($path), 404);

        return Storage::download($path);
    }

    private function canManage(): bool
    {
        return in_array(request()->user()->role?->value, ['admin', 'operator_instansi'], true);
    }

    private function guardEditable(Usulan $usulan): void
    {
        $this->authorizeInstansi($usulan->instansi_id);
        abort_unless($this->canManage(), 403);
        abort_unless($usulan->status->editable(), 403, 'Usulan yang sedang diproses tidak dapat diubah.');
    }

    private function validated(Request $request, ?Usulan $usulan = null): array
    {
        $data = $request->validate([
            'instansi_id' => [$usulan ? 'nullable' : 'required', 'exists:instansis,id'],
            'tahun' => 'required|integer|min:2020|max:2100',
            'periode' => 'nullable|string|max:20',
            'jenis_asn' => 'required|in:pns,pppk',
            'perihal' => 'required|string|max:255',
            'keterangan' => 'nullable|string',
            'surat_pengantar' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        if (! $usulan) {
            $this->authorizeInstansi((int) $data['instansi_id']);
        }

        $data['surat_pengantar'] = $request->hasFile('surat_pengantar')
            ? $request->file('surat_pengantar')->store('usulan')
            : null;

        return $data;
    }
}
