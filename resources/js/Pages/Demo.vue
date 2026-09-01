<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    baseDomain: {
        type: String,
        required: true,
    },
});

const page = usePage();
const domainState = ref('idle');
const domainMessage = ref('Elige el dominio de tu salón.');

const form = useForm({
    salon_name: '',
    subdomain: '',
    owner_first_name: '',
    owner_last_name: '',
    owner_email: '',
    password: '',
    password_confirmation: '',
    terms_accepted: false,
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const fullDomain = computed(() => {
    if (!form.subdomain) {
        return `tu-salon.${props.baseDomain}`;
    }

    return `${form.subdomain}.${props.baseDomain}`;
});

const passwordState = computed(() => {
    if (!form.password && !form.password_confirmation) {
        return 'idle';
    }

    return form.password === form.password_confirmation ? 'match' : 'mismatch';
});

const passwordMessage = computed(() => {
    if (passwordState.value === 'match' && form.password_confirmation) {
        return 'Las contraseñas coinciden.';
    }

    if (passwordState.value === 'mismatch' && form.password_confirmation) {
        return 'Las contraseñas no coinciden.';
    }

    return 'Usa una contraseña segura para la cuenta principal del salón.';
});

const submitDisabled = computed(() => {
    return form.processing
        || domainState.value === 'checking'
        || domainState.value === 'unavailable'
        || passwordState.value === 'mismatch';
});

watch(
    () => form.subdomain,
    () => {
        domainState.value = 'idle';
        domainMessage.value = 'Elige el dominio de tu salón.';
    },
);

function normalizeSubdomainInput() {
    form.subdomain = form.subdomain
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-{2,}/g, '-')
        .replace(/^-+|-+$/g, '');
}

async function checkDomainAvailability() {
    normalizeSubdomainInput();

    if (!form.subdomain || form.subdomain.length < 3) {
        domainState.value = 'idle';
        domainMessage.value = 'Usa al menos 3 caracteres.';
        return;
    }

    domainState.value = 'checking';
    domainMessage.value = 'Verificando disponibilidad...';

    try {
        const response = await fetch(route('demo.domain-availability', { subdomain: form.subdomain }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json();

        if (!response.ok) {
            domainState.value = 'error';
            domainMessage.value = payload.message ?? 'No pudimos validar este dominio en este momento.';
            return;
        }

        domainState.value = payload.available ? 'available' : 'unavailable';
        domainMessage.value = payload.message;
    } catch {
        domainState.value = 'error';
        domainMessage.value = 'No pudimos validar este dominio en este momento.';
    }
}

function submit() {
    normalizeSubdomainInput();

    form.post(route('demo.provision'), {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Prueba gratis | Salon de Belleza" />

    <div class="min-h-screen bg-[#120f19] text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(244,114,182,0.22),_transparent_35%),radial-gradient(circle_at_bottom,_rgba(251,191,36,0.12),_transparent_25%)]" />

        <div class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 sm:px-6 lg:flex-row lg:items-center lg:gap-10 lg:px-8 lg:py-10">
            <section class="hidden flex-1 rounded-[2rem] border border-white/10 bg-white/5 p-6 shadow-[0_30px_80px_rgba(15,23,42,0.45)] backdrop-blur-xl sm:p-8 lg:block lg:p-10">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-rose-200/80">Salon de Belleza</p>
                        <h1 class="mt-4 max-w-md text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                            Lanza la experiencia premium de tu salón en minutos.
                        </h1>
                    </div>

                    <Link
                        href="/"
                        class="hidden rounded-full border border-white/15 px-4 py-2 text-sm text-white/80 transition hover:border-rose-200/60 hover:text-white lg:inline-flex"
                    >
                        Volver
                    </Link>
                </div>

                <p class="mt-6 max-w-xl text-sm leading-7 text-white/70 sm:text-base">
                    Crea el entorno de tu salón, reserva tu dominio y activa la cuenta principal desde una sola experiencia cuidada.
                </p>

                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl border border-white/10 bg-white/6 p-4">
                        <p class="text-xs uppercase tracking-[0.28em] text-white/45">Configuración</p>
                        <p class="mt-3 text-lg font-semibold text-white">Espacio + cuenta principal</p>
                    </div>
                    <div class="rounded-3xl border border-emerald-300/20 bg-emerald-300/10 p-4">
                        <p class="text-xs uppercase tracking-[0.28em] text-emerald-100/70">Dominio</p>
                        <p class="mt-3 text-lg font-semibold text-emerald-100">Dominio completo guardado</p>
                    </div>
                    <div class="rounded-3xl border border-amber-300/20 bg-amber-300/10 p-4">
                        <p class="text-xs uppercase tracking-[0.28em] text-amber-100/70">Mobile First</p>
                        <p class="mt-3 text-lg font-semibold text-amber-50">Alta premium desde móvil</p>
                    </div>
                </div>

                <div class="mt-8 rounded-[1.75rem] border border-white/10 bg-[#1a1526]/80 p-5 sm:p-6">
                    <p class="text-sm font-medium text-white/70">URL de tu salón</p>
                    <p class="mt-3 break-all text-2xl font-semibold text-white sm:text-3xl">{{ fullDomain }}</p>
                    <p
                        class="mt-3 text-sm"
                        :class="{
                            'text-white/60': domainState === 'idle' || domainState === 'checking',
                            'text-emerald-300': domainState === 'available',
                            'text-rose-300': domainState === 'unavailable' || domainState === 'error',
                        }"
                    >
                        {{ domainMessage }}
                    </p>
                </div>
            </section>

            <section class="w-full max-w-2xl rounded-[2rem] border border-white/10 bg-white p-5 text-slate-900 shadow-[0_30px_90px_rgba(15,23,42,0.3)] sm:p-8 lg:mt-0 lg:w-[46%]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.28em] text-rose-500">Salon de Belleza</p>
                        <h2 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Empieza tu prueba gratis</h2>
                        <p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Configura el espacio de tu salón y crea tu cuenta principal en un solo paso.</p>
                    </div>

                    <Link
                        href="/"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:border-slate-300 hover:text-slate-950 lg:hidden"
                    >
                        Volver
                    </Link>
                </div>

                <div
                    v-if="flashSuccess"
                    class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                >
                    {{ flashSuccess }}
                </div>

                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <div>
                        <label class="text-sm font-medium text-slate-700" for="salon_name">Nombre del salón</label>
                        <input
                            id="salon_name"
                            v-model="form.salon_name"
                            type="text"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm transition focus:border-rose-300 focus:ring-rose-200"
                            placeholder="Salon de Belleza Aurora"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.salon_name" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700" for="subdomain">Dominio del salón</label>
                        <div
                            class="mt-2 flex items-center overflow-hidden rounded-2xl border bg-slate-50 transition"
                            :class="{
                                'border-slate-200 focus-within:border-rose-300': domainState === 'idle' || domainState === 'checking',
                                'border-emerald-400 bg-emerald-50/70': domainState === 'available',
                                'border-rose-400 bg-rose-50/70': domainState === 'unavailable' || domainState === 'error',
                            }"
                        >
                            <input
                                id="subdomain"
                                v-model="form.subdomain"
                                type="text"
                                class="w-full border-0 bg-transparent px-4 py-3 text-sm text-slate-900 focus:ring-0"
                                placeholder="aurora"
                                autocapitalize="none"
                                spellcheck="false"
                                required
                                @input="normalizeSubdomainInput"
                                @blur="checkDomainAvailability"
                            />
                            <span class="border-l border-slate-200 px-4 text-sm font-medium text-slate-500">.{{ baseDomain }}</span>
                        </div>
                        <p
                            class="mt-2 text-sm font-medium"
                            :class="{
                                'text-slate-500': domainState === 'idle' || domainState === 'checking',
                                'text-emerald-600': domainState === 'available',
                                'text-rose-600': domainState === 'unavailable' || domainState === 'error',
                            }"
                        >
                            {{ domainMessage }}
                        </p>
                        <InputError class="mt-2" :message="form.errors.subdomain" />
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:gap-5">
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="owner_first_name">Nombre</label>
                            <input
                                id="owner_first_name"
                                v-model="form.owner_first_name"
                                type="text"
                                class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm transition focus:border-rose-300 focus:ring-rose-200"
                                placeholder="Ana"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.owner_first_name" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700" for="owner_last_name">Apellido</label>
                            <input
                                id="owner_last_name"
                                v-model="form.owner_last_name"
                                type="text"
                                class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm transition focus:border-rose-300 focus:ring-rose-200"
                                placeholder="López"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.owner_last_name" />
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700" for="owner_email">Correo principal</label>
                        <input
                            id="owner_email"
                            v-model="form.owner_email"
                            type="email"
                            class="mt-2 block w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm shadow-sm transition focus:border-rose-300 focus:ring-rose-200"
                            placeholder="owner@aurora.com"
                            autocomplete="email"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.owner_email" />
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:gap-5">
                        <div>
                            <label class="text-sm font-medium text-slate-700" for="password">Contraseña</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-2 block w-full rounded-2xl border px-4 py-3 text-sm shadow-sm transition focus:ring-0"
                                :class="{
                                    'border-slate-200 focus:border-rose-300': passwordState === 'idle',
                                    'border-emerald-400 bg-emerald-50/60 text-emerald-900': passwordState === 'match',
                                    'border-rose-400 bg-rose-50/60 text-rose-900': passwordState === 'mismatch',
                                }"
                                autocomplete="new-password"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700" for="password_confirmation">Confirmar</label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="mt-2 block w-full rounded-2xl border px-4 py-3 text-sm shadow-sm transition focus:ring-0"
                                :class="{
                                    'border-slate-200 focus:border-rose-300': passwordState === 'idle',
                                    'border-emerald-400 bg-emerald-50/60 text-emerald-900': passwordState === 'match',
                                    'border-rose-400 bg-rose-50/60 text-rose-900': passwordState === 'mismatch',
                                }"
                                autocomplete="new-password"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>
                    </div>

                    <p
                        class="text-sm font-medium"
                        :class="{
                            'text-slate-500': passwordState === 'idle',
                            'text-emerald-600': passwordState === 'match',
                            'text-rose-600': passwordState === 'mismatch',
                        }"
                    >
                        {{ passwordMessage }}
                    </p>

                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <Checkbox v-model:checked="form.terms_accepted" />
                        <span class="text-sm leading-6 text-slate-600">
                            Acepto los términos de la plataforma y confirmo que estoy creando la cuenta principal de este salón.
                        </span>
                    </label>
                    <InputError class="mt-2" :message="form.errors.terms_accepted" />

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center rounded-2xl bg-slate-950 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="submitDisabled"
                    >
                        <span v-if="form.processing">Configurando salón...</span>
                        <span v-else>Crear espacio del salón</span>
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>
