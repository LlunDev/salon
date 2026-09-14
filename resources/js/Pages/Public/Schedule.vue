<script setup>
import { useBookingSelection } from '@/Composables/useBookingSelection';
import { useBookingSlotSelection } from '@/Composables/useBookingSlotSelection';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    salon: { type: Object, required: true },
});

const { selectedIds, selectedCount } = useBookingSelection(props.salon.id);
const today = salonToday(props.salon.timezone);
const dates = buildDateRange(today, 14);
const { selectedDate, selectedStartsAt, selectDate, reconcile } = useBookingSlotSelection(props.salon.id, today);
if (!dates.some((date) => date.value === selectedDate.value)) selectDate(today);
const slots = ref([]);
const duration = ref(0);
const loading = ref(false);
const loadError = ref('');
const staleSelection = ref(false);
let requestController;

const selectedSlot = computed(() => slots.value.find((slot) => slot.startsAt === selectedStartsAt.value));
const morningSlots = computed(() => slots.value.filter((slot) => Number(slot.startsAtLocal.slice(11, 13)) < 12));
const afternoonSlots = computed(() => slots.value.filter((slot) => {
    const hour = Number(slot.startsAtLocal.slice(11, 13));
    return hour >= 12 && hour < 18;
}));
const eveningSlots = computed(() => slots.value.filter((slot) => Number(slot.startsAtLocal.slice(11, 13)) >= 18));

async function loadAvailability() {
    if (!selectedCount.value) return;

    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    loading.value = true;
    loadError.value = '';
    staleSelection.value = false;
    const params = new URLSearchParams({ date: selectedDate.value });
    selectedIds.value.forEach((id) => params.append('serviceIds[]', id));

    try {
        const response = await fetch(`/api/public/availability?${params}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: controller.signal,
        });
        const payload = await response.json();

        if (response.status === 422 && payload.errors?.serviceIds) {
            staleSelection.value = true;
            slots.value = [];
            return;
        }
        if (!response.ok) throw new Error(payload.message);

        slots.value = payload.data.slots;
        duration.value = payload.data.duration;
        reconcile(slots.value);
    } catch (error) {
        if (error.name !== 'AbortError') loadError.value = 'No pudimos consultar los horarios. Inténtalo nuevamente.';
    } finally {
        if (!controller.signal.aborted) loading.value = false;
    }
}

function salonToday(timezone) {
    const parts = new Intl.DateTimeFormat('en', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(new Date()).reduce((values, part) => ({ ...values, [part.type]: part.value }), {});

    return `${parts.year}-${parts.month}-${parts.day}`;
}

function buildDateRange(start, amount) {
    const first = new Date(`${start}T12:00:00Z`);

    return Array.from({ length: amount }, (_, index) => {
        const date = new Date(first);
        date.setUTCDate(first.getUTCDate() + index);
        const value = date.toISOString().slice(0, 10);
        return {
            value,
            weekday: new Intl.DateTimeFormat('es-CO', { weekday: 'short', timeZone: 'UTC' }).format(date).replace('.', ''),
            day: date.getUTCDate(),
            month: new Intl.DateTimeFormat('es-CO', { month: 'short', timeZone: 'UTC' }).format(date).replace('.', ''),
        };
    });
}

function formatTime(slot) {
    const [hour, minute] = slot.startsAtLocal.slice(11).split(':').map(Number);
    const suffix = hour < 12 ? 'a. m.' : 'p. m.';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${String(minute).padStart(2, '0')} ${suffix}`;
}

function formatDuration(minutes) {
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const remaining = minutes % 60;
    return remaining ? `${hours} h ${remaining} min` : `${hours} h`;
}

watch(selectedDate, loadAvailability);
onMounted(loadAvailability);
onBeforeUnmount(() => requestController?.abort());
</script>

<template>
    <Head :title="`Elige fecha - ${salon.name}`" />
    <div class="min-h-screen bg-[#faf8fc] pb-36 text-slate-950">
        <header class="schedule-hero text-white">
            <div class="mx-auto max-w-5xl px-5 py-6 sm:px-8">
                <Link href="/reservar" class="inline-flex items-center gap-2 text-sm font-semibold text-violet-100 hover:text-white">← Cambiar servicios</Link>
                <div class="py-9 sm:py-12">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-fuchsia-300">Paso 2 de 4</p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Elige fecha y hora</h1>
                    <p class="mt-3 text-sm text-violet-100">{{ salon.name }} · {{ selectedCount }} {{ selectedCount === 1 ? 'servicio' : 'servicios' }}<span v-if="duration"> · {{ formatDuration(duration) }}</span></p>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-10">
            <section v-if="!selectedCount" class="rounded-[2rem] border border-violet-100 bg-white px-6 py-14 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-violet-50 text-2xl text-violet-600">✦</div>
                <h2 class="mt-5 text-xl font-semibold">Primero elige tus servicios</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Necesitamos conocer la duración total para mostrarte horarios disponibles.</p>
                <Link href="/reservar" class="continue-button mt-6 inline-flex rounded-2xl px-6 py-3 text-sm font-semibold text-white">Elegir servicios</Link>
            </section>

            <template v-else>
                <section>
                    <div class="flex items-end justify-between gap-4">
                        <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-fuchsia-700">Próximos 14 días</p><h2 class="mt-2 text-2xl font-semibold tracking-tight">Selecciona una fecha</h2></div>
                        <p class="hidden text-sm text-slate-500 sm:block">Zona horaria: {{ salon.timezone }}</p>
                    </div>
                    <div class="mt-5 flex gap-3 overflow-x-auto pb-3" aria-label="Fechas disponibles">
                        <button v-for="date in dates" :key="date.value" type="button" class="min-w-[5rem] rounded-2xl border px-3 py-3 text-center transition" :class="selectedDate === date.value ? 'border-violet-600 bg-violet-600 text-white shadow-lg' : 'border-violet-100 bg-white text-slate-700 hover:border-violet-300'" @click="selectDate(date.value)">
                            <span class="block text-xs font-semibold uppercase">{{ date.weekday }}</span><span class="mt-1 block text-2xl font-semibold">{{ date.day }}</span><span class="block text-xs capitalize opacity-75">{{ date.month }}</span>
                        </button>
                    </div>
                </section>

                <section class="mt-8 rounded-[2rem] border border-violet-100 bg-white p-5 shadow-[0_18px_45px_rgba(44,24,65,0.07)] sm:p-7">
                    <div class="flex items-center justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Horarios del salón</p><h2 class="mt-1 text-xl font-semibold">Horas disponibles</h2></div><span v-if="!loading && slots.length" class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">{{ slots.length }} opciones</span></div>

                    <div v-if="loading" class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Cargando horarios"><div v-for="index in 8" :key="index" class="h-12 animate-pulse rounded-xl bg-violet-50"/></div>
                    <div v-else-if="loadError" class="mt-6 rounded-2xl bg-rose-50 p-6 text-center"><p class="text-sm font-medium text-rose-700">{{ loadError }}</p><button type="button" class="mt-4 rounded-xl bg-rose-100 px-4 py-2 text-sm font-semibold text-rose-700" @click="loadAvailability">Reintentar</button></div>
                    <div v-else-if="staleSelection" class="mt-6 rounded-2xl bg-amber-50 p-6 text-center"><p class="font-semibold text-amber-900">Tu selección cambió</p><p class="mt-1 text-sm text-amber-700">Algún servicio ya no está disponible. Actualiza tu selección para continuar.</p><Link href="/reservar" class="mt-4 inline-flex rounded-xl bg-amber-900 px-4 py-2 text-sm font-semibold text-white">Revisar servicios</Link></div>
                    <div v-else-if="slots.length === 0" class="mt-6 rounded-2xl bg-violet-50 px-5 py-10 text-center"><div class="text-3xl text-violet-500">◷</div><p class="mt-3 font-semibold">No quedan horarios para este día</p><p class="mt-1 text-sm text-slate-500">Prueba con otra fecha de las disponibles.</p></div>
                    <div v-else class="mt-6 space-y-6">
                        <div v-for="group in [{ label: 'Mañana', items: morningSlots }, { label: 'Tarde', items: afternoonSlots }, { label: 'Noche', items: eveningSlots }]" v-show="group.items.length" :key="group.label">
                            <h3 class="text-sm font-semibold text-slate-500">{{ group.label }}</h3>
                            <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button v-for="slot in group.items" :key="slot.startsAt" type="button" class="rounded-xl border px-3 py-3 text-sm font-semibold transition" :class="selectedStartsAt === slot.startsAt ? 'border-violet-600 bg-violet-600 text-white shadow-md' : 'border-violet-100 bg-white text-slate-700 hover:border-violet-400 hover:text-violet-700'" :aria-pressed="selectedStartsAt === slot.startsAt" @click="selectedStartsAt = slot.startsAt">{{ formatTime(slot) }}</button>
                            </div>
                        </div>
                    </div>
                </section>
            </template>
        </main>

        <div v-if="selectedSlot" class="fixed inset-x-0 bottom-0 z-30 border-t border-violet-100 bg-white/95 shadow-[0_-16px_45px_rgba(44,24,65,0.12)] backdrop-blur-xl">
            <div class="mx-auto flex max-w-5xl flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Tu cita</p><p class="mt-1 font-semibold">{{ dates.find((date) => date.value === selectedDate)?.weekday }} {{ dates.find((date) => date.value === selectedDate)?.day }} · {{ formatTime(selectedSlot) }}</p></div><p class="rounded-2xl bg-violet-50 px-5 py-3 text-center text-sm font-semibold text-violet-700">Horario seleccionado ✓</p></div>
        </div>
    </div>
</template>

<style scoped>
.schedule-hero { background: #241238; }
.continue-button { background: #7c3aed; }
.continue-button:hover { background: #6d28d9; }
</style>
