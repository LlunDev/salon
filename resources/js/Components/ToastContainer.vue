<script setup>
import { useToast } from '@/Composables/useToast';
import { computed } from 'vue';

const { toasts, dismiss } = useToast();

const styles = {
    success: {
        title: 'Éxito',
        icon: 'text-emerald-600',
        iconBackground: 'bg-emerald-400/20 ring-emerald-500/25 shadow-[0_8px_24px_rgba(16,185,129,0.22)]',
        glow: 'bg-emerald-300/25',
    },
    error: {
        title: 'Error',
        icon: 'text-rose-600',
        iconBackground: 'bg-rose-400/20 ring-rose-500/25 shadow-[0_8px_24px_rgba(244,63,94,0.22)]',
        glow: 'bg-rose-300/25',
    },
    info: {
        title: 'Información',
        icon: 'text-blue-600',
        iconBackground: 'bg-blue-400/20 ring-blue-500/25 shadow-[0_8px_24px_rgba(59,130,246,0.22)]',
        glow: 'bg-blue-300/25',
    },
    warning: {
        title: 'Atención',
        icon: 'text-amber-500',
        iconBackground: 'bg-amber-300/25 ring-amber-500/25 shadow-[0_8px_24px_rgba(245,158,11,0.22)]',
        glow: 'bg-amber-300/25',
    },
};

const visibleToasts = computed(() => toasts.value.slice(-5));
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed left-1/2 top-0 z-[100] flex w-full max-w-[30rem] -translate-x-1/2 flex-col items-center gap-3 px-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-0 sm:pt-5"
            aria-live="polite"
            aria-atomic="true"
        >
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="-translate-y-5 scale-95 opacity-0"
                enter-to-class="translate-y-0 scale-100 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-y-0 scale-100 opacity-100"
                leave-to-class="-translate-y-3 scale-95 opacity-0"
                move-class="transition-transform duration-300 ease-out"
            >
                <article
                    v-for="item in visibleToasts"
                    :key="item.id"
                    class="pointer-events-auto relative isolate w-full overflow-hidden rounded-[1.75rem] border border-white/80 bg-[linear-gradient(135deg,rgba(255,255,255,0.78)_0%,rgba(250,245,255,0.62)_52%,rgba(238,242,255,0.55)_100%)] p-4 text-slate-900 shadow-[0_24px_70px_rgba(49,46,129,0.22),inset_0_1px_0_rgba(255,255,255,0.95)] ring-1 ring-violet-950/10 backdrop-blur-[28px] backdrop-saturate-[1.8] sm:p-5"
                    :role="item.type === 'error' ? 'alert' : 'status'"
                >
                    <div class="pointer-events-none absolute -right-10 -top-14 -z-10 h-32 w-32 rounded-full blur-3xl" :class="styles[item.type].glow" />
                    <div class="pointer-events-none absolute -bottom-16 left-1/4 -z-10 h-28 w-44 rounded-full bg-violet-300/20 blur-3xl" />
                    <div class="pointer-events-none absolute inset-x-5 top-0 h-px bg-gradient-to-r from-transparent via-white to-transparent" />

                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-[1.1rem] ring-1 backdrop-blur-md"
                            :class="[styles[item.type].icon, styles[item.type].iconBackground]"
                        >
                            <svg v-if="item.type === 'success'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else-if="item.type === 'error'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" stroke-linecap="round"/></svg>
                            <svg v-else-if="item.type === 'warning'" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 8v5m0 3.5v.01M10.3 4.2 3.1 17a2 2 0 0 0 1.7 3h14.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <svg v-else class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8v.01" stroke-linecap="round"/></svg>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold tracking-tight text-slate-950 sm:text-lg">{{ item.title || styles[item.type].title }}</p>
                            <p class="mt-1 text-[0.95rem] leading-6 text-slate-700 sm:text-base">{{ item.message }}</p>
                        </div>

                        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/60 bg-white/30 text-xl text-slate-500 shadow-sm backdrop-blur transition hover:bg-white/70 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-violet-400/60" aria-label="Cerrar notificación" @click="dismiss(item.id)">×</button>
                    </div>
                </article>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
