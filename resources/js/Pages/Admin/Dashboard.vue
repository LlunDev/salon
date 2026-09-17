<script setup>
import BasePage from "@/Components/BasePage.vue";
import { toast } from "@/Composables/useToast";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";

defineProps({ salon: { type: Object, required: true } });

const today = new Intl.DateTimeFormat("en-CA").format(new Date());
const selectedDate = ref(today);
const timezone = ref("");
const appointments = ref([]);
const blocks = ref([]);
const services = ref([]);
const clients = ref([]);
const loading = ref(true);
const loadError = ref("");
const selectedAppointment = ref(null);
const sheet = ref(null);
const saving = ref(false);
const formError = ref("");
const errors = ref({});
const clientSearch = ref("");
const manualForm = reactive({ clientId: "", startsAtLocal: "", serviceIds: [] });
const blockForm = reactive({ startsAtLocal: "", endsAtLocal: "", reason: "" });
const cancelForm = reactive({ reason: "", notifyClient: false });
let loadController;
let clientController;

const activeAppointments = computed(() => appointments.value.filter((item) => item.status === "confirmed").length);
const completedAppointments = computed(() => appointments.value.filter((item) => item.status === "completed").length);
const dayLabel = computed(() => new Intl.DateTimeFormat("es", { dateStyle: "full", timeZone: "UTC" }).format(new Date(`${selectedDate.value}T12:00:00Z`)));

function headers(json = false) {
    const value = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    if (json) {
        value["Content-Type"] = "application/json";
        value["X-CSRF-TOKEN"] = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    }
    return value;
}

async function payload(response) {
    if (response.status === 204) return null;
    return response.json();
}

async function loadAgenda() {
    loadController?.abort();
    const controller = new AbortController();
    loadController = controller;
    loading.value = true;
    loadError.value = "";
    try {
        const response = await fetch(`/api/admin/agenda?date=${selectedDate.value}`, { headers: headers(), signal: controller.signal });
        const body = await payload(response);
        if (!response.ok) throw new Error();
        appointments.value = body.data.appointments;
        blocks.value = body.data.blocks;
        services.value = body.data.services;
        timezone.value = body.data.timezone;
    } catch (error) {
        if (error.name !== "AbortError") loadError.value = "No pudimos cargar la agenda de este día.";
    } finally {
        if (!controller.signal.aborted) loading.value = false;
    }
}

async function searchClients() {
    clientController?.abort();
    const controller = new AbortController();
    clientController = controller;
    try {
        const response = await fetch(`/api/admin/clients?search=${encodeURIComponent(clientSearch.value)}`, { headers: headers(), signal: controller.signal });
        const body = await payload(response);
        if (response.ok) clients.value = body.data;
    } catch (error) {
        if (error.name !== "AbortError") clients.value = [];
    }
}

function moveDate(days) {
    const date = new Date(`${selectedDate.value}T12:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    selectedDate.value = date.toISOString().slice(0, 10);
}

function localTime(value) {
    return new Intl.DateTimeFormat("es", { hour: "2-digit", minute: "2-digit", hour12: false, timeZone: timezone.value }).format(new Date(value));
}

function currency(value) {
    return new Intl.NumberFormat("es-CO", { style: "currency", currency: "COP", maximumFractionDigits: 0 }).format(Number(value));
}

function openSheet(name, appointment = null) {
    sheet.value = name;
    selectedAppointment.value = appointment;
    errors.value = {};
    formError.value = "";
    if (name === "manual") {
        Object.assign(manualForm, { clientId: "", startsAtLocal: `${selectedDate.value}T09:00`, serviceIds: [] });
        clientSearch.value = "";
        searchClients();
    }
    if (name === "block") Object.assign(blockForm, { startsAtLocal: `${selectedDate.value}T09:00`, endsAtLocal: `${selectedDate.value}T10:00`, reason: "" });
    if (name === "cancel") Object.assign(cancelForm, { reason: "", notifyClient: false });
    document.body.style.overflow = "hidden";
}

function closeSheet() {
    if (saving.value) return;
    sheet.value = null;
    selectedAppointment.value = null;
    document.body.style.overflow = "";
}

function backendDate(value) {
    return value.replace("T", " ");
}

async function submit(url, body, successMessage, method = "POST") {
    saving.value = true;
    errors.value = {};
    formError.value = "";
    try {
        const response = await fetch(url, { method, headers: headers(true), body: body ? JSON.stringify(body) : undefined });
        const data = await payload(response);
        if (response.status === 422) {
            errors.value = data.errors ?? {};
            formError.value = data.message ?? "Revisa los datos ingresados.";
            return false;
        }
        if (!response.ok) throw new Error();
        sheet.value = null;
        selectedAppointment.value = null;
        document.body.style.overflow = "";
        toast.success(successMessage);
        await loadAgenda();
        return true;
    } catch {
        formError.value = "No pudimos completar la operación. Inténtalo nuevamente.";
        toast.error(formError.value);
        return false;
    } finally {
        saving.value = false;
    }
}

function createAppointment() {
    return submit("/api/admin/appointments", {
        clientId: manualForm.clientId,
        startsAtLocal: backendDate(manualForm.startsAtLocal),
        serviceIds: manualForm.serviceIds,
    }, "La cita se agregó a la agenda.");
}

function createBlock() {
    return submit("/api/admin/schedule/blocks", {
        startsAtLocal: backendDate(blockForm.startsAtLocal),
        endsAtLocal: backendDate(blockForm.endsAtLocal),
        reason: blockForm.reason.trim() || null,
    }, "El bloqueo se agregó a la agenda.");
}

function cancelAppointment() {
    return submit(`/api/appointments/${selectedAppointment.value.id}/cancel`, {
        reason: cancelForm.reason.trim() || null,
        notifyClient: cancelForm.notifyClient,
    }, "La cita fue cancelada.");
}

function completeAppointment(appointment) {
    selectedAppointment.value = appointment;
    return submit(`/api/appointments/${appointment.id}/complete`, {}, "La cita fue marcada como completada.");
}

async function deleteBlock(block) {
    if (!window.confirm("¿Eliminar este bloqueo de disponibilidad?")) return;
    await submit(`/api/admin/schedule/blocks/${block.id}`, null, "El bloqueo fue eliminado.", "DELETE");
}

function errorFor(field) {
    return errors.value[field]?.[0];
}

let searchTimer;
watch(clientSearch, () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(searchClients, 250);
});
watch(selectedDate, loadAgenda);
onMounted(loadAgenda);
onBeforeUnmount(() => {
    loadController?.abort();
    clientController?.abort();
    window.clearTimeout(searchTimer);
    document.body.style.overflow = "";
});
</script>

<template>
    <AdminLayout title="Agenda" :salon="salon">
        <BasePage title="Agenda" description="Gestiona las citas y la disponibilidad diaria del salón.">
            <div class="flex flex-col gap-3 rounded-[1.75rem] border border-[#e8def4] bg-white p-4 shadow-[0_18px_45px_rgba(15,23,42,0.06)] sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center justify-between gap-2 sm:justify-start">
                    <button class="agenda-icon-button" type="button" aria-label="Día anterior" @click="moveDate(-1)">‹</button>
                    <div class="min-w-0 text-center sm:min-w-64">
                        <p class="truncate text-base font-semibold capitalize text-slate-950">{{ dayLabel }}</p>
                        <button v-if="selectedDate !== today" type="button" class="mt-1 text-xs font-semibold text-[#7c3aed]" @click="selectedDate = today">Volver a hoy</button>
                        <p v-else class="mt-1 text-xs font-semibold uppercase tracking-[0.2em] text-[#7c3aed]">Hoy</p>
                    </div>
                    <button class="agenda-icon-button" type="button" aria-label="Día siguiente" @click="moveDate(1)">›</button>
                </div>
                <input v-model="selectedDate" type="date" class="rounded-xl border-slate-200 text-sm focus:border-[#7c3aed] focus:ring-[#7c3aed]" />
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <article class="agenda-stat"><span>Confirmadas</span><strong>{{ activeAppointments }}</strong></article>
                <article class="agenda-stat"><span>Completadas</span><strong>{{ completedAppointments }}</strong></article>
                <article class="agenda-stat"><span>Bloqueos</span><strong>{{ blocks.length }}</strong></article>
                <article class="agenda-stat"><span>Total citas</span><strong>{{ appointments.length }}</strong></article>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <button type="button" class="agenda-primary" @click="openSheet('manual')">+ Crear cita manual</button>
                <button type="button" class="agenda-secondary" @click="openSheet('block')">Bloquear horario</button>
            </div>

            <section class="rounded-[1.75rem] border border-[#e8def4] bg-white p-4 shadow-[0_18px_45px_rgba(15,23,42,0.06)] sm:p-6">
                <div class="flex items-end justify-between gap-4">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#7c3aed]">Jornada</p><h2 class="mt-2 text-xl font-semibold text-slate-950">Citas y bloqueos</h2></div>
                    <span v-if="timezone" class="text-xs text-slate-500">{{ timezone }}</span>
                </div>

                <div v-if="loading" class="mt-6 space-y-3"><div v-for="item in 3" :key="item" class="h-28 animate-pulse rounded-2xl bg-slate-100" /></div>
                <div v-else-if="loadError" class="mt-6 rounded-2xl bg-rose-50 p-5 text-sm text-rose-700"><p>{{ loadError }}</p><button type="button" class="mt-3 font-semibold underline" @click="loadAgenda">Reintentar</button></div>
                <div v-else-if="appointments.length === 0 && blocks.length === 0" class="mt-6 rounded-2xl border border-dashed border-[#d8cbea] bg-[#faf8ff] px-5 py-12 text-center"><p class="font-semibold text-slate-900">La jornada está libre</p><p class="mt-2 text-sm text-slate-500">Crea una cita o registra un bloqueo para este día.</p></div>
                <div v-else class="mt-6 space-y-3">
                    <article v-for="appointment in appointments" :key="appointment.id" class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                        <button type="button" class="w-full text-left" @click="openSheet('detail', appointment)">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="text-lg font-semibold text-slate-950">{{ localTime(appointment.startsAt) }} - {{ localTime(appointment.endsAt) }}</p><p class="mt-1 font-medium text-slate-800">{{ appointment.client.name }}</p><p class="mt-1 text-sm text-slate-500">{{ appointment.services.map((item) => item.name).join(' · ') }}</p></div>
                                <span class="agenda-status" :class="`agenda-status--${appointment.status}`">{{ appointment.status === 'confirmed' ? 'Confirmada' : appointment.status === 'completed' ? 'Completada' : 'Cancelada' }}</span>
                            </div>
                        </button>
                    </article>
                    <article v-for="block in blocks" :key="block.id" class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-amber-950">{{ localTime(block.startsAt) }} - {{ localTime(block.endsAt) }} · Horario bloqueado</p><p class="mt-1 text-sm text-amber-800">{{ block.reason || 'Sin motivo registrado' }}</p></div><button type="button" class="text-sm font-semibold text-rose-700" @click="deleteBlock(block)">Eliminar</button></div>
                    </article>
                </div>
            </section>
        </BasePage>

        <div v-if="sheet" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/55 p-0 sm:items-center sm:p-6" @click.self="closeSheet">
            <section class="max-h-[92vh] w-full overflow-y-auto rounded-t-[2rem] bg-white p-5 shadow-2xl sm:max-w-xl sm:rounded-[2rem] sm:p-7">
                <div class="flex items-center justify-between gap-4"><h2 class="text-xl font-semibold text-slate-950">{{ sheet === 'manual' ? 'Nueva cita' : sheet === 'block' ? 'Bloquear horario' : sheet === 'cancel' ? 'Cancelar cita' : 'Detalle de cita' }}</h2><button type="button" class="agenda-icon-button" aria-label="Cerrar" @click="closeSheet">×</button></div>

                <form v-if="sheet === 'manual'" class="mt-6 space-y-5" @submit.prevent="createAppointment">
                    <label class="agenda-field"><span>Buscar cliente</span><input v-model="clientSearch" type="search" placeholder="Nombre, correo o teléfono" /></label>
                    <label class="agenda-field"><span>Cliente</span><select v-model="manualForm.clientId" required><option value="">Selecciona un cliente</option><option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }} · {{ client.email }}</option></select><small v-if="errorFor('clientId')">{{ errorFor('clientId') }}</small></label>
                    <label class="agenda-field"><span>Fecha y hora</span><input v-model="manualForm.startsAtLocal" type="datetime-local" required /><small v-if="errorFor('startsAtLocal')">{{ errorFor('startsAtLocal') }}</small></label>
                    <fieldset><legend class="text-sm font-semibold text-slate-700">Servicios</legend><div class="mt-2 max-h-52 space-y-2 overflow-y-auto rounded-2xl border border-slate-200 p-3"><label v-for="service in services" :key="service.id" class="flex cursor-pointer items-center justify-between gap-3 rounded-xl p-2 hover:bg-slate-50"><span><strong class="block text-sm text-slate-900">{{ service.name }}</strong><small class="text-slate-500">{{ service.duration }} min · {{ currency(service.price) }}</small></span><input v-model="manualForm.serviceIds" type="checkbox" :value="service.id" class="rounded text-[#7c3aed] focus:ring-[#7c3aed]" /></label></div><small v-if="errorFor('serviceIds')" class="text-rose-600">{{ errorFor('serviceIds') }}</small></fieldset>
                    <p v-if="formError" class="text-sm text-rose-600">{{ formError }}</p><button class="agenda-primary w-full" type="submit" :disabled="saving">{{ saving ? 'Creando...' : 'Confirmar cita' }}</button>
                </form>

                <form v-else-if="sheet === 'block'" class="mt-6 space-y-5" @submit.prevent="createBlock">
                    <label class="agenda-field"><span>Desde</span><input v-model="blockForm.startsAtLocal" type="datetime-local" required /><small v-if="errorFor('startsAtLocal')">{{ errorFor('startsAtLocal') }}</small></label>
                    <label class="agenda-field"><span>Hasta</span><input v-model="blockForm.endsAtLocal" type="datetime-local" required /><small v-if="errorFor('endsAtLocal')">{{ errorFor('endsAtLocal') }}</small></label>
                    <label class="agenda-field"><span>Motivo opcional</span><textarea v-model="blockForm.reason" rows="3" maxlength="1000" /></label>
                    <p class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800">El bloqueo impedirá nuevas reservas, pero no cancelará citas existentes.</p><p v-if="formError" class="text-sm text-rose-600">{{ formError }}</p><button class="agenda-primary w-full" type="submit" :disabled="saving">{{ saving ? 'Guardando...' : 'Crear bloqueo' }}</button>
                </form>

                <form v-else-if="sheet === 'cancel'" class="mt-6 space-y-5" @submit.prevent="cancelAppointment">
                    <label class="agenda-field"><span>Motivo opcional</span><textarea v-model="cancelForm.reason" rows="4" maxlength="1000" /></label><label class="flex items-center gap-3 text-sm text-slate-700"><input v-model="cancelForm.notifyClient" type="checkbox" class="rounded text-[#7c3aed] focus:ring-[#7c3aed]" />Notificar al cliente cuando esté disponible el canal de comunicaciones</label><p v-if="formError" class="text-sm text-rose-600">{{ formError }}</p><button class="w-full rounded-xl bg-rose-600 px-4 py-3 font-semibold text-white disabled:opacity-50" type="submit" :disabled="saving">{{ saving ? 'Cancelando...' : 'Cancelar cita' }}</button>
                </form>

                <div v-else-if="selectedAppointment" class="mt-6 space-y-5">
                    <div class="rounded-2xl bg-[#f7f2ff] p-4"><p class="text-2xl font-semibold text-slate-950">{{ localTime(selectedAppointment.startsAt) }} - {{ localTime(selectedAppointment.endsAt) }}</p><p class="mt-1 text-sm text-slate-600">{{ selectedAppointment.duration }} minutos · {{ currency(selectedAppointment.total) }}</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Cliente</p><p class="mt-2 font-semibold text-slate-950">{{ selectedAppointment.client.name }}</p><a class="mt-1 block text-sm text-[#7c3aed]" :href="`mailto:${selectedAppointment.client.email}`">{{ selectedAppointment.client.email }}</a><a v-if="selectedAppointment.client.phone" class="mt-1 block text-sm text-[#7c3aed]" :href="`tel:${selectedAppointment.client.phone}`">{{ selectedAppointment.client.phone }}</a></div>
                    <div><p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Servicios reservados</p><div class="mt-2 space-y-2"><div v-for="service in selectedAppointment.services" :key="`${service.name}-${service.price}`" class="flex justify-between gap-3 rounded-xl bg-slate-50 p-3 text-sm"><span>{{ service.name }} · {{ service.duration }} min</span><strong>{{ currency(service.price) }}</strong></div></div></div>
                    <p v-if="selectedAppointment.cancellationReason" class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700">Motivo de cancelación: {{ selectedAppointment.cancellationReason }}</p>
                    <div v-if="selectedAppointment.status === 'confirmed'" class="grid gap-3 sm:grid-cols-2"><button type="button" class="agenda-secondary" @click="openSheet('cancel', selectedAppointment)">Cancelar cita</button><button type="button" class="agenda-primary" :disabled="saving" @click="completeAppointment(selectedAppointment)">Marcar completada</button></div>
                    <p v-if="formError" class="text-sm text-rose-600">{{ formError }}</p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<style scoped>
.agenda-icon-button { @apply inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-xl font-semibold text-slate-700 transition hover:border-[#c4a5ee] hover:text-[#7c3aed]; }
.agenda-stat { @apply rounded-2xl border border-[#e8def4] bg-white p-4 shadow-[0_12px_30px_rgba(15,23,42,0.05)]; }
.agenda-stat span { @apply block text-xs font-medium uppercase tracking-[0.14em] text-slate-500; }
.agenda-stat strong { @apply mt-2 block text-2xl text-slate-950; }
.agenda-primary { @apply rounded-xl bg-[#6d28d9] px-4 py-3 text-center font-semibold text-white shadow-[0_10px_24px_rgba(109,40,217,0.22)] transition hover:bg-[#5b21b6] disabled:cursor-not-allowed disabled:opacity-50; }
.agenda-secondary { @apply rounded-xl border border-[#cdb8e8] bg-white px-4 py-3 text-center font-semibold text-[#6d28d9] transition hover:bg-[#f8f4ff]; }
.agenda-status { @apply shrink-0 rounded-full px-3 py-1 text-xs font-semibold; }
.agenda-status--confirmed { @apply bg-violet-100 text-violet-700; }
.agenda-status--completed { @apply bg-emerald-100 text-emerald-700; }
.agenda-status--cancelled { @apply bg-slate-200 text-slate-600; }
.agenda-field { @apply block text-sm font-semibold text-slate-700; }
.agenda-field input, .agenda-field select, .agenda-field textarea { @apply mt-2 block w-full rounded-xl border-slate-200 text-sm font-normal text-slate-950 focus:border-[#7c3aed] focus:ring-[#7c3aed]; }
.agenda-field small { @apply mt-1 block font-normal text-rose-600; }
</style>
