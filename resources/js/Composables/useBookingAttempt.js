import { ref } from 'vue';

export function useBookingAttempt(salonId) {
    const storageKey = `salon:${salonId}:booking-attempt`;
    const bookingKey = ref(localStorage.getItem(storageKey) || createBookingKey());

    localStorage.setItem(storageKey, bookingKey.value);

    function clear() {
        localStorage.removeItem(storageKey);
        bookingKey.value = '';
    }

    return { bookingKey, clear };
}

function createBookingKey() {
    return crypto.randomUUID();
}
