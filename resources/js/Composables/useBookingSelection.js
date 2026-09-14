import { computed, ref, watch } from 'vue';

export function useBookingSelection(salonId) {
    const storageKey = `salon:${salonId}:booking-services`;
    const selectedIds = ref(readSelection(storageKey));

    watch(selectedIds, (ids) => {
        localStorage.setItem(storageKey, JSON.stringify(ids));
    }, { deep: true });

    function toggle(serviceId) {
        selectedIds.value = selectedIds.value.includes(serviceId)
            ? selectedIds.value.filter((id) => id !== serviceId)
            : [...selectedIds.value, serviceId];
    }

    function reconcile(services) {
        const availableIds = new Set(services.map((service) => service.id));
        selectedIds.value = selectedIds.value.filter((id) => availableIds.has(id));
    }

    return {
        selectedIds,
        selectedCount: computed(() => selectedIds.value.length),
        toggle,
        reconcile,
    };
}

function readSelection(storageKey) {
    try {
        const value = JSON.parse(localStorage.getItem(storageKey) ?? '[]');

        return Array.isArray(value) ? value.filter((id) => typeof id === 'string') : [];
    } catch {
        return [];
    }
}
