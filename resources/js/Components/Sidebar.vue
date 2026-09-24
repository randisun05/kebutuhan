<template>
    <aside class="app-sidebar" :class="{ open }">
        <div class="brand d-flex justify-content-between align-items-start">
            <div>
                <div class="title"><i class="fa fa-sitemap me-2"></i>SIMONKEB</div>
                <small>Monitoring Kebutuhan ASN</small>
            </div>
            <button class="btn btn-sm text-white d-lg-none" @click="$emit('close')"><i class="fa fa-times"></i></button>
        </div>

        <nav class="nav flex-column pb-4">
            <template v-for="group in menu" :key="group.label">
                <div class="menu-label" v-if="group.items.some(visible)">{{ group.label }}</div>
                <template v-for="item in group.items" :key="item.href">
                    <Link v-if="visible(item)" :href="item.href" class="nav-link" :class="{ active: isActive(item) }">
                        <i class="fa" :class="item.icon"></i>
                        <span class="flex-grow-1">{{ item.label }}</span>
                        <span v-if="item.badge && $page.props.inbox" class="badge rounded-pill bg-warning text-dark">{{ $page.props.inbox }}</span>
                    </Link>
                </template>
            </template>
        </nav>
    </aside>
</template>

<script>
import { Link } from '@inertiajs/vue3';

const ALL = ['admin', 'verifikator_bkn', 'validator_kemenpan', 'operator_instansi', 'pimpinan'];

export default {
    components: { Link },
    props: { open: Boolean },
    emits: ['close'],
    data() {
        return {
            menu: [
                { label: 'Monitoring', items: [
                    { label: 'Dashboard', href: '/dashboard', icon: 'fa-tachometer', roles: ALL },
                    { label: 'Monitoring Kebutuhan', href: '/monitoring', icon: 'fa-bar-chart', roles: ALL },
                ] },
                { label: 'Hulu: Perencanaan', items: [
                    { label: 'Struktur Unit Kerja', href: '/unit-kerja', icon: 'fa-sitemap', roles: ['admin', 'operator_instansi'] },
                    { label: 'Data Pegawai (Existing)', href: '/pegawai', icon: 'fa-users', roles: ['admin', 'operator_instansi'] },
                    { label: 'Anjab & ABK', href: '/anjab', icon: 'fa-calculator', roles: ALL },
                ] },
                { label: 'Hilir: Usulan s.d. Penetapan', items: [
                    { label: 'Usulan Kebutuhan', href: '/usulan', icon: 'fa-inbox', roles: ALL, badge: true },
                    { label: 'Penetapan Kebutuhan', href: '/penetapan', icon: 'fa-gavel', roles: ALL },
                ] },
                { label: 'Referensi', items: [
                    { label: 'Instansi', href: '/instansi', icon: 'fa-building', roles: ['admin'] },
                    { label: 'Jabatan', href: '/jabatan', icon: 'fa-briefcase', roles: ['admin'] },
                    { label: 'Pengguna', href: '/users', icon: 'fa-user-secret', roles: ['admin'] },
                    { label: 'Pedoman & Regulasi', href: '/pedoman', icon: 'fa-book', roles: ALL },
                ] },
            ],
        };
    },
    methods: {
        visible(item) {
            return item.roles.includes(this.$page.props.auth.user?.role);
        },
        isActive(item) {
            return this.$page.url === item.href || this.$page.url.startsWith(item.href + '/') || this.$page.url.startsWith(item.href + '?');
        },
    },
};
</script>
