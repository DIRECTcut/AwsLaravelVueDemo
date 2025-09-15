import { config } from '@vue/test-utils';
import { vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    usePage: vi.fn(() => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    name: 'Test User',
                    email: 'test@example.com',
                },
            },
            errors: {},
        },
    })),
    useForm: vi.fn((data) => ({
        ...data,
        processing: false,
        errors: {},
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
    })),
    router: {
        visit: vi.fn(),
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('vue-toast-notification', () => ({
    useToast: vi.fn(() => ({
        success: vi.fn(),
        error: vi.fn(),
        info: vi.fn(),
        warning: vi.fn(),
    })),
}));

Object.defineProperty(window, 'Echo', {
    value: {
        channel: vi.fn(() => ({
            listen: vi.fn(),
            stopListening: vi.fn(),
        })),
        private: vi.fn(() => ({
            listen: vi.fn(),
            stopListening: vi.fn(),
        })),
    },
    writable: true,
});

global.fetch = vi.fn();

config.global.stubs = {
    teleport: true,
};
