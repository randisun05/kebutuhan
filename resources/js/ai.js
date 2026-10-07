import { waktu } from './utils';

// keterangan asal hasil AI (disimpan di riwayat, tidak di-generate ulang kecuali diminta)
export const infoRiwayat = (r) => {
    if (!r) return '';
    const asal = r.dari_riwayat ? 'Diambil dari riwayat' : 'Dibuat';
    return `${asal} ${waktu(r.dibuat)}${r.oleh ? ' oleh ' + r.oleh : ''}.` + (r.usang ? ' Data sudah berubah sejak hasil ini dibuat; klik "Buat ulang" untuk memperbarui.' : '');
};

export const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
