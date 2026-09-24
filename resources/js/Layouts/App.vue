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
                        <li><Link :href="route('logout')" method="post" as="button" class="dropdown-item"><i class="fa fa-sign-out me-1"></i> Keluar</Link></li>
                    </ul>
                </div>
            </nav>
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
    },
};
</script>
