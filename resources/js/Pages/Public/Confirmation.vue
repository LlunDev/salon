<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    salon: { type: Object, required: true },
    appointment: { type: Object, required: true },
});

function formatDateTime(value) {
    return new Intl.DateTimeFormat('es-CO', {
        dateStyle: 'full', timeStyle: 'short', timeZone: props.salon.timezone,
    }).format(new Date(value));
}

function formatPrice(value) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', maximumFractionDigits: 0,
    }).format(Number(value));
}
</script>

<template>
    <Head :title="`Cita confirmada - ${salon.name}`" />
    <div class="min-h-screen bg-[#faf8fc] text-slate-950">
        <header class="confirmation-header text-white">
            <div class="mx-auto max-w-3xl px-5 py-12 text-center sm:px-8 sm:py-16">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-400 text-3xl text-emerald-950">✓</div>
                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.22em] text-fuchsia-300">Paso 4 de 4</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Tu cita está confirmada</h1>
                <p class="mt-3 text-sm text-violet-100">Te esperamos en {{ salon.name }}.</p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-5 py-8 sm:px-8 sm:py-10">
            <section class="rounded-[2rem] border border-violet-100 bg-white p-6 shadow-[0_18px_45px_rgba(44,24,65,0.07)] sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-fuchsia-700">Fecha y hora</p>
                <p class="mt-3 text-xl font-semibold capitalize">{{ formatDateTime(appointment.startsAt) }}</p>

                <div class="mt-7 border-t border-slate-100 pt-6">
                    <div v-for="service in appointment.services" :key="`${service.name}-${service.price}`" class="flex justify-between gap-4 py-2 text-sm">
                        <span>{{ service.name }} · {{ service.duration }} min</span>
                        <span class="font-semibold">{{ formatPrice(service.price) }}</span>
                    </div>
                    <div class="mt-4 flex justify-between border-t border-slate-100 pt-5 text-lg font-semibold">
                        <span>Total · {{ appointment.duration }} min</span>
                        <span class="text-violet-700">{{ formatPrice(appointment.total) }}</span>
                    </div>
                </div>
            </section>

            <Link href="/reservar" class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-2xl bg-violet-700 px-6 py-3.5 text-sm font-semibold text-white hover:bg-violet-800">Hacer otra reserva</Link>
        </main>
    </div>
</template>

<style scoped>
.confirmation-header { background: #241238; }
</style>
