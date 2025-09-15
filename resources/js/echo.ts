import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { env } from './config/env';

// Make Pusher available globally for Echo
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<any>;
    }
}

window.Pusher = Pusher;

// Create a mock Echo instance for development/testing
const createMockEcho = () => {
    console.info('🔧 Laravel Echo: Using mock implementation (broadcasting disabled)');
    return {
        private: () => ({
            listen: () => ({
                stopListening: () => {},
            }),
            stopListening: () => {},
        }),
        channel: () => ({
            listen: () => ({
                stopListening: () => {},
            }),
            stopListening: () => {},
        }),
        leave: () => {},
        disconnect: () => {},
    } as any;
};

// Create auth handler for private channels
const createAuthorizer = (channel: any) => ({
    authorize: (socketId: string, callback: (error: any, data?: any) => void) => {
        fetch('/broadcasting/auth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                socket_id: socketId,
                channel_name: channel.name,
            }),
        })
            .then((response) => response.json())
            .then((data) => callback(null, data))
            .catch((error) => callback(error));
    },
});

// Initialize Echo based on broadcast driver
let echoInstance: Echo<any>;

try {
    if (env.VITE_BROADCAST_DRIVER === 'log' || env.VITE_BROADCAST_DRIVER === 'null') {
        // Use mock for log/null drivers
        echoInstance = createMockEcho();
    } else if (env.VITE_BROADCAST_DRIVER === 'pusher') {
        // Configure Pusher
        if (!env.VITE_PUSHER_APP_KEY) {
            console.error('❌ Laravel Echo: VITE_PUSHER_APP_KEY is required for Pusher broadcaster');
            echoInstance = createMockEcho();
        } else {
            const echoConfig = {
                broadcaster: 'pusher' as const,
                key: env.VITE_PUSHER_APP_KEY,
                cluster: env.VITE_PUSHER_APP_CLUSTER,
                wsHost: env.VITE_PUSHER_HOST || `ws-${env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
                wsPort: env.VITE_PUSHER_PORT || 80,
                wssPort: env.VITE_PUSHER_PORT || 443,
                forceTLS: env.VITE_PUSHER_SCHEME === 'https',
                enabledTransports: ['ws', 'wss'],
                authorizer: (channel: any) => createAuthorizer(channel),
            };
            echoInstance = new Echo(echoConfig);
            console.info('✓ Laravel Echo: Initialized with Pusher broadcaster');
        }
    } else if (env.VITE_BROADCAST_DRIVER === 'reverb') {
        // Configure Laravel Reverb
        if (!env.VITE_REVERB_APP_KEY) {
            console.error('❌ Laravel Echo: VITE_REVERB_APP_KEY is required for Reverb broadcaster');
            echoInstance = createMockEcho();
        } else {
            const echoConfig = {
                broadcaster: 'reverb' as const,
                key: env.VITE_REVERB_APP_KEY,
                wsHost: env.VITE_REVERB_HOST,
                wsPort: env.VITE_REVERB_PORT,
                wssPort: env.VITE_REVERB_PORT,
                forceTLS: env.VITE_REVERB_SCHEME === 'https',
                enableLogging: import.meta.env.DEV,
                authorizer: (channel: any) => createAuthorizer(channel),
            };
            echoInstance = new Echo(echoConfig);
            console.info('✓ Laravel Echo: Initialized with Reverb broadcaster');
        }
    } else {
        console.error(`❌ Laravel Echo: Unknown broadcast driver '${env.VITE_BROADCAST_DRIVER}'`);
        echoInstance = createMockEcho();
    }
} catch (error) {
    console.error('❌ Laravel Echo: Failed to initialize broadcaster', error);
    echoInstance = createMockEcho();
}

// Set global Echo instance
window.Echo = echoInstance;

export default window.Echo;
