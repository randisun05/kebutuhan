<template>
    <nav v-if="links && links.length > 3" class="mt-3">
        <ul class="pagination pagination-sm justify-content-end mb-0 flex-wrap">
            <li v-for="(link, i) in links" :key="i" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                <Link v-if="link.url" class="page-link" :href="link.url" preserve-scroll v-html="link.label" />
                <span v-else class="page-link" v-html="link.label"></span>
            </li>
        </ul>
    </nav>
</template>

<script>
import { Link } from '@inertiajs/vue3';

export default {
    components: { Link },
    props: { links: Array },
};
</script>
