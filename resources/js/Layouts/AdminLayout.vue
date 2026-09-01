<script setup>
import AdminSidebar from '@/Components/AdminSidebar.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

const mobileMenuOpen = ref(false);

const navigationItems = computed(() => [
    { label: 'Agenda', href: route('admin.dashboard'), active: route().current('admin.dashboard') },
    { label: 'Servicios', href: route('admin.services'), active: route().current('admin.services') },
    { label: 'Recordatorios', href: route('admin.reminders'), active: route().current('admin.reminders') },
    { label: 'Usuarios', href: route('admin.users'), active: route().current('admin.users') },
    { label: 'Clientes', href: route('admin.clients'), active: route().current('admin.clients') },
    { label: 'Configuracion', href: route('admin.settings'), active: route().current('admin.settings') },
]);

const flashSuccess = computed(() => usePage().props.flash?.success ?? null);
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-[#f7f4fb] text-slate-900">
        <div class="flex min-h-screen w-full flex-col lg:flex-row">
            <aside class="hidden w-[296px] shrink-0 border-r border-white/6 bg-[linear-gradient(180deg,#17101f_0%,#1f182a_100%)] text-white lg:block">
                <AdminSidebar :salon="salon" :items="navigationItems" />
            </aside>

            <main class="flex-1">
                <header class="border-b border-[#eadff5] bg-white/88 px-4 py-4 backdrop-blur sm:px-6 lg:hidden">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#db2777]">Salon de Belleza</p>
                            <p class="mt-1 text-lg font-semibold text-slate-950">{{ salon.name }}</p>
                        </div>

                        <button
                            type="button"
                            class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-950 shadow-[0_10px_30px_rgba(15,23,42,0.08)] transition hover:border-slate-300"
                            @click="mobileMenuOpen = !mobileMenuOpen"
                        >
                            <span class="relative block h-4 w-5">
                                <span
                                    class="absolute left-0 top-0 h-0.5 w-5 rounded-full bg-current transition-transform duration-300"
                                    :class="mobileMenuOpen ? 'translate-y-[7px] rotate-45' : ''"
                                />
                                <span
                                    class="absolute left-0 top-[7px] h-0.5 w-5 rounded-full bg-current transition-opacity duration-200"
                                    :class="mobileMenuOpen ? 'opacity-0' : 'opacity-100'"
                                />
                                <span
                                    class="absolute left-0 top-[14px] h-0.5 w-5 rounded-full bg-current transition-transform duration-300"
                                    :class="mobileMenuOpen ? '-translate-y-[7px] -rotate-45' : ''"
                                />
                            </span>
                        </button>
                    </div>

                    <transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-[-10px] opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-[-8px] opacity-0"
                    >
                        <div
                            v-if="mobileMenuOpen"
                            class="mt-4 rounded-[2rem] bg-[linear-gradient(180deg,#17101f_0%,#231a2d_100%)] p-5 text-white shadow-[0_30px_80px_rgba(18,15,25,0.35)]"
                        >
                            <AdminSidebar :salon="salon" :items="navigationItems" mobile />
                        </div>
                    </transition>
                </header>

                <div class="px-4 py-5 sm:px-6 lg:px-10 lg:py-8">
                    <div class="mx-auto w-full max-w-7xl">
                        <div
                            v-if="flashSuccess"
                            class="mb-6 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 shadow-sm"
                        >
                            {{ flashSuccess }}
                        </div>

                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
