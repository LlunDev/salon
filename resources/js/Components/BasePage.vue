<script setup>
defineProps({
    eyebrow: {
        type: String,
        default: 'Panel de administracion',
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="space-y-6">
        <div class="rounded-[2rem] bg-gradient-to-r from-[#0f172a] via-[#1f1630] to-[#312e81] p-6 text-white shadow-[0_28px_80px_rgba(49,46,129,0.28)] sm:p-8">
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ title }}</h1>
            <p v-if="description" class="mt-3 max-w-2xl text-sm leading-7 text-white/72 sm:text-base">
                {{ description }}
            </p>
        </div>

        <slot />
    </section>
</template>
