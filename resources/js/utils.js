export const num = (v) => (v === null || v === undefined ? '-' : Number(v).toLocaleString('id-ID'));

export const pct = (v) => (v === null || v === undefined ? '-' : Number(v).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%');

export const tanggal = (v) => (v ? new Date(v).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-');

export const waktu = (v) => (v ? new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-');

// warna series: identitas tetap, tidak berubah ketika difilter
export const COLORS = {
    kebutuhan: '#2a78d6',
    existing: '#eb6834',
    kurang: '#e34948',
    lebih: '#2a78d6',
};

export const barClass = (persen) => {
    if (persen === null || persen === undefined) return 'bg-secondary';
    if (persen < 90) return 'bg-danger';
    if (persen > 110) return 'bg-warning';
    return 'bg-success';
};
