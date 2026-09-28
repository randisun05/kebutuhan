<template>
    <div class="app-wrapper">
        <FlashMessages />
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />
        <main class="app-main">
            <nav class="app-navbar d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-light d-lg-none" @click="sidebarOpen = !sidebarOpen"><i class="fa fa-bars"></i></button>
                    <span class="text-muted small d-none d-md-inline">
                        Sistem Informasi Monitoring Penyusunan Kebutuhan ASN
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                <Link :href="bantuanUrl" class="btn btn-sm btn-light" title="Panduan untuk halaman ini">
                    <i class="fa fa-question-circle"></i> <span class="d-none d-md-inline">Bantuan</span>
                </Link>
                <div class="dropdown" v-if="$page.props.notifikasi">
                    <button class="btn btn-sm btn-light position-relative" data-bs-toggle="dropdown" title="Notifikasi">
                        <i class="fa fa-bell"></i>
                        <span v-if="$page.props.notifikasi.unread" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $page.props.notifikasi.unread }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px">
                        <div class="px-3 py-2 border-bottom small fw-semibold">Notifikasi</div>
                        <a v-for="n in $page.props.notifikasi.items" :key="n.id" :href="`/notifikasi/${n.id}`" class="dropdown-item small py-2 text-wrap" :class="{ 'bg-light': !n.read }">
                            <div class="fw-semibold">{{ n.data.judul }}</div>
                            <div class="text-muted">{{ n.data.pesan }}</div>
                        </a>
                        <div v-if="!$page.props.notifikasi.items.length" class="px-3 py-2 small text-muted">Belum ada notifikasi.</div>
                        <Link href="/notifikasi" class="dropdown-item small text-center border-top">Lihat semua</Link>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fa fa-user-circle me-1"></i> {{ user.name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-item-text small">
                            <div class="fw-semibold">{{ user.role_label }}</div>
                            <div class="text-muted" v-if="user.instansi">{{ user.instansi.nama }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><Link href="/akun" class="dropdown-item"><i class="fa fa-shield me-1"></i> Keamanan akun</Link></li>
                        <li><Link href="/logout" method="post" as="button" class="dropdown-item"><i class="fa fa-sign-out me-1"></i> Keluar</Link></li>
                    </ul>
                </div>
                </div>
            </nav>
            <div v-if="$page.props.panduan?.baru && !$page.url.startsWith('/panduan')" class="alert alert-info rounded-0 mb-0 py-2 small d-flex justify-content-between align-items-center">
                <span><i class="fa fa-bullhorn me-1"></i> Ada pembaruan aplikasi (versi {{ $page.props.panduan.versi }}). Lihat apa yang baru di panduan.</span>
                <Link href="/panduan/perubahan" class="btn btn-sm btn-info">Lihat perubahan</Link>
            </div>
            <div class="app-content" :key="$page.url">
                <slot />
            </div>
        </main>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3';
import Sidebar from '../Components/Sidebar.vue';
import FlashMessages from '../Components/FlashMessages.vue';

export default {
    components: { Link, Sidebar, FlashMessages },
    data() {
        return { sidebarOpen: false };
    },
    computed: {
        user() {
            return this.$page.props.auth.user;
        },
        // halaman panduan yang paling cocok dengan URL saat ini (prefix terpanjang)
        bantuanUrl() {
            const peta = this.$page.props.panduan?.peta || {};
            const path = this.$page.url.split('?')[0];
            const cocok = Object.keys(peta)
                .filter((m) => path === m || path.startsWith(m + '/'))
                .sort((a, b) => b.length - a.length)[0];
            return cocok ? `/panduan/${peta[cocok]}` : '/panduan';
        },
    },
};
</script>
