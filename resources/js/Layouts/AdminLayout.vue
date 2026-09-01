<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    salon: {
        type: Object,
        required: true,
    },
});

const navigationItems = [
    { label: 'Agenda', href: '/admin/dashboard', active: true },
    { label: 'Servicios', href: '#' },
    { label: 'Recordatorios', href: '#' },
    { label: 'Usuarios', href: '#' },
    { label: 'Clientes', href: '#' },
    { label: 'Configuracion', href: '#' },
];

const flashSuccess = computed(() => usePage().props.flash?.success ?? null);
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-[#f7f4fb] text-slate-900">
        <div class="mx-auto flex min-h-screen w-full max-w-[1600px] flex-col lg:flex-row">
            <aside class="w-full border-b border-[#eadff5] bg-[#170f20] px-5 py-6 text-white lg:min-h-screen lg:w-[290px] lg:border-b-0 lg:border-r lg:px-6 lg:py-8">
                <div class="rounded-[2rem] border border-white/10 bg-white/5 p-5 shadow-[0_24px_80px_rgba(0,0,0,0.24)] backdrop-blur">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-[#6ee7f9] via-[#9f8cff] to-[#f9a8d4] text-lg font-semibold text-slate-950">
                            {{ salon.name.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-base font-semibold text-white">{{ salon.name }}</p>
                            <p class="truncate text-sm text-white/60">{{ salon.domain }}</p>
                        </div>
                    </div>

                    <nav class="mt-8 space-y-2">
                        <Link
                            v-for="item in navigationItems"
                            :key="item.label"
                            :href="item.href"
                            class="flex items-center rounded-2xl px-4 py-3 text-sm font-medium transition"
                            :class="item.active
                                ? 'bg-gradient-to-r from-[#67d4f7] via-[#8aa7ff] to-[#b78dff] text-slate-950 shadow-[0_18px_35px_rgba(103,212,247,0.22)]'
                                : 'text-white/72 hover:bg-white/8 hover:text-white'"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
            </aside>

            <main class="flex-1 px-4 py-5 sm:px-6 lg:px-10 lg:py-8">
                <div
                    v-if="flashSuccess"
                    class="mb-6 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 shadow-sm"
                >
                    {{ flashSuccess }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
