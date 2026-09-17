<script setup>
import { useBookingAttempt } from '@/Composables/useBookingAttempt';
import { useBookingSelection } from '@/Composables/useBookingSelection';
import { useBookingSlotSelection } from '@/Composables/useBookingSlotSelection';
import { toast } from '@/Composables/useToast';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    salon: { type: Object, required: true },
    customer: { type: Object, required: true },
});

const services = ref([]);
const loading = ref(true);
const submitting = ref(false);
const loadError = ref('');
const { selectedIds, selectedCount, reconcile, clear: clearServices } = useBookingSelection(props.salon.id);
const { selectedDate, selectedStartsAt, clear: clearSlot } = useBookingSlotSelection(props.salon.id, '');
const { bookingKey, clear: clearAttempt } = useBookingAttempt(props.salon.id);
const selectedServices = computed(() => services.value.filter((service) => selectedIds.value.includes(service.id)));
const totalDuration = computed(() => selectedServices.value.reduce((total, service) => total + service.duration, 0));
const totalPrice = computed(() => selectedServices.value.reduce((total, service) => total + Number(service.price), 0));
const hasBookingSelection = computed(() => selectedCount.value > 0 && Boolean(selectedStartsAt.value));
const hasContactDetails = computed(() => Boolean(props.customer.name && props.customer.email && props.customer.phone));

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
        loadError.value = 'No pudimos validar los servicios seleccionados.';
    } finally {
        loading.value = false;
    }
}

async function confirmAppointment() {
    if (!hasBookingSelection.value || submitting.value) return;
    submitting.value = true;

    try {
        const response = await fetch('/api/appointments', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                bookingKey: bookingKey.value,
                startsAt: selectedStartsAt.value,
                serviceIds: selectedIds.value,
            }),
        });
        const payload = await response.json();

        if (!response.ok) {
            const validationMessage = Object.values(payload.errors ?? {}).flat()[0];
            throw new Error(validationMessage || payload.message || 'No pudimos confirmar la cita.');
        }

        clearServices();
        clearSlot();
        clearAttempt();
        window.location.assign(`/reservar/confirmacion/${payload.data.id}`);
    } catch (error) {
        toast.error(error.message || 'No pudimos confirmar la cita. Inténtalo nuevamente.');
    } finally {
        submitting.value = false;
    }
}

function formatDate(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('es-CO', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(`${value}T12:00:00Z`));
}

function formatTime(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('es-CO', { hour: 'numeric', minute: '2-digit', timeZone: props.salon.timezone }).format(new Date(value));
}


function formatPrice(value) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', maximumFractionDigits: 0,
    }).format(Number(value));
}

onMounted(loadServices);
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
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-fuchsia-700">Revisa tu cita</p>
                <p class="mt-3 text-lg font-semibold capitalize">{{ formatDate(selectedDate) }} · {{ formatTime(selectedStartsAt) }}</p>

                <p v-if="loading" class="mt-5 text-sm text-slate-500">Validando servicios...</p>
                <div v-else-if="loadError" class="mt-5 rounded-2xl bg-rose-50 p-4 text-sm text-rose-700">
                    {{ loadError }}
                    <button type="button" class="ml-1 font-semibold underline" @click="loadServices">Reintentar</button>
                </div>
                <div v-else class="mt-5 border-t border-slate-100 pt-4">
                    <div v-for="service in selectedServices" :key="service.id" class="flex justify-between gap-4 py-2 text-sm">
                        <span>{{ service.name }} · {{ service.duration }} min</span>
                        <span class="font-semibold">{{ formatPrice(service.price) }}</span>
                    </div>
                    <div class="mt-3 flex justify-between border-t border-slate-100 pt-4 font-semibold">
                        <span>Total · {{ totalDuration }} min</span>
                        <span class="text-violet-700">{{ formatPrice(totalPrice) }}</span>
                    </div>
                </div>

                <p v-if="!hasContactDetails" class="mt-6 rounded-2xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">Completa tu teléfono en el perfil antes de confirmar la cita.</p>
                <button
                    type="button"
                    class="confirm-button mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-2xl px-6 py-3.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="loading || Boolean(loadError) || !hasContactDetails || submitting"
                    @click="confirmAppointment"
                >
                    {{ submitting ? 'Confirmando...' : 'Confirmar cita' }}
                </button>
                <p class="mt-3 text-center text-xs leading-5 text-slate-500">El horario y los importes se validarán nuevamente antes de confirmar.</p>
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
.confirm-button { background: #7c3aed; }
.confirm-button:hover:not(:disabled) { background: #6d28d9; }
</style>
