<script setup>
import { useBookingSelection } from '@/Composables/useBookingSelection';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    salon: { type: Object, required: true },
});

const services = ref([]);
const loading = ref(true);
const loadError = ref('');
const { selectedIds, selectedCount, toggle, reconcile } = useBookingSelection(props.salon.id);

const selectedServices = computed(() => services.value.filter((service) => selectedIds.value.includes(service.id)));
const totalDuration = computed(() => selectedServices.value.reduce((total, service) => total + service.duration, 0));
const totalPrice = computed(() => selectedServices.value.reduce((total, service) => total + Number(service.price), 0));

async function loadServices() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await fetch('/api/public/services', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();

        if (!response.ok) throw new Error(payload.message);

        services.value = payload.data;
        reconcile(services.value);
    } catch {
        loadError.value = 'No pudimos cargar los servicios. Inténtalo nuevamente.';
    } finally {
        loading.value = false;
    }
}

function formatPrice(price) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', maximumFractionDigits: 0,
    }).format(Number(price));
}

function formatDuration(minutes) {
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return remaining ? `${hours} h ${remaining} min` : `${hours} h`;
}

onMounted(loadServices);
</script>

<template>
    <Head :title="`Reserva en ${salon.name}`" />

    <div class="min-h-screen bg-[#faf8fc] pb-40 text-slate-950 sm:pb-36">
        <header class="public-hero overflow-hidden text-white">
            <div class="mx-auto max-w-6xl px-5 py-6 sm:px-8">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/10 text-xl ring-1 ring-white/15">✦</div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-fuchsia-200">Reserva online</p>
                        <p class="mt-1 font-semibold">{{ salon.name }}</p>
                    </div>
                </div>
                <div class="max-w-2xl py-12 sm:py-16">
                    <p class="text-sm font-semibold text-fuchsia-300">Paso 1 de 4</p>
                    <h1 class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">Elige tu momento de cuidado</h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-violet-100">Selecciona uno o varios servicios. Calcularemos el tiempo necesario para encontrar una cita que encaje contigo.</p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12">
            <div class="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#a21caf]">Nuestros servicios</p>
                    <h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">¿Qué quieres reservar?</h2>
                </div>
                <p v-if="!loading && services.length" class="text-sm font-medium text-slate-500">{{ services.length }} disponibles</p>
            </div>

            <div v-if="loading" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-label="Cargando servicios">
                <div v-for="index in 6" :key="index" class="h-[24rem] animate-pulse overflow-hidden rounded-[1.75rem] border border-violet-100 bg-white"><div class="h-44 bg-violet-100"/><div class="space-y-4 p-5"><div class="h-5 w-2/3 rounded bg-slate-200"/><div class="h-4 rounded bg-slate-100"/><div class="h-4 w-1/2 rounded bg-slate-100"/></div></div>
            </div>

            <div v-else-if="loadError" class="rounded-[1.75rem] border border-rose-200 bg-white p-8 text-center shadow-sm">
                <p class="text-sm font-medium text-rose-700">{{ loadError }}</p>
                <button type="button" class="mt-5 rounded-xl bg-rose-100 px-5 py-3 text-sm font-semibold text-rose-700 hover:bg-rose-200" @click="loadServices">Reintentar</button>
            </div>

            <div v-else-if="services.length === 0" class="rounded-[2rem] border border-dashed border-violet-200 bg-white px-6 py-16 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 text-3xl text-violet-600">✦</div>
                <h2 class="mt-5 text-xl font-semibold">Aún no hay servicios disponibles</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">El salón está preparando su catálogo. Vuelve a visitarnos pronto.</p>
            </div>

            <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="service in services"
                    :key="service.id"
                    type="button"
                    class="group overflow-hidden rounded-[1.75rem] border bg-white text-left shadow-[0_16px_40px_rgba(44,24,65,0.07)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_50px_rgba(76,29,149,0.13)] focus:outline-none focus:ring-4 focus:ring-violet-200"
                    :class="selectedIds.includes(service.id) ? 'border-violet-500 ring-2 ring-violet-500' : 'border-violet-100'"
                    :aria-pressed="selectedIds.includes(service.id)"
                    @click="toggle(service.id)"
                >
                    <div class="relative h-48 overflow-hidden bg-[linear-gradient(135deg,#ede9fe,#fce7f3)]">
                        <img v-if="service.imageUrl" :src="service.imageUrl" :alt="service.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        <div v-else class="flex h-full items-center justify-center"><div class="flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-white/70 bg-white/60 text-4xl text-violet-600 shadow-lg backdrop-blur">✦</div></div>
                        <span class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full border-2 shadow-lg transition" :class="selectedIds.includes(service.id) ? 'border-violet-600 bg-violet-600 text-white' : 'border-white bg-white/90 text-transparent'" aria-hidden="true">✓</span>
                    </div>
                    <div class="flex min-h-[13rem] flex-col p-5">
                        <h3 class="text-xl font-semibold tracking-tight">{{ service.name }}</h3>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">{{ service.description || 'Una experiencia preparada especialmente para ti.' }}</p>
                        <div class="mt-auto flex items-end justify-between gap-4 border-t border-slate-100 pt-5">
                            <span class="text-sm font-medium text-slate-500">{{ formatDuration(service.duration) }}</span>
                            <span class="text-xl font-semibold tracking-tight">{{ formatPrice(service.price) }}</span>
                        </div>
                    </div>
                </button>
            </div>
        </main>

        <div v-if="selectedCount" class="fixed inset-x-0 bottom-0 z-30 border-t border-violet-100 bg-white/95 shadow-[0_-16px_45px_rgba(44,24,65,0.12)] backdrop-blur-xl">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div class="flex items-center justify-between gap-6 sm:justify-start">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Tu selección</p><p class="mt-1 text-sm font-semibold">{{ selectedCount }} {{ selectedCount === 1 ? 'servicio' : 'servicios' }} · {{ formatDuration(totalDuration) }}</p></div>
                    <p class="text-xl font-semibold text-violet-700">{{ formatPrice(totalPrice) }}</p>
                </div>
                <Link href="/reservar/fecha" class="continue-button inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl px-6 py-3.5 text-sm font-semibold text-white shadow-[0_12px_28px_rgba(124,58,237,0.3)] transition">Continuar al calendario <span aria-hidden="true">→</span></Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.public-hero {
    background: #241238;
}

.continue-button {
    background: #7c3aed;
}

.continue-button:hover {
    background: #6d28d9;
}
</style>
