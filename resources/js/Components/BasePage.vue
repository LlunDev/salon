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
        <div class="">
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/60">{{ eyebrow }}</p>
            <h1 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl">{{ title }}</h1>
            <p v-if="description" class="mt-3 max-w-2xl text-sm leading-7 text-white/72 sm:text-base">
                {{ description }}
            </p>
        </div>

        <slot />
    </section>
</template>
