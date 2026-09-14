import { ref, watch } from 'vue';

export function useBookingSlotSelection(salonId, initialDate) {
    const storageKey = `salon:${salonId}:booking-slot`;
    const stored = readSlot(storageKey);
    const selectedDate = ref(stored.date ?? initialDate);
    const selectedStartsAt = ref(stored.startsAt ?? '');

    watch([selectedDate, selectedStartsAt], ([date, startsAt]) => {
        localStorage.setItem(storageKey, JSON.stringify({ date, startsAt }));
    });

    function selectDate(date) {
        if (selectedDate.value === date) return;
        selectedDate.value = date;
        selectedStartsAt.value = '';
    }

    function reconcile(slots) {
        if (selectedStartsAt.value && !slots.some((slot) => slot.startsAt === selectedStartsAt.value)) {
            selectedStartsAt.value = '';
        }
    }

    return { selectedDate, selectedStartsAt, selectDate, reconcile };
}

function readSlot(storageKey) {
    try {
        const value = JSON.parse(localStorage.getItem(storageKey) ?? '{}');

        return value && typeof value === 'object' ? value : {};
    } catch {
        return {};
    }
}
