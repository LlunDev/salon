<script setup>
import BasePage from "@/Components/BasePage.vue";
import { toast } from "@/Composables/useToast";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
} from "vue";

defineProps({
    salon: { type: Object, required: true },
    pageTitle: { type: String, required: true },
});

const dayNames = [
    "Domingo",
    "Lunes",
    "Martes",
    "Miércoles",
    "Jueves",
    "Viernes",
    "Sábado",
];
const slotIntervals = [1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30, 60];
const schedule = reactive({
    timezone: "",
    slotIntervalMinutes: 15,
    appointmentCapacity: 1,
    cancellationNoticeHours: 0,
    weeklyHours: [],
});
const blocks = ref([]);
const loading = ref(true);
const loadError = ref("");
const savingSchedule = ref(false);
const scheduleErrors = ref({});
const scheduleError = ref("");
const blockSheetOpen = ref(false);
const savingBlock = ref(false);
const blockErrors = ref({});
const blockError = ref("");
const deleteCandidate = ref(null);
const deletingBlock = ref(false);
const deleteError = ref("");
const blockStartInput = ref(null);
const blockForm = reactive({ startsAtLocal: "", endsAtLocal: "", reason: "" });
let loadController;
let scheduleController;
let blockController;
let deleteController;

const activeDays = computed(
    () => schedule.weeklyHours.filter((day) => !day.closed).length,
);

function apiHeaders(includeJson = false) {
    const headers = {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    };
    if (includeJson) {
        headers["Content-Type"] = "application/json";
        headers["X-CSRF-TOKEN"] =
            document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    }
    return headers;
}

async function responsePayload(response) {
    if (response.status === 204) return null;
    return response.json();
}

function applySchedule(data) {
    Object.assign(schedule, {
        timezone: data.timezone,
        slotIntervalMinutes: data.slotIntervalMinutes,
        appointmentCapacity: data.appointmentCapacity,
        cancellationNoticeHours: data.cancellationNoticeHours,
        weeklyHours: [...data.weeklyHours]
            .sort((a, b) => a.weekday - b.weekday)
            .map((day) => ({ ...day })),
    });
}

async function loadSettings() {
    loadController?.abort();
    const controller = new AbortController();
    loadController = controller;
    loading.value = true;
    loadError.value = "";

    try {
        const [scheduleResponse, blocksResponse] = await Promise.all([
            fetch("/api/admin/schedule", {
                headers: apiHeaders(),
                signal: controller.signal,
            }),
            fetch("/api/admin/schedule/blocks", {
                headers: apiHeaders(),
                signal: controller.signal,
            }),
        ]);
        const [schedulePayload, blocksPayload] = await Promise.all([
            responsePayload(scheduleResponse),
            responsePayload(blocksResponse),
        ]);
        if (!scheduleResponse.ok || !blocksResponse.ok) throw new Error();
        applySchedule(schedulePayload.data);
        blocks.value = blocksPayload.data;
    } catch (error) {
        if (error.name !== "AbortError")
            loadError.value = "No pudimos cargar la configuración del salón.";
    } finally {
        if (!controller.signal.aborted) loading.value = false;
    }
}

function toggleDay(day) {
    day.closed = !day.closed;
    if (day.closed) {
        day.opensAt = null;
        day.closesAt = null;
    } else {
        day.opensAt = day.opensAt || "09:00";
        day.closesAt = day.closesAt || "18:00";
    }
}

function schedulePayload() {
    return {
        timezone: schedule.timezone.trim(),
        slotIntervalMinutes: Number(schedule.slotIntervalMinutes),
        appointmentCapacity: Number(schedule.appointmentCapacity),
        cancellationNoticeHours: Number(schedule.cancellationNoticeHours),
        weeklyHours: schedule.weeklyHours.map((day) => ({
            weekday: day.weekday,
            closed: day.closed,
            opensAt: day.closed ? null : day.opensAt,
            closesAt: day.closed ? null : day.closesAt,
        })),
    };
}

async function saveSchedule() {
    scheduleController?.abort();
    const controller = new AbortController();
    scheduleController = controller;
    savingSchedule.value = true;
    scheduleErrors.value = {};
    scheduleError.value = "";

    try {
        const response = await fetch("/api/admin/schedule", {
            method: "PUT",
            headers: apiHeaders(true),
            body: JSON.stringify(schedulePayload()),
            signal: controller.signal,
        });
        const payload = await responsePayload(response);
        if (response.status === 422) {
            scheduleErrors.value = payload.errors ?? {};
            scheduleError.value =
                "Revisa los campos señalados antes de guardar.";
            return;
        }
        if (!response.ok) throw new Error();
        applySchedule(payload.data);
        toast.success(
            "La disponibilidad del salón se actualizó correctamente.",
        );
    } catch (error) {
        if (error.name !== "AbortError") {
            scheduleError.value =
                "No pudimos guardar la configuración. Inténtalo nuevamente.";
            toast.error(scheduleError.value);
        }
    } finally {
        if (!controller.signal.aborted) savingSchedule.value = false;
    }
}

function fieldError(field) {
    return scheduleErrors.value[field]?.[0];
}

function dayError(index) {
    return (
        fieldError(`weeklyHours.${index}.opensAt`) ||
        fieldError(`weeklyHours.${index}.closesAt`)
    );
}

function openBlockSheet() {
    Object.assign(blockForm, {
        startsAtLocal: "",
        endsAtLocal: "",
        reason: "",
    });
    blockErrors.value = {};
    blockError.value = "";
    blockSheetOpen.value = true;
    document.body.style.overflow = "hidden";
    nextTick(() => blockStartInput.value?.focus());
}

function closeBlockSheet() {
    if (savingBlock.value) return;
    blockSheetOpen.value = false;
    document.body.style.overflow = "";
}

function backendDateTime(value) {
    return value ? value.replace("T", " ") : value;
}

async function createBlock() {
    blockController?.abort();
    const controller = new AbortController();
    blockController = controller;
    savingBlock.value = true;
    blockErrors.value = {};
    blockError.value = "";

    try {
        const response = await fetch("/api/admin/schedule/blocks", {
            method: "POST",
            headers: apiHeaders(true),
            signal: controller.signal,
            body: JSON.stringify({
                startsAtLocal: backendDateTime(blockForm.startsAtLocal),
                endsAtLocal: backendDateTime(blockForm.endsAtLocal),
                reason: blockForm.reason.trim() || null,
            }),
        });
        const payload = await responsePayload(response);
        if (response.status === 422) {
            blockErrors.value = payload.errors ?? {};
            blockError.value = "Revisa las fechas ingresadas.";
            return;
        }
        if (!response.ok) throw new Error();
        blocks.value = [...blocks.value, payload.data].sort((a, b) =>
            a.startsAt.localeCompare(b.startsAt),
        );
        blockSheetOpen.value = false;
        document.body.style.overflow = "";
        toast.success("El bloqueo se agregó a la agenda.");
    } catch (error) {
        if (error.name !== "AbortError") {
            blockError.value =
                "No pudimos crear el bloqueo. Inténtalo nuevamente.";
            toast.error(blockError.value);
        }
    } finally {
        if (!controller.signal.aborted) savingBlock.value = false;
    }
}

function confirmDelete(block) {
    deleteCandidate.value = block;
    deleteError.value = "";
    document.body.style.overflow = "hidden";
}

function closeDeleteConfirmation() {
    if (deletingBlock.value) return;
    deleteCandidate.value = null;
    deleteError.value = "";
    document.body.style.overflow = "";
}

async function deleteBlock() {
    deleteController?.abort();
    const controller = new AbortController();
    deleteController = controller;
    deletingBlock.value = true;
    deleteError.value = "";
    const block = deleteCandidate.value;

    try {
        const response = await fetch(`/api/admin/schedule/blocks/${block.id}`, {
            method: "DELETE",
            headers: apiHeaders(true),
            signal: controller.signal,
        });
        if (!response.ok) throw new Error();
        blocks.value = blocks.value.filter((item) => item.id !== block.id);
        deleteCandidate.value = null;
        document.body.style.overflow = "";
        toast.success("El bloqueo se eliminó correctamente.");
    } catch (error) {
        if (error.name !== "AbortError") {
            deleteError.value =
                "No pudimos eliminar el bloqueo. Inténtalo nuevamente.";
            toast.error(deleteError.value);
        }
    } finally {
        if (!controller.signal.aborted) deletingBlock.value = false;
    }
}

function parseLocalDateTime(value) {
    const [date, time] = value.split(" ");
    const [year, month, day] = date.split("-").map(Number);
    const [hour, minute] = time.split(":").map(Number);
    return new Date(year, month - 1, day, hour, minute);
}

function formatBlockDate(value) {
    return new Intl.DateTimeFormat("es-CO", {
        weekday: "short",
        day: "numeric",
        month: "short",
        hour: "numeric",
        minute: "2-digit",
    }).format(parseLocalDateTime(value));
}

function onKeydown(event) {
    if (event.key !== "Escape") return;
    if (deleteCandidate.value) closeDeleteConfirmation();
    else if (blockSheetOpen.value) closeBlockSheet();
}

onMounted(() => {
    loadSettings();
    window.addEventListener("keydown", onKeydown);
});

onBeforeUnmount(() => {
    loadController?.abort();
    scheduleController?.abort();
    blockController?.abort();
    deleteController?.abort();
    document.body.style.overflow = "";
    window.removeEventListener("keydown", onKeydown);
});
</script>

<template>
    <AdminLayout :title="pageTitle" :salon="salon">
        <BasePage
            eyebrow="Operación del salón"
            :title="pageTitle"
            description="Define cuándo atiendes, el ritmo de tu agenda y las pausas excepcionales del equipo."
        >
            <div
                v-if="loading"
                class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(19rem,0.8fr)]"
                aria-label="Cargando configuración"
            >
                <div
                    class="h-[42rem] animate-pulse rounded-[2rem] border border-[#e8def4] bg-white/75"
                />
                <div
                    class="h-[28rem] animate-pulse rounded-[2rem] border border-[#e8def4] bg-white/75"
                />
            </div>

            <div
                v-else-if="loadError"
                class="rounded-[2rem] border border-rose-200 bg-white px-6 py-14 text-center shadow-sm"
            >
                <div
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-2xl text-rose-600"
                >
                    !
                </div>
                <h2 class="mt-5 text-xl font-semibold text-slate-950">
                    La configuración no está disponible
                </h2>
                <p class="mt-2 text-sm text-slate-500">
                    {{ loadError }} Revisa tu conexión e inténtalo nuevamente.
                </p>
                <button
                    type="button"
                    class="mt-6 min-h-11 rounded-xl bg-[#7c3aed] px-5 py-3 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(124,58,237,0.24)]"
                    @click="loadSettings"
                >
                    Reintentar
                </button>
            </div>

            <div
                v-else
                class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(20rem,0.75fr)]"
            >
                <form
                    class="overflow-hidden rounded-[2rem] border border-[#e8def4] bg-white shadow-[0_18px_45px_rgba(15,23,42,0.06)]"
                    @submit.prevent="saveSchedule"
                >
                    <div
                        class="border-b border-[#eee7f4] bg-[linear-gradient(135deg,#faf7ff,#fff7fb)] px-5 py-6 sm:px-7"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.24em] text-[#db2777]"
                                >
                                    Disponibilidad habitual
                                </p>
                                <h2
                                    class="mt-2 text-2xl font-semibold tracking-tight text-slate-950"
                                >
                                    Semana de atención
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ activeDays }} de 7 días abiertos
                                </p>
                            </div>
                            <span
                                class="w-fit rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-[#6d28d9] ring-1 ring-[#e3d8f0]"
                                >{{ schedule.timezone }}</span
                            >
                        </div>
                    </div>

                    <div class="space-y-7 p-5 sm:p-7">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label
                                    for="timezone"
                                    class="text-sm font-semibold text-slate-700"
                                    >Zona horaria</label
                                >
                                <input
                                    id="timezone"
                                    v-model="schedule.timezone"
                                    required
                                    autocomplete="off"
                                    class="mt-2 min-h-11 w-full rounded-xl border-[#ded6e6] bg-[#fcfbfd] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                    aria-describedby="timezone-help timezone-error"
                                />
                                <p
                                    id="timezone-help"
                                    class="mt-1.5 text-xs leading-5 text-slate-500"
                                >
                                    Identificador IANA usado para toda la
                                    agenda, por ejemplo America/Bogota.
                                </p>
                                <p
                                    v-if="fieldError('timezone')"
                                    id="timezone-error"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ fieldError("timezone") }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="slot-interval"
                                    class="text-sm font-semibold text-slate-700"
                                    >Intervalo de la agenda</label
                                >
                                <select
                                    id="slot-interval"
                                    v-model="schedule.slotIntervalMinutes"
                                    class="mt-2 min-h-11 w-full rounded-xl border-[#ded6e6] bg-[#fcfbfd] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                >
                                    <option
                                        v-for="minutes in slotIntervals"
                                        :key="minutes"
                                        :value="minutes"
                                    >
                                        Cada {{ minutes }} minutos
                                    </option>
                                </select>
                                <p
                                    v-if="fieldError('slotIntervalMinutes')"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ fieldError("slotIntervalMinutes") }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="capacity"
                                    class="text-sm font-semibold text-slate-700"
                                    >Citas simultáneas</label
                                >
                                <input
                                    id="capacity"
                                    v-model="schedule.appointmentCapacity"
                                    type="number"
                                    min="1"
                                    max="65535"
                                    required
                                    class="mt-2 min-h-11 w-full rounded-xl border-[#ded6e6] bg-[#fcfbfd] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                />
                                <p
                                    v-if="fieldError('appointmentCapacity')"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ fieldError("appointmentCapacity") }}
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <label
                                    for="cancellation-notice"
                                    class="text-sm font-semibold text-slate-700"
                                    >Anticipación mínima para cancelar</label
                                >
                                <div class="relative mt-2 max-w-sm">
                                    <input
                                        id="cancellation-notice"
                                        v-model="
                                            schedule.cancellationNoticeHours
                                        "
                                        type="number"
                                        min="0"
                                        max="65535"
                                        required
                                        class="min-h-11 w-full rounded-xl border-[#ded6e6] bg-[#fcfbfd] pr-16 focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                    />
                                    <span
                                        class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400"
                                        >horas</span
                                    >
                                </div>
                                <p
                                    v-if="fieldError('cancellationNoticeHours')"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ fieldError("cancellationNoticeHours") }}
                                </p>
                            </div>
                        </div>

                        <fieldset>
                            <legend
                                class="text-sm font-semibold text-slate-800"
                            >
                                Horario por día
                            </legend>
                            <div
                                class="mt-3 divide-y divide-[#eee8f3] overflow-hidden rounded-2xl border border-[#e8def4]"
                            >
                                <div
                                    v-for="(day, index) in schedule.weeklyHours"
                                    :key="day.weekday"
                                    class="bg-white p-4 transition sm:p-5"
                                    :class="day.closed ? 'bg-slate-50/75' : ''"
                                >
                                    <div
                                        class="flex min-h-11 items-center justify-between gap-4"
                                    >
                                        <div>
                                            <p
                                                class="font-semibold text-slate-900"
                                            >
                                                {{ dayNames[day.weekday] }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs font-medium"
                                                :class="
                                                    day.closed
                                                        ? 'text-slate-400'
                                                        : 'text-emerald-600'
                                                "
                                            >
                                                {{
                                                    day.closed
                                                        ? "Cerrado"
                                                        : "Abierto"
                                                }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="!day.closed"
                                            :aria-label="`${day.closed ? 'Abrir' : 'Cerrar'} el ${dayNames[day.weekday]}`"
                                            class="relative h-11 w-16 shrink-0 rounded-full transition focus:outline-none focus:ring-2 focus:ring-[#8b5cf6] focus:ring-offset-2"
                                            :class="
                                                day.closed
                                                    ? 'bg-slate-200'
                                                    : 'bg-[#7c3aed]'
                                            "
                                            @click="toggleDay(day)"
                                        >
                                            <span
                                                class="absolute top-1.5 h-8 w-8 rounded-full bg-white shadow-md transition"
                                                :class="
                                                    day.closed
                                                        ? 'left-1.5'
                                                        : 'left-[1.625rem]'
                                                "
                                            />
                                        </button>
                                    </div>
                                    <div
                                        v-if="!day.closed"
                                        class="mt-4 grid grid-cols-2 gap-3"
                                    >
                                        <div>
                                            <label
                                                :for="`opens-${day.weekday}`"
                                                class="text-xs font-semibold uppercase tracking-wider text-slate-500"
                                                >Abre</label
                                            >
                                            <input
                                                :id="`opens-${day.weekday}`"
                                                v-model="day.opensAt"
                                                type="time"
                                                required
                                                class="mt-1.5 min-h-11 w-full rounded-xl border-[#ded6e6] bg-[#fcfbfd] text-sm focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                            />
                                        </div>
                                        <div>
                                            <label
                                                :for="`closes-${day.weekday}`"
                                                class="text-xs font-semibold uppercase tracking-wider text-slate-500"
                                                >Cierra</label
                                            >
                                            <input
                                                :id="`closes-${day.weekday}`"
                                                v-model="day.closesAt"
                                                type="time"
                                                required
                                                class="mt-1.5 min-h-11 w-full rounded-xl border-[#ded6e6] bg-[#fcfbfd] text-sm focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                            />
                                        </div>
                                        <p
                                            v-if="dayError(index)"
                                            class="col-span-2 text-sm text-rose-600"
                                        >
                                            {{ dayError(index) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-if="fieldError('weeklyHours')"
                                class="mt-2 text-sm text-rose-600"
                            >
                                {{ fieldError("weeklyHours") }}
                            </p>
                        </fieldset>
                    </div>

                    <div
                        class="sticky bottom-0 flex flex-col gap-3 border-t border-[#eee7f4] bg-white/95 px-5 py-4 backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:px-7"
                    >
                        <p
                            v-if="scheduleError"
                            class="text-sm font-medium text-rose-600"
                            role="alert"
                        >
                            {{ scheduleError }}
                        </p>
                        <span v-else class="text-xs leading-5 text-slate-400"
                            >Los cambios aplican a nuevas reservas.</span
                        >
                        <button
                            type="submit"
                            :disabled="savingSchedule"
                            class="min-h-11 rounded-xl bg-[#7c3aed] px-6 py-3 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(124,58,237,0.25)] transition hover:bg-[#6d28d9] disabled:cursor-wait disabled:opacity-60 sm:ml-auto"
                        >
                            {{
                                savingSchedule
                                    ? "Guardando..."
                                    : "Guardar disponibilidad"
                            }}
                        </button>
                    </div>
                </form>

                <section
                    class="overflow-hidden rounded-[2rem] border border-[#e8def4] bg-[#1b1425] text-white shadow-[0_22px_55px_rgba(31,22,48,0.18)]"
                >
                    <div
                        class="bg-[radial-gradient(circle_at_top_right,rgba(219,39,119,0.22),transparent_42%)] p-5 sm:p-6"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-[0.24em] text-pink-300"
                        >
                            Excepciones
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight">
                            Bloqueos de agenda
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-white/60">
                            Reserva períodos para festivos, eventos o pausas del
                            equipo.
                        </p>
                        <button
                            type="button"
                            class="mt-5 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-[#5b21b6] shadow-[0_10px_28px_rgba(0,0,0,0.18)] transition hover:bg-[#faf5ff]"
                            @click="openBlockSheet"
                        >
                            <span class="text-lg leading-none">+</span> Agregar
                            bloqueo
                        </button>
                    </div>
                    <div
                        class="border-t border-white/10 bg-white/[0.04] p-4 sm:p-5"
                    >
                        <div
                            v-if="blocks.length === 0"
                            class="rounded-2xl border border-dashed border-white/15 px-4 py-10 text-center"
                        >
                            <p class="text-sm font-semibold text-white/80">
                                Sin bloqueos programados
                            </p>
                            <p class="mt-1 text-xs leading-5 text-white/45">
                                La agenda sigue el horario semanal sin
                                excepciones.
                            </p>
                        </div>
                        <div v-else class="space-y-3">
                            <article
                                v-for="block in blocks"
                                :key="block.id"
                                class="rounded-2xl border border-white/10 bg-white/[0.07] p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm font-semibold capitalize text-white"
                                        >
                                            {{
                                                formatBlockDate(
                                                    block.startsAtLocal,
                                                )
                                            }}
                                        </p>
                                        <p class="mt-1 text-xs text-white/50">
                                            hasta
                                            {{
                                                formatBlockDate(
                                                    block.endsAtLocal,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-rose-300 transition hover:bg-rose-500/15 hover:text-rose-200"
                                        aria-label="Eliminar bloqueo"
                                        @click="confirmDelete(block)"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.9"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <p
                                    v-if="block.reason"
                                    class="mt-3 border-t border-white/10 pt-3 text-sm leading-5 text-white/65"
                                >
                                    {{ block.reason }}
                                </p>
                                <p
                                    v-else
                                    class="mt-3 border-t border-white/10 pt-3 text-xs italic text-white/35"
                                >
                                    Sin motivo registrado
                                </p>
                            </article>
                        </div>
                    </div>
                </section>
            </div>
        </BasePage>

        <Teleport to="body">
            <transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="blockSheetOpen"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-[#120f19]/70 backdrop-blur-sm sm:items-center sm:p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="block-form-title"
                    @mousedown.self="closeBlockSheet"
                >
                    <form
                        class="max-h-[94vh] w-full overflow-y-auto rounded-t-[2rem] bg-white shadow-[0_30px_100px_rgba(15,23,42,0.4)] sm:max-w-lg sm:rounded-[2rem]"
                        @submit.prevent="createBlock"
                    >
                        <div
                            class="sticky top-0 z-10 flex items-start justify-between border-b border-[#eee7f4] bg-white/95 px-5 py-5 backdrop-blur sm:px-7"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold uppercase tracking-[0.24em] text-[#db2777]"
                                >
                                    Pausa excepcional
                                </p>
                                <h2
                                    id="block-form-title"
                                    class="mt-2 text-2xl font-semibold text-slate-950"
                                >
                                    Bloquear la agenda
                                </h2>
                            </div>
                            <button
                                type="button"
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-500 transition hover:bg-slate-200"
                                aria-label="Cerrar formulario"
                                @click="closeBlockSheet"
                            >
                                ×
                            </button>
                        </div>
                        <div class="space-y-5 p-5 sm:p-7">
                            <p
                                class="rounded-xl bg-[#f5f0fb] p-3 text-xs leading-5 text-[#6b4c82]"
                            >
                                Las fechas se interpretan en
                                <strong>{{ schedule.timezone }}</strong
                                >.
                            </p>
                            <p
                                v-if="blockError"
                                class="rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-700"
                                role="alert"
                            >
                                {{ blockError }}
                            </p>
                            <div>
                                <label
                                    for="block-start"
                                    class="text-sm font-semibold text-slate-700"
                                    >Inicio</label
                                >
                                <input
                                    id="block-start"
                                    ref="blockStartInput"
                                    v-model="blockForm.startsAtLocal"
                                    type="datetime-local"
                                    required
                                    class="mt-2 min-h-12 w-full rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                />
                                <p
                                    v-if="blockErrors.startsAtLocal"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ blockErrors.startsAtLocal[0] }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="block-end"
                                    class="text-sm font-semibold text-slate-700"
                                    >Fin</label
                                >
                                <input
                                    id="block-end"
                                    v-model="blockForm.endsAtLocal"
                                    type="datetime-local"
                                    required
                                    class="mt-2 min-h-12 w-full rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                />
                                <p
                                    v-if="blockErrors.endsAtLocal"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ blockErrors.endsAtLocal[0] }}
                                </p>
                            </div>
                            <div>
                                <label
                                    for="block-reason"
                                    class="text-sm font-semibold text-slate-700"
                                    >Motivo
                                    <span class="font-normal text-slate-400"
                                        >(opcional)</span
                                    ></label
                                >
                                <textarea
                                    id="block-reason"
                                    v-model="blockForm.reason"
                                    maxlength="1000"
                                    rows="3"
                                    class="mt-2 w-full resize-none rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                                    placeholder="Ej. Festivo, capacitación o mantenimiento"
                                />
                                <p
                                    v-if="blockErrors.reason"
                                    class="mt-1.5 text-sm text-rose-600"
                                >
                                    {{ blockErrors.reason[0] }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="sticky bottom-0 flex gap-3 border-t border-[#eee7f4] bg-white/95 px-5 py-4 backdrop-blur sm:justify-end sm:px-7"
                        >
                            <button
                                type="button"
                                :disabled="savingBlock"
                                class="min-h-11 flex-1 rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 disabled:opacity-50 sm:flex-none"
                                @click="closeBlockSheet"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="savingBlock"
                                class="min-h-11 flex-1 rounded-xl bg-[#7c3aed] px-6 py-3 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(124,58,237,0.25)] transition hover:bg-[#6d28d9] disabled:cursor-wait disabled:opacity-60 sm:flex-none"
                            >
                                {{
                                    savingBlock ? "Creando..." : "Crear bloqueo"
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </transition>

            <transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="deleteCandidate"
                    class="fixed inset-0 z-[60] flex items-end justify-center bg-[#120f19]/70 backdrop-blur-sm sm:items-center sm:p-6"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-block-title"
                    @mousedown.self="closeDeleteConfirmation"
                >
                    <div
                        class="w-full rounded-t-[2rem] bg-white p-6 shadow-[0_30px_100px_rgba(15,23,42,0.4)] sm:max-w-md sm:rounded-[2rem] sm:p-7"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 ring-1 ring-rose-100"
                        >
                            <svg
                                class="h-7 w-7"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.9"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 7h16M9 7V4h6v3m-9 0 1 13h10l1-13M10 11v5m4-5v5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                        <h2
                            id="delete-block-title"
                            class="mt-5 text-2xl font-semibold tracking-tight text-slate-950"
                        >
                            Eliminar bloqueo
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            La agenda volverá a estar disponible durante este
                            período según el horario semanal.
                        </p>
                        <p
                            v-if="deleteError"
                            class="mt-4 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-700"
                            role="alert"
                        >
                            {{ deleteError }}
                        </p>
                        <div
                            class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                        >
                            <button
                                type="button"
                                :disabled="deletingBlock"
                                class="min-h-11 rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 disabled:opacity-50"
                                @click="closeDeleteConfirmation"
                            >
                                Conservar bloqueo
                            </button>
                            <button
                                type="button"
                                :disabled="deletingBlock"
                                class="min-h-11 rounded-xl bg-rose-600 px-5 py-3 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(225,29,72,0.24)] transition hover:bg-rose-700 disabled:cursor-wait disabled:opacity-60"
                                @click="deleteBlock"
                            >
                                {{
                                    deletingBlock
                                        ? "Eliminando..."
                                        : "Eliminar bloqueo"
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>
    </AdminLayout>
</template>
