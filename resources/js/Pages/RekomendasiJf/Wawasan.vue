<template>
    <Head title="Wawasan Rekomendasi JF" />
    <Link href="/rekomendasi-jf" class="small"><i class="fa fa-angle-left"></i> Daftar rekomendasi</Link>
    <div class="page-title">Wawasan Rekomendasi JF</div>
    <div class="page-subtitle">Ringkasan dari data rekomendasi dan penetapan untuk menentukan sasaran penataan dan pendampingan. Semua angka adalah <b>foto saat dokumen</b> (bezetting saat rekomendasi), bukan kondisi sekarang.</div>

    <div v-if="pembinaOptions.length > 1" class="mb-3 d-flex align-items-center gap-2">
        <span class="small text-muted">Instansi pembina:</span>
        <select class="form-select form-select-sm w-auto" :value="pembinaId" @change="router.get('/rekomendasi-jf/wawasan', { pembina_id: $event.target.value })"><option v-for="p in pembinaOptions" :key="p.id" :value="p.id">{{ p.nama }}</option></select>
    </div>

    <div v-if="!w.lengkap" class="alert alert-warning small">Wawasan memerlukan minimal satu sumber bezetting dan satu sumber kebutuhan aktif pada pembina ini (menu Master JF & Sumber), serta data yang sudah diimpor.</div>

    <template v-else>
        <div class="small text-muted mb-3">Sumber yang dipakai: bezetting = <b>{{ w.peran.bezetting }}</b>; rekomendasi = <b>{{ w.peran.rekom }}</b><template v-if="w.peran.penetapan">; penetapan = <b>{{ w.peran.penetapan }}</b></template>. Ambang dapat diubah lewat konfigurasi.</div>

        <!-- 1. cakupan -->
        <div class="card mb-3">
            <div class="card-header">Cakupan rekomendasi per jabatan</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Jabatan</th><th class="num">Instansi</th><th class="num">Sudah rekom</th><th class="num">Sudah ditetapkan</th><th class="num">Belum usul</th></tr></thead>
                <tbody><tr v-for="c in w.cakupan" :key="c.jabatan"><td>{{ c.jabatan }}</td><td class="num">{{ num(c.instansi) }}</td>
                    <td class="num">{{ num(c.sudah_rekom) }} <span class="text-muted small">({{ pct(c.sudah_rekom, c.instansi) }})</span></td>
                    <td class="num">{{ num(c.sudah_ditetapkan) }} <span class="text-muted small">({{ pct(c.sudah_ditetapkan, c.instansi) }})</span></td>
                    <td class="num">{{ num(c.belum_usul) }}</td></tr></tbody>
            </table></div>
        </div>

        <div class="row g-3">
            <!-- 2. sasaran -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Instansi sasaran penataan <span class="text-muted small">(JF kurang dari {{ w.ambang.ambang_sasaran }} pejabat)</span></span>
                        <a v-if="w.sasaran.total_daftar" :href="unduh('sasaran')" class="btn btn-sm btn-success"><i class="fa fa-file-excel-o me-1"></i> Unduh semua</a>
                    </div>
                    <div class="card-body py-2 small">
                        <span class="badge bg-danger me-1">{{ num(w.sasaran.ringkas.nol) }}</span> nol pejabat ·
                        <span class="badge bg-warning text-dark mx-1">{{ num(w.sasaran.ringkas.kurang) }}</span> kurang dari {{ w.ambang.ambang_sasaran }} ·
                        <span class="badge bg-secondary mx-1">{{ num(w.sasaran.ringkas.belum_ada_data) }}</span> belum ada data (belum usul, tidak dihitung)
                        <div class="text-muted">dari {{ num(w.sasaran.ringkas.total_instansi) }} instansi. "Nol" berarti bezetting tercatat nol pada instansi yang sudah direkom; verifikasi karena nol dapat berarti belum didata.</div>
                    </div>
                    <TabelDaftar :baris="w.sasaran.daftar" :total="w.sasaran.total_daftar" :batas="batasLayar"
                        :kolom="[['instansi', 'Instansi'], ['jenis', 'Jenis'], ['pokja', 'Pokja'], ['bezetting', 'Bezetting', true], ['rekom', 'Rekomendasi', true]]" />
                </div>
            </div>

            <!-- 3. tersumbat -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Jalur karier tersumbat <span class="text-muted small">(jenjang bawah ≥ {{ w.ambang.ambang_tersumbat }}, dua jenjang teratas nol)</span> <span class="badge bg-secondary ms-1">{{ num(w.tersumbat.jumlah) }}</span></span>
                        <a v-if="w.tersumbat.total_daftar" :href="unduh('tersumbat')" class="btn btn-sm btn-success"><i class="fa fa-file-excel-o me-1"></i> Unduh semua</a>
                    </div>
                    <TabelDaftar :baris="w.tersumbat.daftar" :total="w.tersumbat.total_daftar" :batas="batasLayar"
                        :kolom="[['instansi', 'Instansi'], ['jenis', 'Jenis'], ['jabatan', 'Jabatan'], ['bawah', 'Jenjang bawah', true], ['atas', 'Dua jenjang teratas', true]]" />
                </div>
            </div>

            <!-- 4. pendampingan -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Peluang pendampingan <span class="text-muted small">(sudah direkom {{ w.pendampingan.acuan }}, belum usul jabatan lain)</span></span>
                        <a v-if="w.pendampingan.total_daftar" :href="unduh('pendampingan')" class="btn btn-sm btn-success"><i class="fa fa-file-excel-o me-1"></i> Unduh semua</a>
                    </div>
                    <div class="table-responsive"><table class="table table-sm mb-0">
                        <thead><tr><th>Jenis instansi</th><th class="num">Sudah rekom {{ w.pendampingan.acuan }}</th><th v-for="j in w.pendampingan.jabatan_lain" :key="j" class="num">Belum usul {{ j }}</th></tr></thead>
                        <tbody><tr v-for="p in w.pendampingan.per_jenis" :key="p.jenis"><td>{{ p.jenis }}</td><td class="num">{{ num(p.instansi) }}</td>
                            <td v-for="j in w.pendampingan.jabatan_lain" :key="j" class="num">{{ num(p.belum?.[j] ?? 0) }} <span class="text-muted small">({{ pct(p.belum?.[j] ?? 0, p.instansi) }})</span></td></tr></tbody>
                    </table></div>
                    <TabelDaftar :baris="w.pendampingan.daftar" :total="w.pendampingan.total_daftar" :batas="batasLayar"
                        :kolom="[['instansi', 'Instansi'], ['jenis', 'Jenis'], ['pokja', 'Pokja'], ['belum_usul', 'Belum usul']]" />
                </div>
            </div>

            <!-- 5. antrean -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span>Antrean menunggu penetapan <span class="badge bg-secondary ms-1">{{ num(w.antrean.jumlah) }}</span></span>
                        <a v-if="w.antrean.total_daftar" :href="unduh('antrean')" class="btn btn-sm btn-success"><i class="fa fa-file-excel-o me-1"></i> Unduh semua</a>
                    </div>
                    <div class="card-body py-2 small">
                        Umur surat rekomendasi terakhir: <span class="badge bg-success mx-1">{{ w.antrean.kelompok.kurang_90 }}</span> &lt; 90 hari ·
                        <span class="badge bg-info text-dark mx-1">{{ w.antrean.kelompok.d90_180 }}</span> 90–180 ·
                        <span class="badge bg-warning text-dark mx-1">{{ w.antrean.kelompok.d181_365 }}</span> 181–365 ·
                        <span class="badge bg-danger mx-1">{{ w.antrean.kelompok.lebih_365 }}</span> &gt; 365 hari ·
                        <span class="badge bg-secondary mx-1">{{ w.antrean.kelompok.tanpa_tanggal }}</span> tanpa tanggal
                        <div class="text-muted">"Menunggu" = sudah ada rekomendasi tetapi belum ada angka atau surat penetapan; sebagian dapat berarti belum diinput.</div>
                    </div>
                    <TabelDaftar :baris="w.antrean.daftar" :total="w.antrean.total_daftar" :batas="batasLayar"
                        :kolom="[['instansi', 'Instansi'], ['jenis', 'Jenis'], ['jabatan', 'Jabatan'], ['nomor', 'Nomor surat'], ['tanggal', 'Tanggal'], ['umur_hari', 'Umur (hari)', true]]" />
                </div>
            </div>

            <!-- 6. pemotongan -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">Penetapan dibanding rekomendasi, per jenis instansi</div>
                    <div class="table-responsive"><table class="table table-sm mb-0">
                        <thead><tr><th>Jenis instansi</th><th class="num">Baris ditetapkan</th><th class="num">Rekomendasi</th><th class="num">Penetapan</th><th class="num">Penetapan / rekomendasi</th></tr></thead>
                        <tbody><tr v-for="p in w.pemotongan" :key="p.jenis"><td>{{ p.jenis }}</td><td class="num">{{ num(p.baris) }}</td><td class="num">{{ num(p.rekom) }}</td><td class="num">{{ num(p.penetapan) }}</td>
                            <td class="num" :class="p.periksa ? 'text-danger fw-semibold' : ''">{{ p.persen === null ? '-' : p.persen + '%' }} <span v-if="p.periksa" class="badge bg-danger ms-1" :title="`Di atas ${w.ambang.ambang_pemotongan}%: periksa data`">periksa</span></td></tr></tbody>
                    </table></div>
                </div>
            </div>
        </div>
    </template>
</template>

<script setup>
import { h } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { num } from '../../utils';

const props = defineProps({ w: Object, pembinaOptions: Array, pembinaId: Number, batasLayar: Number });
const pct = (a, b) => (b ? Math.round((a / b) * 100) + '%' : '-');
const unduh = (panel) => `/rekomendasi-jf/wawasan/unduh/${panel}?pembina_id=${props.pembinaId}`;

// tabel daftar sederhana dengan catatan bila dipotong
const TabelDaftar = {
    props: { baris: Array, kolom: Array, total: Number, batas: Number },
    setup(p) {
        return () => h('div', [
            h('div', { class: 'table-responsive' }, h('table', { class: 'table table-sm table-hover mb-0' }, [
                h('thead', h('tr', p.kolom.map(([, label, angka]) => h('th', { class: angka ? 'num' : '' }, label)))),
                h('tbody', [
                    ...p.baris.map((r) => h('tr', p.kolom.map(([k, , angka]) => h('td', { class: angka ? 'num' : '' }, angka ? num(r[k]) : (r[k] ?? '-'))))),
                    p.baris.length ? null : h('tr', h('td', { colspan: p.kolom.length, class: 'text-center text-muted py-3' }, 'Tidak ada.')),
                ]),
            ])),
            p.total > p.baris.length ? h('div', { class: 'card-body py-2 small text-muted' }, `Menampilkan ${p.baris.length} dari ${num(p.total)}. Unduh Excel untuk daftar lengkap.`) : null,
        ]);
    },
};
</script>
