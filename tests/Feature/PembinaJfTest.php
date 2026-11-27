<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use App\Models\User;
use App\Services\RekomendasiJfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\Feature\Concerns\BuildsRekomendasiExcel;
use Tests\TestCase;

/**
 * Banyak instansi pembina dengan metode yang sama (BKN untuk JF SDM Aparatur, pembina lain untuk JF-nya), dan
 * pengguna pembina hanya dapat mengakses data haknya sendiri.
 */
class PembinaJfTest extends TestCase
{
    use BuildsKebutuhanData, BuildsRekomendasiExcel, RefreshDatabase;

    /** Impor satu baris untuk tiap pembina dan kembalikan [pembinaBkn, pembinaLan]. */
    private function dataDuaPembina(): array
    {
        $bkn = $this->pembinaBkn();
        $lan = $this->pembinaKedua('lan');
        $svc = app(RekomendasiJfImportService::class);

        $p1 = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur')]);
        $svc->simpan($svc->baca($p1, ImporProfil::where('pembina_id', $bkn->id)->first()), null, 'bkn.xlsx');
        $p2 = $this->buatExcel([[3 => 'Instansi A', 4 => 'JF Milik LAN', 7 => 'DONE', 22 => 8, 23 => 4]], 'Data LAN');
        $svc->simpan($svc->baca($p2, ImporProfil::where('pembina_id', $lan->id)->first()), null, 'lan.xlsx');
        unlink($p1);
        unlink($p2);

        return [$bkn, $lan];
    }

    public function test_pengguna_pembina_hanya_melihat_data_pembinanya(): void
    {
        [$bkn, $lan] = $this->dataDuaPembina();
        $userBkn = $this->penggunaPembina($bkn);
        $userLan = $this->penggunaPembina($lan);

        $this->actingAs($userBkn)->get('/rekomendasi-jf')->assertOk()
            ->assertInertia(fn ($p) => $p->where('pembinaId', $bkn->id)->has('pembinaOptions', 1)->where('ringkasan.baris', 1)->where('datas.data.0.jabatan_pokok.nama', 'Analis SDM Aparatur'));
        // meminta pembina lain lewat parameter tetap dikembalikan ke pembinanya sendiri
        $this->get("/rekomendasi-jf?pembina_id={$lan->id}")->assertInertia(fn ($p) => $p->where('pembinaId', $bkn->id)->where('datas.data.0.jabatan_pokok.nama', 'Analis SDM Aparatur'));

        $milikLan = RekomendasiJf::whereHas('jabatanPokok', fn ($q) => $q->where('pembina_id', $lan->id))->firstOrFail();
        $milikBkn = RekomendasiJf::whereHas('jabatanPokok', fn ($q) => $q->where('pembina_id', $bkn->id))->firstOrFail();
        $this->get("/rekomendasi-jf/{$milikLan->id}")->assertForbidden();
        $this->get("/rekomendasi-jf/{$milikBkn->id}")->assertOk();

        $this->actingAs($userLan)->get('/rekomendasi-jf')->assertInertia(fn ($p) => $p->where('pembinaId', $lan->id)->where('ringkasan.baris', 1)->where('datas.data.0.jabatan_pokok.nama', 'JF Milik LAN')
            ->where('sumber.0.kode', 'rekom_lan'));
        $this->get("/rekomendasi-jf/{$milikBkn->id}")->assertForbidden();
    }

    public function test_banyak_pengguna_dalam_satu_pembina_melihat_data_yang_sama(): void
    {
        [$bkn] = $this->dataDuaPembina();
        foreach ([$this->penggunaPembina($bkn), $this->penggunaPembina($bkn)] as $u) {
            $this->actingAs($u)->get('/rekomendasi-jf')->assertInertia(fn ($p) => $p->where('ringkasan.baris', 1)->where('bolehImpor', true));
        }
        $this->assertSame(2, $bkn->users()->count());
    }

    public function test_pengguna_pembina_tidak_dapat_mengimpor_dengan_profil_pembina_lain(): void
    {
        Storage::fake('local');
        [$bkn, $lan] = $this->dataDuaPembina();
        $profilLan = ImporProfil::where('pembina_id', $lan->id)->firstOrFail();
        $profilBkn = ImporProfil::where('pembina_id', $bkn->id)->firstOrFail();
        $path = $this->buatExcel([$this->baris('Instansi B', 'Analis SDM Aparatur')]);
        $file = fn () => new UploadedFile($path, 'rekap.xlsx', null, null, true);

        $this->actingAs($this->penggunaPembina($bkn));
        $this->postJson('/rekomendasi-jf/pratinjau', ['file' => $file(), 'profil_id' => $profilLan->id])->assertForbidden();
        $res = $this->postJson('/rekomendasi-jf/pratinjau', ['file' => $file(), 'profil_id' => $profilBkn->id])->assertOk();
        // token sah dari profil sendiri tidak dapat dipakai dengan profil pembina lain
        $this->post('/rekomendasi-jf/impor', ['token' => $res->json('token'), 'profil_id' => $profilLan->id])->assertForbidden();
        $this->post('/rekomendasi-jf/impor', ['token' => $res->json('token'), 'profil_id' => $profilBkn->id])->assertSessionHas('success');
        unlink($path);
    }

    public function test_berkas_satu_pembina_tidak_dapat_menulis_jabatan_pembina_lain(): void
    {
        $bkn = $this->pembinaBkn();
        $lan = $this->pembinaKedua('lan');
        // berkas berisi jabatan BKN diimpor memakai profil LAN: jabatan BKN tidak dikenali oleh LAN dan dilewati
        $path = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur')], 'Data LAN');
        $h = app(RekomendasiJfImportService::class)->baca($path, ImporProfil::where('pembina_id', $lan->id)->first());
        unlink($path);

        $this->assertSame([], $h['baris']);
        $this->assertTrue(collect($h['peringatan'])->contains(fn ($p) => str_contains($p, 'Analis SDM Aparatur') && str_contains($p, 'master Jabatan Pokok')));
    }

    public function test_pengguna_pembina_hanya_mengelola_master_milik_pembinanya(): void
    {
        [$bkn, $lan] = $this->dataDuaPembina();
        $jabLan = JfJabatanPokok::where('pembina_id', $lan->id)->firstOrFail();
        $sumberLan = SumberData::where('pembina_id', $lan->id)->firstOrFail();
        $profilLan = ImporProfil::where('pembina_id', $lan->id)->firstOrFail();
        $this->actingAs($this->penggunaPembina($bkn));

        // data milik pembina lain tidak dapat diubah atau dihapus
        $this->put("/jf-master/jabatan-pokok/{$jabLan->id}", ['kode' => $jabLan->kode, 'nama' => 'Dibajak', 'rumpun' => 'keahlian'])->assertForbidden();
        $this->delete("/jf-master/jabatan-pokok/{$jabLan->id}")->assertForbidden();
        $this->put("/jf-master/sumber/{$sumberLan->id}", ['kode' => $sumberLan->kode, 'nama' => 'Dibajak', 'jenis' => 'kebutuhan', 'tingkat' => 'instansi', 'sifat' => 'foto', 'adapter' => 'fakta'])->assertForbidden();
        $this->get("/impor-profil/{$profilLan->id}/edit")->assertForbidden();
        $this->put("/impor-profil/{$profilLan->id}", ['kode' => $profilLan->kode, 'nama' => 'x', 'sheet' => 'x', 'pemetaan' => $profilLan->pemetaan])->assertForbidden();
        $this->delete("/impor-profil/{$profilLan->id}")->assertForbidden();
        $this->assertSame('JF Milik LAN', $jabLan->fresh()->nama);

        // data baru selalu menjadi milik pembinanya sendiri, walau payload menyebut pembina lain
        $this->post('/jf-master/jabatan-pokok', ['pembina_id' => $lan->id, 'kode' => 'jf-baru', 'nama' => 'JF Baru', 'rumpun' => 'keahlian'])->assertSessionHasNoErrors();
        $this->assertSame($bkn->id, JfJabatanPokok::where('kode', 'jf-baru')->value('pembina_id'));
        // kode sumber boleh sama di pembina berbeda, dan blok profil hanya boleh memakai sumber pembinanya
        $this->post('/jf-master/sumber', ['kode' => 'rekom_lan', 'nama' => 'Rekom Lan di BKN', 'jenis' => 'kebutuhan', 'tingkat' => 'instansi', 'sifat' => 'foto', 'adapter' => 'fakta'])->assertSessionHasNoErrors();
        $pemetaan = ImporProfil::where('pembina_id', $bkn->id)->first()->pemetaan;
        $pemetaan['blok'] = [['sumber' => 'rekom_lan', 'kolom_awal' => 'W']];
        $this->post('/impor-profil', ['kode' => 'p-baru', 'nama' => 'P', 'sheet' => 'S', 'pemetaan' => $pemetaan])->assertSessionHasNoErrors();
        $this->assertSame($bkn->id, ImporProfil::where('kode', 'p-baru')->value('pembina_id'));
        $pemetaan['blok'] = [['sumber' => 'tidak-ada', 'kolom_awal' => 'W']];
        $this->post('/impor-profil', ['kode' => 'p-baru2', 'nama' => 'P', 'sheet' => 'S', 'pemetaan' => $pemetaan])->assertSessionHasErrors('pemetaan.blok.0.sumber');

        // jenjang bersama, alias, dan instansi pembina khusus administrator
        $this->post('/jf-master/jenjang', ['kode' => 'x', 'nama' => 'X', 'rumpun' => 'keahlian'])->assertForbidden();
        $this->post('/jf-master/pembina', ['kode' => 'baru', 'nama' => 'Baru'])->assertForbidden();
        $this->get('/alias-instansi')->assertForbidden();
        $this->get('/jf-master')->assertOk()->assertInertia(fn ($p) => $p->where('pembinaId', $bkn->id)->where('bolehAdmin', false)->has('jabatan', 5)->where('pembina', []));
    }

    public function test_pengguna_pembina_ditolak_dari_modul_operasional(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $this->abk($unit, $jab, 2);
        $this->actingAs($this->penggunaPembina($this->pembinaBkn()));

        foreach (['/monitoring', '/anjab', '/pegawai', '/usulan', '/penetapan', '/formasi', '/laporan', '/analitik', '/kualitas-data', '/siasn', '/users', '/instansi', '/jabatan', '/audit', "/monitoring/instansi/{$instansi->id}", '/peringatan', '/histori', '/unit-kerja'] as $url) {
            $this->get($url)->assertForbidden();
        }
        // halaman utama dialihkan; panduan, akun, dan modul pembina terbuka
        $this->get('/dashboard')->assertRedirect('/rekomendasi-jf');
        $this->get('/')->assertRedirect();
        $this->get('/panduan')->assertOk();
        $this->get('/akun')->assertOk();
        $this->get('/rekomendasi-jf')->assertOk();
        // kebutuhan sementara (modul operasional) tertutup walau lewat POST
        $this->post('/kebutuhan-sementara', ['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah' => 3])->assertForbidden();
    }

    public function test_operator_instansi_melihat_data_dari_semua_pembina_untuk_instansinya(): void
    {
        [$bkn, $lan] = $this->dataDuaPembina();
        $instansi = $this->instansi('KAB-A');
        $alias = InstansiAlias::where('nama', 'Instansi A')->firstOrFail();
        $alias->update(['ref_instansi_id' => RefInstansiBkn::create(['siasn_id' => 'R1', 'nama' => 'Instansi A', 'instansi_id' => $instansi->id])->id]);

        $this->actingAs($this->user(Role::OperatorInstansi, $instansi));
        $this->get("/rekomendasi-jf?pembina_id={$bkn->id}")->assertInertia(fn ($p) => $p->has('pembinaOptions', 2)->where('ringkasan.baris', 1)->where('bolehImpor', false));
        $this->get("/rekomendasi-jf?pembina_id={$lan->id}")->assertInertia(fn ($p) => $p->where('pembinaId', $lan->id)->where('ringkasan.baris', 1)->where('datas.data.0.jabatan_pokok.nama', 'JF Milik LAN'));
    }

    public function test_admin_mengelola_pembina_dan_pengguna_pembina_wajib_punya_pembina(): void
    {
        $this->actingAs($this->user(Role::Admin));

        $this->post('/jf-master/pembina', ['kode' => 'baru', 'nama' => 'Pembina Baru', 'is_active' => true])->assertSessionHas('success');
        $this->post('/jf-master/pembina', ['kode' => 'baru', 'nama' => 'Lain'])->assertSessionHasErrors('kode');
        $baru = InstansiPembina::where('kode', 'baru')->firstOrFail();

        // pembina yang punya data atau pengguna tidak dapat dihapus
        $this->delete('/jf-master/pembina/'.$this->pembinaBkn()->id)->assertSessionHas('error');
        $this->post('/users', ['name' => 'Pembina Baru', 'email' => 'pb@test.local', 'password' => 'password-kuat-1', 'role' => 'pembina', 'is_active' => true])->assertSessionHasErrors('pembina_id');
        $this->post('/users', ['name' => 'Pembina Baru', 'email' => 'pb@test.local', 'password' => 'password-kuat-1', 'role' => 'pembina', 'pembina_id' => $baru->id, 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame($baru->id, User::where('email', 'pb@test.local')->value('pembina_id'));
        $this->delete("/jf-master/pembina/{$baru->id}")->assertSessionHas('error');

        // peran selain pembina tidak membawa cakupan pembina
        $this->post('/users', ['name' => 'Pimpinan', 'email' => 'pm@test.local', 'password' => 'password-kuat-1', 'role' => 'pimpinan', 'pembina_id' => $baru->id, 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'pm@test.local')->value('pembina_id'));

        // administrator dapat melihat semua pembina dan memilih salah satunya; jabatan baru dimiliki pembina terpilih
        $this->get('/jf-master')->assertInertia(fn ($p) => $p->has('pembinaOptions', 2)->where('bolehAdmin', true)->has('pembina', 2));
        $this->post('/jf-master/jabatan-pokok', ['pembina_id' => $baru->id, 'kode' => 'jf-pb', 'nama' => 'JF Pembina Baru', 'rumpun' => 'keahlian'])->assertSessionHasNoErrors();
        $this->assertSame($baru->id, JfJabatanPokok::where('kode', 'jf-pb')->value('pembina_id'));
        $this->post('/jf-master/jabatan-pokok', ['kode' => 'jf-tanpa', 'nama' => 'Tanpa Pembina', 'rumpun' => 'keahlian'])->assertSessionHasErrors('pembina_id');
    }
}
