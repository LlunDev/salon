<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    salon: {
        type: Object,
        required: true,
    },
    items: {
        type: Array,
        required: true,
    },
    mobile: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div :class="mobile ? 'space-y-5' : 'flex h-full flex-col px-6 py-8'">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[1.4rem] bg-[linear-gradient(135deg,#67d4f7_0%,#8aa7ff_52%,#dba4ff_100%)] text-lg font-semibold text-slate-950 shadow-[0_20px_45px_rgba(138,167,255,0.24)]">
                {{ salon.name.charAt(0) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold text-white">{{ salon.name }}</p>
                <p class="truncate text-sm text-white/55">{{ salon.domain }}</p>
            </div>
        </div>

        <nav class="space-y-2" :class="mobile ? '' : 'mt-10'">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="group flex items-center justify-between rounded-[1.3rem] px-4 py-3.5 text-sm font-medium transition-all duration-200"
                :class="item.active
                    ? 'bg-[linear-gradient(90deg,#67d4f7_0%,#8aa7ff_45%,#b78dff_100%)] text-slate-950 shadow-[0_18px_35px_rgba(103,212,247,0.18)]'
                    : 'text-white/74 hover:bg-white/6 hover:text-white'"
            >
                <span>{{ item.label }}</span>
                <span
                    class="h-2 w-2 rounded-full transition-opacity duration-200"
                    :class="item.active ? 'bg-slate-950/80 opacity-100' : 'bg-white/30 opacity-0 group-hover:opacity-100'"
                />
            </Link>
        </nav>
    </div>
</template>
