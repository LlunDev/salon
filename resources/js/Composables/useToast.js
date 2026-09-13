import { readonly, ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

function dismiss(id) {
    const toast = toasts.value.find((item) => item.id === id);
    if (!toast) return;

    window.clearTimeout(toast.timeoutId);
    toasts.value = toasts.value.filter((item) => item.id !== id);
}

function show(type, message, options = {}) {
    const id = ++nextId;
    const duration = options.duration ?? 4500;
    const toast = {
        id,
        type,
        message,
        title: options.title,
        timeoutId: null,
    };

    toasts.value.push(toast);

    if (duration > 0) {
        toast.timeoutId = window.setTimeout(() => dismiss(id), duration);
    }

    return id;
}

export const toast = {
    success: (message, options) => show('success', message, options),
    error: (message, options) => show('error', message, options),
    info: (message, options) => show('info', message, options),
    warning: (message, options) => show('warning', message, options),
    dismiss,
};

export function useToast() {
    return {
        toasts: readonly(toasts),
        ...toast,
    };
}
