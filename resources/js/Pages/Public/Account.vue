<script setup>
import { useBookingSelection } from '@/Composables/useBookingSelection';
import { useBookingSlotSelection } from '@/Composables/useBookingSlotSelection';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    salon: { type: Object, required: true },
    customer: { type: Object, required: true },
});

const { selectedCount } = useBookingSelection(props.salon.id);
const { selectedDate, selectedStartsAt } = useBookingSlotSelection(props.salon.id, '');
const hasBookingSelection = computed(() => selectedCount.value > 0 && Boolean(selectedStartsAt.value));

function formatDate(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('es-CO', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));
}

function formatTime(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('es-CO', { hour: 'numeric', minute: '2-digit', timeZone: props.salon.timezone }).format(new Date(value));
}
</script>

<template>
    <Head :title="`Tus datos - ${salon.name}`" />
    <div class="min-h-screen bg-[#faf8fc] text-slate-950">
        <header class="account-header text-white">
            <div class="mx-auto max-w-3xl px-5 py-10 sm:px-8 sm:py-14">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-fuchsia-300">Paso 3 de 4</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Tus datos están listos</h1>
                <p class="mt-3 text-sm text-violet-100">Tu reserva quedará asociada a tu cuenta de {{ salon.name }}.</p>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-5 py-8 sm:px-8 sm:py-10">
            <section class="rounded-[2rem] border border-violet-100 bg-white p-6 shadow-[0_18px_45px_rgba(44,24,65,0.07)] sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-fuchsia-700">Cliente</p>
                <h2 class="mt-2 text-2xl font-semibold">{{ customer.name }}</h2>
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    <div class="rounded-2xl bg-violet-50 p-4"><dt class="font-semibold text-slate-500">Correo</dt><dd class="mt-1 text-slate-900">{{ customer.email }}</dd></div>
                    <div class="rounded-2xl bg-violet-50 p-4"><dt class="font-semibold text-slate-500">Teléfono</dt><dd class="mt-1 text-slate-900">{{ customer.phone || 'Pendiente de completar' }}</dd></div>
                </dl>
            </section>

            <section v-if="hasBookingSelection" class="mt-6 rounded-[2rem] border border-violet-100 bg-white p-6 sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-fuchsia-700">Selección conservada</p>
                <p class="mt-3 text-lg font-semibold capitalize">{{ formatDate(selectedDate) }} · {{ formatTime(selectedStartsAt) }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ selectedCount }} {{ selectedCount === 1 ? 'servicio seleccionado' : 'servicios seleccionados' }}</p>
                <p class="mt-6 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">En el siguiente paso validaremos nuevamente el horario y confirmaremos la cita.</p>
            </section>

            <section v-else class="mt-6 rounded-[2rem] border border-amber-200 bg-amber-50 p-6 text-center">
                <h2 class="font-semibold text-amber-950">Falta seleccionar la cita</h2>
                <p class="mt-2 text-sm text-amber-800">Elige servicios, fecha y hora antes de continuar.</p>
                <Link href="/reservar" class="mt-5 inline-flex rounded-xl bg-amber-900 px-5 py-3 text-sm font-semibold text-white">Volver a la reserva</Link>
            </section>
        </main>
    </div>
</template>

<style scoped>
.account-header { background: #241238; }
</style>
