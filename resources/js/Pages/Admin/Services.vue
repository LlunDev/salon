<script setup>
import BasePage from '@/Components/BasePage.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

defineProps({
    salon: { type: Object, required: true },
    pageTitle: { type: String, required: true },
});

const services = ref([]);
const pagination = ref(null);
const search = ref('');
const loading = ref(true);
const loadError = ref('');
const modalOpen = ref(false);
const saving = ref(false);
const formError = ref('');
const errors = ref({});
let searchTimer;
let requestController;

const form = reactive({
    name: '',
    description: '',
    duration: '',
    imageUrl: '',
    price: '',
    available: true,
});

const resultLabel = computed(() => {
    const total = pagination.value?.total ?? services.value.length;
    return `${total} ${total === 1 ? 'servicio' : 'servicios'}`;
});

function apiHeaders(includeJson = false) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (includeJson) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    return headers;
}

async function loadServices(page = 1) {
    requestController?.abort();
    const controller = new AbortController();
    requestController = controller;
    loading.value = true;
    loadError.value = '';

    const params = new URLSearchParams({ page: String(page) });
    if (search.value.trim()) params.set('search', search.value.trim());

    try {
        const response = await fetch(`/api/admin/services?${params}`, {
            headers: apiHeaders(),
            signal: controller.signal,
        });
        const payload = await response.json();

        if (!response.ok) throw new Error(payload.message);

        services.value = payload.data;
        pagination.value = payload.meta;
    } catch (error) {
        if (error.name !== 'AbortError') {
            loadError.value = 'No pudimos cargar los servicios. Inténtalo nuevamente.';
        }
    } finally {
        if (!controller.signal.aborted) loading.value = false;
    }
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadServices(), 350);
});

function openModal() {
    errors.value = {};
    formError.value = '';
    modalOpen.value = true;
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    if (saving.value) return;
    modalOpen.value = false;
    document.body.style.overflow = '';
}

function resetForm() {
    Object.assign(form, {
        name: '', description: '', duration: '', imageUrl: '', price: '', available: true,
    });
}

async function createService() {
    saving.value = true;
    errors.value = {};
    formError.value = '';

    try {
        const response = await fetch('/api/admin/services', {
            method: 'POST',
            headers: apiHeaders(true),
            body: JSON.stringify({
                name: form.name,
                description: form.description || null,
                duration: Number(form.duration),
                imageUrl: form.imageUrl || null,
                price: form.price,
                available: form.available,
            }),
        });
        const payload = await response.json();

        if (response.status === 422) {
            errors.value = payload.errors ?? {};
            return;
        }
        if (!response.ok) throw new Error(payload.message);

        resetForm();
        modalOpen.value = false;
        document.body.style.overflow = '';
        await loadServices();
    } catch {
        formError.value = 'No pudimos crear el servicio. Revisa la información e inténtalo nuevamente.';
    } finally {
        saving.value = false;
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

function onKeydown(event) {
    if (event.key === 'Escape' && modalOpen.value) closeModal();
}

onMounted(() => {
    loadServices();
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    requestController?.abort();
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <AdminLayout :title="pageTitle" :salon="salon">
        <BasePage
            eyebrow="Catálogo del salón"
            :title="pageTitle"
            description="Gestiona la experiencia, duración y precio de cada servicio que ofreces."
        >
            <section class="rounded-[2rem] border border-[#e8def4] bg-white/80 p-4 shadow-[0_18px_45px_rgba(15,23,42,0.06)] backdrop-blur sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="relative flex-1 lg:max-w-xl">
                        <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Buscar por nombre o descripción"
                            class="w-full rounded-2xl border-[#e5ddec] bg-[#faf8fc] py-3.5 pl-12 pr-4 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#8b5cf6] focus:ring-[#8b5cf6]"
                        >
                    </div>
                    <div class="flex items-center justify-between gap-4 lg:justify-end">
                        <span class="text-sm font-medium text-slate-500">{{ resultLabel }}</span>
                        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[#7c3aed] px-5 py-3.5 text-sm font-semibold text-white shadow-[0_12px_28px_rgba(124,58,237,0.28)] transition hover:-translate-y-0.5 hover:bg-[#6d28d9]" @click="openModal">
                            <span class="text-lg leading-none">+</span> Nuevo servicio
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="grid gap-5 md:grid-cols-2 xl:grid-cols-3" aria-label="Cargando servicios">
                <div v-for="index in 6" :key="index" class="h-[25rem] animate-pulse rounded-[1.75rem] border border-[#e8def4] bg-white shadow-sm">
                    <div class="h-44 rounded-t-[1.75rem] bg-slate-200" />
                    <div class="space-y-4 p-5"><div class="h-5 w-3/4 rounded bg-slate-200"/><div class="h-4 rounded bg-slate-100"/><div class="h-4 w-1/2 rounded bg-slate-100"/></div>
                </div>
            </div>

            <div v-else-if="loadError" class="rounded-[1.75rem] border border-rose-200 bg-rose-50 p-6 text-center text-sm font-medium text-rose-700">
                <p>{{ loadError }}</p>
                <button class="mt-4 rounded-xl bg-rose-100 px-4 py-2 font-semibold hover:bg-rose-200" type="button" @click="loadServices()">Reintentar</button>
            </div>

            <div v-else-if="services.length === 0" class="rounded-[2rem] border border-dashed border-[#d8cbe8] bg-white px-6 py-16 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#f3ecff] text-3xl text-[#7c3aed]">✦</div>
                <h2 class="mt-5 text-xl font-semibold text-slate-950">{{ search ? 'No encontramos coincidencias' : 'Tu catálogo está listo para comenzar' }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">{{ search ? 'Prueba con otro nombre o una palabra de la descripción.' : 'Crea el primer servicio y presenta tu oferta con toda la información que tus clientes necesitan.' }}</p>
            </div>

            <div v-else class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="service in services" :key="service.id" class="group overflow-hidden rounded-[1.75rem] border border-[#e8def4] bg-white shadow-[0_18px_45px_rgba(15,23,42,0.07)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_55px_rgba(76,29,149,0.13)]">
                    <div class="relative h-48 overflow-hidden bg-[linear-gradient(135deg,#ede9fe,#fce7f3)]">
                        <img v-if="service.imageUrl" :src="service.imageUrl" :alt="service.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        <div v-else class="flex h-full items-center justify-center">
                            <div class="flex h-20 w-20 items-center justify-center rounded-[1.75rem] border border-white/60 bg-white/55 text-4xl text-[#7c3aed] shadow-lg backdrop-blur">✦</div>
                        </div>
                        <span class="absolute left-4 top-4 rounded-full px-3 py-1.5 text-xs font-semibold shadow-sm backdrop-blur" :class="service.available ? 'bg-emerald-50/95 text-emerald-700' : 'bg-slate-900/80 text-white'">
                            {{ service.available ? 'Disponible' : 'No disponible' }}
                        </span>
                    </div>
                    <div class="flex min-h-[14rem] flex-col p-5">
                        <h2 class="text-xl font-semibold tracking-tight text-slate-950">{{ service.name }}</h2>
                        <p class="mt-2 line-clamp-3 text-sm leading-6 text-slate-500">{{ service.description || 'Sin descripción adicional.' }}</p>
                        <div class="mt-auto flex items-end justify-between gap-4 border-t border-slate-100 pt-5">
                            <div class="flex items-center gap-2 text-sm font-medium text-slate-500">
                                <svg class="h-5 w-5 text-[#8b5cf6]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                {{ formatDuration(service.duration) }}
                            </div>
                            <p class="text-xl font-semibold tracking-tight text-slate-950">{{ formatPrice(service.price) }}</p>
                        </div>
                    </div>
                </article>
            </div>

            <nav v-if="pagination?.last_page > 1" class="flex items-center justify-center gap-3" aria-label="Paginación de servicios">
                <button type="button" class="rounded-xl border border-[#ded3e9] bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" :disabled="pagination.current_page === 1" @click="loadServices(pagination.current_page - 1)">Anterior</button>
                <span class="text-sm font-medium text-slate-500">Página {{ pagination.current_page }} de {{ pagination.last_page }}</span>
                <button type="button" class="rounded-xl border border-[#ded3e9] bg-white px-4 py-2 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40" :disabled="pagination.current_page === pagination.last_page" @click="loadServices(pagination.current_page + 1)">Siguiente</button>
            </nav>
        </BasePage>

        <Teleport to="body">
            <transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
                <div v-if="modalOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-[#120f19]/65 p-0 backdrop-blur-sm sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="service-form-title" @mousedown.self="closeModal">
                    <form class="max-h-[92vh] w-full overflow-y-auto rounded-t-[2rem] bg-white shadow-[0_30px_100px_rgba(15,23,42,0.35)] sm:max-w-2xl sm:rounded-[2rem]" @submit.prevent="createService">
                        <div class="sticky top-0 z-10 flex items-start justify-between border-b border-[#eee7f4] bg-white/95 px-5 py-5 backdrop-blur sm:px-7">
                            <div><p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#db2777]">Nuevo servicio</p><h2 id="service-form-title" class="mt-2 text-2xl font-semibold text-slate-950">Amplía tu catálogo</h2></div>
                            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-500 transition hover:bg-slate-200 hover:text-slate-900" aria-label="Cerrar formulario" @click="closeModal">×</button>
                        </div>
                        <div class="space-y-5 p-5 sm:p-7">
                            <p v-if="formError" class="rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-700">{{ formError }}</p>
                            <div><label class="text-sm font-semibold text-slate-700" for="service-name">Nombre del servicio</label><input id="service-name" v-model="form.name" maxlength="120" required class="mt-2 w-full rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]" placeholder="Ej. Corte y peinado premium"><p v-if="errors.name" class="mt-1.5 text-sm text-rose-600">{{ errors.name[0] }}</p></div>
                            <div><label class="text-sm font-semibold text-slate-700" for="service-description">Descripción</label><textarea id="service-description" v-model="form.description" rows="3" class="mt-2 w-full resize-none rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]" placeholder="Describe la experiencia y lo que incluye el servicio"/><p v-if="errors.description" class="mt-1.5 text-sm text-rose-600">{{ errors.description[0] }}</p></div>
                            <div class="grid gap-5 sm:grid-cols-2">
                                <div><label class="text-sm font-semibold text-slate-700" for="service-duration">Duración en minutos</label><input id="service-duration" v-model="form.duration" type="number" min="1" max="1440" required class="mt-2 w-full rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]" placeholder="60"><p v-if="errors.duration" class="mt-1.5 text-sm text-rose-600">{{ errors.duration[0] }}</p></div>
                                <div><label class="text-sm font-semibold text-slate-700" for="service-price">Precio en pesos colombianos</label><div class="relative mt-2"><span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-semibold text-slate-500">$</span><input id="service-price" v-model="form.price" type="number" min="0" max="99999999.99" step="0.01" required class="w-full rounded-xl border-[#ded6e6] pl-8 focus:border-[#8b5cf6] focus:ring-[#8b5cf6]" placeholder="50000"></div><p v-if="errors.price" class="mt-1.5 text-sm text-rose-600">{{ errors.price[0] }}</p></div>
                            </div>
                            <div><label class="text-sm font-semibold text-slate-700" for="service-image">URL de la imagen <span class="font-normal text-slate-400">(opcional)</span></label><input id="service-image" v-model="form.imageUrl" type="url" maxlength="2048" class="mt-2 w-full rounded-xl border-[#ded6e6] focus:border-[#8b5cf6] focus:ring-[#8b5cf6]" placeholder="https://ejemplo.com/imagen.jpg"><p v-if="errors.imageUrl" class="mt-1.5 text-sm text-rose-600">{{ errors.imageUrl[0] }}</p></div>
                            <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl bg-[#f8f5fb] p-4"><span><span class="block text-sm font-semibold text-slate-800">Disponible para clientes</span><span class="mt-1 block text-xs leading-5 text-slate-500">Podrás cambiar esta visibilidad más adelante.</span></span><input v-model="form.available" type="checkbox" class="h-6 w-6 rounded-lg border-[#cfc1dd] text-[#7c3aed] focus:ring-[#8b5cf6]"></label>
                        </div>
                        <div class="sticky bottom-0 flex gap-3 border-t border-[#eee7f4] bg-white/95 px-5 py-4 backdrop-blur sm:justify-end sm:px-7">
                            <button type="button" class="flex-1 rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 sm:flex-none" @click="closeModal">Cancelar</button>
                            <button type="submit" :disabled="saving" class="flex-1 rounded-xl bg-[#7c3aed] px-6 py-3 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(124,58,237,0.25)] transition hover:bg-[#6d28d9] disabled:cursor-wait disabled:opacity-60 sm:flex-none">{{ saving ? 'Creando...' : 'Crear servicio' }}</button>
                        </div>
                    </form>
                </div>
            </transition>
        </Teleport>
    </AdminLayout>
</template>
