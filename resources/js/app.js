import './bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'font-awesome/css/font-awesome.min.css';
import 'sweetalert2/dist/sweetalert2.min.css';
import 'bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import LayoutApp from './Layouts/App.vue';

createInertiaApp({
    title: (title) => (title ? `${title} - SIMONKEB` : 'SIMONKEB'),
    resolve: async (name) => {
        const page = await resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        // semua halaman memakai layout aplikasi kecuali yang menentukan layout sendiri
        page.default.layout = page.default.layout === undefined ? LayoutApp : page.default.layout;
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#0d6efd',
    },
});
