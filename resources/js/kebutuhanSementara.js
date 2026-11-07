import axios from 'axios';
import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';
import { escapeHtml } from './ai';

/**
 * Alur isi massal kebutuhan sementara: pilih sumber -> pratinjau (tanpa menulis) -> konfirmasi -> simpan.
 * @param {{instansiId: number, unitId?: number, sub?: boolean, target: string}} opsi
 */
export async function isiMassal({ instansiId, unitId = null, sub = false, target }) {
    const pilih = await Swal.fire({
        title: 'Isi kebutuhan sementara',
        html: `<div class="small text-start">Pilih sumber angka untuk <b>${escapeHtml(target)}</b>. Posisi yang sudah punya ABK final atau kebutuhan sementara <b>tidak diubah</b>.</div>`,
        input: 'radio', inputValue: 'existing',
        inputOptions: { existing: 'Jumlah pegawai existing (pegawai aktif)', formasi: 'Formasi yang sudah ditetapkan (jumlah ditetapkan pada SK)' },
        showCancelButton: true, confirmButtonText: 'Lihat pratinjau', cancelButtonText: 'Batal',
    });
    if (!pilih.isConfirmed) return;

    const body = { instansi_id: instansiId, sumber: pilih.value, ...(unitId ? { unit_kerja_id: unitId, sub: sub ? 1 : 0 } : {}) };
    let p;
    try {
        p = (await axios.post('/kebutuhan-sementara/pratinjau', body)).data;
    } catch (e) {
        return Swal.fire({ icon: 'error', title: 'Pratinjau gagal', text: e.response?.data?.message || 'Coba lagi.' });
    }

    if (!p.dibuat) {
        return Swal.fire({ icon: 'info', title: 'Tidak ada yang perlu diisi', text: `${p.dilewati_abk} posisi sudah ber-ABK final dan ${p.dilewati_ada} posisi sudah terisi.` });
    }
    const baris = p.contoh.map((c) => `<tr><td>${escapeHtml(c.unit)}</td><td>${escapeHtml(c.jabatan)}</td><td class="text-end">${c.jumlah}</td></tr>`).join('');
    const ok = await Swal.fire({
        title: `${p.dibuat} posisi akan diisi`,
        html: `<div class="small text-start">
            <p class="mb-2">Total kebutuhan <b>${p.total_kebutuhan}</b>. Dilewati: ${p.dilewati_abk} ber-ABK final, ${p.dilewati_ada} sudah terisi.</p>
            <table class="table table-sm"><thead><tr><th>Unit</th><th>Jabatan</th><th class="text-end">Jumlah</th></tr></thead><tbody>${baris}</tbody></table>
            ${p.dibuat > p.contoh.length ? `<div class="text-muted">…dan ${p.dibuat - p.contoh.length} posisi lain.</div>` : ''}
            <div class="text-muted mt-2">Pengisian ini dapat dibatalkan setelahnya.</div></div>`,
        showCancelButton: true, confirmButtonText: 'Isi sekarang', cancelButtonText: 'Batal',
    });
    if (ok.isConfirmed) router.post('/kebutuhan-sementara/isi-existing', body, { preserveScroll: true });
}

export async function batalkanIsi(batch) {
    const res = await Swal.fire({
        title: 'Batalkan pengisian terakhir?',
        text: `${batch.jumlah_dibuat} posisi yang diisi massal akan dihapus (yang sudah Anda ubah dipertahankan).`,
        icon: 'warning', showCancelButton: true, confirmButtonText: 'Batalkan pengisian', cancelButtonText: 'Tidak',
    });
    if (res.isConfirmed) router.post(`/kebutuhan-sementara/batal/${batch.uuid}`, {}, { preserveScroll: true });
}
