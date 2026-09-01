<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    salon: {
        type: Object,
        required: true,
    },
    stats: {
        type: Array,
        required: true,
    },
    agenda: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AdminLayout title="Panel del salon" :salon="salon">
        <section class="space-y-6">
            <div class="rounded-[2rem] bg-gradient-to-r from-[#0f172a] via-[#1f1630] to-[#312e81] p-6 text-white shadow-[0_28px_80px_rgba(49,46,129,0.28)] sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/60">Panel de administracion</p>
                <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ salon.name }}</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
                            Vista general de tu salon. Por ahora mostramos informacion mock para avanzar con el frontend del panel.
                        </p>
                    </div>

                    <div class="rounded-[1.5rem] border border-white/10 bg-white/10 px-5 py-4 backdrop-blur">
                        <p class="text-xs uppercase tracking-[0.3em] text-white/50">Dominio activo</p>
                        <p class="mt-2 text-lg font-semibold text-white">{{ salon.domain }}</p>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="item in stats"
                    :key="item.label"
                    class="rounded-[1.75rem] border border-[#e8def4] bg-white p-5 shadow-[0_18px_45px_rgba(15,23,42,0.06)]"
                >
                    <p class="text-sm font-medium text-slate-500">{{ item.label }}</p>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950">{{ item.value }}</p>
                    <p class="mt-3 text-sm text-slate-500">{{ item.detail }}</p>
                </article>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <section class="rounded-[1.75rem] border border-[#e8def4] bg-white p-5 shadow-[0_18px_45px_rgba(15,23,42,0.06)] sm:p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#7c3aed]">Agenda</p>
                            <h2 class="mt-2 text-2xl font-semibold text-slate-950">Citas del dia</h2>
                        </div>
                        <span class="rounded-full bg-[#f3ecff] px-4 py-2 text-sm font-medium text-[#6d28d9]">Mock data</span>
                    </div>

                    <div class="mt-6 space-y-4">
                        <article
                            v-for="appointment in agenda"
                            :key="`${appointment.time}-${appointment.client}`"
                            class="rounded-[1.5rem] border border-slate-100 bg-slate-50 px-4 py-4"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-lg font-semibold text-slate-950">{{ appointment.client }}</p>
                                    <p class="mt-1 text-sm text-slate-500">{{ appointment.service }}</p>
                                </div>
                                <div class="sm:text-right">
                                    <p class="text-base font-semibold text-[#7c3aed]">{{ appointment.time }}</p>
                                    <p class="mt-1 text-sm text-slate-500">Profesional: {{ appointment.stylist }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-[#e8def4] bg-white p-5 shadow-[0_18px_45px_rgba(15,23,42,0.06)] sm:p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-[#db2777]">Resumen</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-950">Estado general</h2>

                    <div class="mt-6 space-y-4">
                        <div class="rounded-[1.5rem] bg-[#fff6fb] p-4">
                            <p class="text-sm text-slate-500">Estado del salon</p>
                            <p class="mt-2 text-lg font-semibold capitalize text-slate-950">{{ salon.status }}</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-[#f5f7ff] p-4">
                            <p class="text-sm text-slate-500">Recordatorios activos</p>
                            <p class="mt-2 text-lg font-semibold text-slate-950">12 enviados hoy</p>
                        </div>
                        <div class="rounded-[1.5rem] bg-[#f7fff7] p-4">
                            <p class="text-sm text-slate-500">Equipo conectado</p>
                            <p class="mt-2 text-lg font-semibold text-slate-950">4 usuarios en linea</p>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </AdminLayout>
</template>
