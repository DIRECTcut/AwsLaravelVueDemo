import { useToast as useVueToast } from 'vue-toast-notification';
import 'vue-toast-notification/dist/theme-sugar.css';

export function useAppToast() {
    const toast = useVueToast();

    const showSuccess = (message: string, options?: any) => {
        toast.success(message, {
            duration: 4000,
            position: 'top-right',
            ...options,
        });
    };

    const showError = (message: string, options?: any) => {
        toast.error(message, {
            duration: 6000,
            position: 'top-right',
            ...options,
        });
    };

    const showInfo = (message: string, options?: any) => {
        toast.info(message, {
            duration: 4000,
            position: 'top-right',
            ...options,
        });
    };

    const showWarning = (message: string, options?: any) => {
        toast.warning(message, {
            duration: 5000,
            position: 'top-right',
            ...options,
        });
    };

    return {
        showSuccess,
        showError,
        showInfo,
        showWarning,
    };
}
