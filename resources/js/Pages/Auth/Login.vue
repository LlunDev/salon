<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    salon: { type: Object, required: true },
    status: { type: String, default: '' },
});

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head :title="`Accede a ${salon.name}`" />
    <div class="auth-shell min-h-screen px-5 py-8 text-slate-950 sm:grid sm:place-items-center sm:py-12">
        <main class="mx-auto w-full max-w-md overflow-hidden rounded-[2rem] bg-white shadow-[0_28px_80px_rgba(36,18,56,0.2)]">
            <header class="auth-header px-7 py-8 text-white">
                <Link href="/reservar" class="text-sm font-semibold text-violet-100 hover:text-white">← Volver a la reserva</Link>
                <p class="mt-8 text-xs font-semibold uppercase tracking-[0.22em] text-fuchsia-300">{{ salon.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Inicia sesión</h1>
                <p class="mt-2 text-sm leading-6 text-violet-100">Usa la cuenta que tienes registrada en este salón.</p>
            </header>

            <form class="space-y-5 p-7" @submit.prevent="submit">
                <p v-if="status" class="rounded-xl bg-emerald-50 p-3 text-sm font-medium text-emerald-700">{{ status }}</p>

                <label class="block text-sm font-semibold text-slate-700">
                    Correo electrónico
                    <input v-model="form.email" type="email" required autofocus autocomplete="username" class="mt-2 block w-full rounded-xl border-violet-100 bg-violet-50/50 px-4 py-3 focus:border-violet-500 focus:ring-violet-500" />
                    <InputError class="mt-2" :message="form.errors.email" />
                </label>

                <label class="block text-sm font-semibold text-slate-700">
                    Contraseña
                    <input v-model="form.password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-xl border-violet-100 bg-violet-50/50 px-4 py-3 focus:border-violet-500 focus:ring-violet-500" />
                    <InputError class="mt-2" :message="form.errors.password" />
                </label>

                <label class="flex items-center gap-3 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-violet-200 text-violet-600 focus:ring-violet-500" />
                    Mantener la sesión iniciada
                </label>

                <button type="submit" :disabled="form.processing" class="auth-button w-full rounded-xl px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-50">Entrar</button>
                <p class="text-center text-sm text-slate-500">¿Primera vez aquí? <Link :href="route('register')" class="font-semibold text-violet-700 hover:text-violet-900">Crea tu cuenta</Link></p>
            </form>
        </main>
    </div>
</template>

<style scoped>
.auth-shell { background: linear-gradient(145deg, #f6eefb 0%, #fdf8fc 45%, #eee8f8 100%); }
.auth-header { background: #241238; }
.auth-button { background: #7c3aed; }
.auth-button:hover { background: #6d28d9; }
</style>
