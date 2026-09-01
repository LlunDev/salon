<script setup>
import BasePage from '@/Components/BasePage.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    salon: {
        type: Object,
        required: true,
    },
    pageTitle: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AdminLayout :title="pageTitle" :salon="salon">
        <BasePage :title="pageTitle" description="Vista inicial para validar navegacion y estructura del panel.">
            <div class="rounded-[1.75rem] border border-[#e8def4] bg-white p-8 shadow-[0_18px_45px_rgba(15,23,42,0.06)]">
                <h2 class="text-2xl font-semibold text-slate-950">{{ pageTitle }}</h2>
            </div>
        </BasePage>
    </AdminLayout>
</template>
