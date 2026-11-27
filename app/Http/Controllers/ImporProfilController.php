<?php

namespace App\Http\Controllers;

use App\Models\ImporProfil;
use App\Models\SumberData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Profil impor (khusus administrator): tata letak berkas Excel sumber sebagai data. Kolom ditulis sebagai huruf Excel
 * (A, B, ..., AX). Bila tim data mengubah tata letak, cukup ubah profil; kode tidak perlu disentuh.
 */
class ImporProfilController extends Controller
{
    private const HURUF = '/^[A-Za-z]{1,3}$/';

    public function index()
    {
        return inertia('ImporProfil/Index', ['datas' => ImporProfil::orderBy('nama')->get()]);
    }

    public function create()
    {
        return inertia('ImporProfil/Form', ['data' => null, 'sumberOptions' => $this->sumberOptions()]);
    }

    public function edit(ImporProfil $profil)
    {
        return inertia('ImporProfil/Form', ['data' => $profil, 'sumberOptions' => $this->sumberOptions()]);
    }

    public function store(Request $request)
    {
        ImporProfil::create($this->valid($request));

        return redirect()->route('impor-profil.index')->with('success', 'Profil impor ditambahkan.');
    }

    public function update(Request $request, ImporProfil $profil)
    {
        $profil->update($this->valid($request, $profil));

        return redirect()->route('impor-profil.index')->with('success', 'Profil impor diperbarui.');
    }

    public function destroy(ImporProfil $profil)
    {
        $profil->delete();

        return back()->with('success', 'Profil impor dihapus. Catatan impor lama tetap tersimpan.');
    }

    private function valid(Request $request, ?ImporProfil $profil = null): array
    {
        $huruf = ['required', 'regex:'.self::HURUF];
        $opsi = ['nullable', 'regex:'.self::HURUF];
        $data = $request->validate([
            'kode' => ['required', 'regex:/^[a-z0-9_-]+$/', 'max:32', Rule::unique('impor_profil', 'kode')->ignore($profil)],
            'nama' => 'required|string|max:255',
            'sheet' => 'required|string|max:255',
            'is_active' => 'boolean',
            'pemetaan' => 'required|array',
            'pemetaan.kolom' => 'required|array',
            'pemetaan.kolom.instansi' => $huruf,
            'pemetaan.kolom.jabatan' => $huruf,
            'pemetaan.kolom.jenis_instansi' => $opsi, 'pemetaan.kolom.pokja' => $opsi, 'pemetaan.kolom.status_rekom' => $opsi, 'pemetaan.kolom.jenis_rekom' => $opsi,
            'pemetaan.kolom.wilayah_kerja' => $opsi, 'pemetaan.kolom.sesuai_bezetting' => $opsi,
            'pemetaan.kolom.catatan' => 'nullable|array|max:5',
            'pemetaan.kolom.catatan.*' => $huruf,
            'pemetaan.surat' => 'nullable|array|max:10',
            'pemetaan.surat.*.jenis' => ['required', 'regex:/^[a-z0-9_]+$/', 'max:20'],
            'pemetaan.surat.*.nomor' => $huruf,
            'pemetaan.surat.*.tanggal' => $opsi, 'pemetaan.surat.*.tautan' => $opsi, 'pemetaan.surat.*.ttd_jabatan' => $opsi, 'pemetaan.surat.*.ttd_nama' => $opsi,
            'pemetaan.blok' => 'required|array|min:1|max:12',
            'pemetaan.blok.*.sumber' => ['required', Rule::exists('sumber_data', 'kode')->where('adapter', 'fakta')],
            'pemetaan.blok.*.kolom_awal' => $huruf,
            'pemetaan.blok.*.lebar' => 'nullable|integer|min:1|max:12',
            'pemetaan.blok.*.cadangan_kolom_awal' => $opsi,
        ]);

        // buang isian kosong agar tersimpan rapi
        $data['pemetaan']['kolom'] = array_filter($data['pemetaan']['kolom'], fn ($v) => $v !== null && $v !== [] && $v !== '');
        $data['pemetaan']['surat'] = array_values(array_map(fn ($s) => array_filter($s, fn ($v) => $v !== null && $v !== ''), $data['pemetaan']['surat'] ?? []));
        $data['pemetaan']['blok'] = array_values(array_map(fn ($b) => array_filter($b, fn ($v) => $v !== null && $v !== ''), $data['pemetaan']['blok']));

        return $data;
    }

    private function sumberOptions()
    {
        return SumberData::where('adapter', 'fakta')->where('is_active', true)->orderBy('urutan')->get(['kode', 'nama']);
    }
}
