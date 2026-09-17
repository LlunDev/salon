<script setup>
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({ salon: { type: Object, required: true } });

const form = useForm({
    name: '',
    phone: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <Head :title="`Crea tu cuenta en ${salon.name}`" />
    <div class="auth-shell min-h-screen px-5 py-8 text-slate-950 sm:grid sm:place-items-center sm:py-12">
        <main class="mx-auto w-full max-w-lg overflow-hidden rounded-[2rem] bg-white shadow-[0_28px_80px_rgba(36,18,56,0.2)]">
            <header class="auth-header px-7 py-8 text-white">
                <Link href="/reservar/fecha" class="text-sm font-semibold text-violet-100 hover:text-white">← Volver al horario</Link>
                <p class="mt-8 text-xs font-semibold uppercase tracking-[0.22em] text-fuchsia-300">{{ salon.name }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">Crea tu cuenta</h1>
                <p class="mt-2 text-sm leading-6 text-violet-100">Tus datos se guardarán únicamente para este salón y sus reservas.</p>
            </header>

            <form class="grid gap-5 p-7 sm:grid-cols-2" @submit.prevent="submit">
                <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Nombre completo<input v-model="form.name" type="text" required autofocus autocomplete="name" class="field" /><InputError class="mt-2" :message="form.errors.name" /></label>
                <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Teléfono<input v-model="form.phone" type="tel" required autocomplete="tel" class="field" /><InputError class="mt-2" :message="form.errors.phone" /></label>
                <label class="block text-sm font-semibold text-slate-700 sm:col-span-2">Correo electrónico<input v-model="form.email" type="email" required autocomplete="username" class="field" /><InputError class="mt-2" :message="form.errors.email" /></label>
                <label class="block text-sm font-semibold text-slate-700">Contraseña<input v-model="form.password" type="password" required autocomplete="new-password" class="field" /><InputError class="mt-2" :message="form.errors.password" /></label>
                <label class="block text-sm font-semibold text-slate-700">Confirma la contraseña<input v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="field" /><InputError class="mt-2" :message="form.errors.password_confirmation" /></label>
                <button type="submit" :disabled="form.processing" class="auth-button rounded-xl px-5 py-3.5 text-sm font-semibold text-white disabled:opacity-50 sm:col-span-2">Crear cuenta y continuar</button>
                <p class="text-center text-sm text-slate-500 sm:col-span-2">¿Ya tienes una cuenta? <Link :href="route('login')" class="font-semibold text-violet-700 hover:text-violet-900">Inicia sesión</Link></p>
            </form>
        </main>
    </div>
</template>

<style scoped>
.auth-shell { background: linear-gradient(145deg, #f6eefb 0%, #fdf8fc 45%, #eee8f8 100%); }
.auth-header { background: #241238; }
.field { margin-top: 0.5rem; display: block; width: 100%; border-radius: 0.75rem; border-color: #ede9fe; background: rgba(245, 243, 255, 0.5); padding: 0.75rem 1rem; }
.field:focus { border-color: #8b5cf6; --tw-ring-color: #8b5cf6; }
.auth-button { background: #7c3aed; }
.auth-button:hover { background: #6d28d9; }
</style>
