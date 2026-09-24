<template>
    <span class="kondisi" :class="'kondisi-' + status" :title="meta.title">
        <i class="fa" :class="meta.icon"></i> {{ meta.label }}
    </span>
</template>

<script>
export const KONDISI = {
    kurang: { label: 'Kurang', icon: 'fa-arrow-down', title: 'Existing di bawah kebutuhan' },
    kosong: { label: 'Kosong', icon: 'fa-circle-o', title: 'Dibutuhkan tetapi belum ada pegawai' },
    lebih: { label: 'Lebih', icon: 'fa-arrow-up', title: 'Existing melebihi kebutuhan' },
    sesuai: { label: 'Sesuai', icon: 'fa-check', title: 'Existing sama dengan kebutuhan' },
    ideal: { label: 'Ideal', icon: 'fa-check', title: 'Pemenuhan 90–110%' },
    tanpa_abk: { label: 'Belum ada ABK', icon: 'fa-question', title: 'Ada pegawai tetapi belum ada ABK final' },
    belum_ada_data: { label: 'Belum ada data', icon: 'fa-minus', title: 'Belum ada ABK maupun pegawai' },
};

export default {
    props: { status: String },
    computed: {
        meta() {
            return KONDISI[this.status] || { label: this.status, icon: 'fa-circle' };
        },
    },
};
</script>
