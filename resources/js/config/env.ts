import { z } from 'zod';

// Define the environment schema
const envSchema = z.object({
    // App configuration
    VITE_APP_NAME: z.string().min(1).default('Laravel'),

    // Broadcasting configuration
    VITE_BROADCAST_DRIVER: z.enum(['pusher', 'reverb', 'log', 'null']).default('log'),

    // Pusher configuration (optional - only required when using pusher)
    VITE_PUSHER_APP_KEY: z.string().optional(),
    VITE_PUSHER_APP_CLUSTER: z.string().default('mt1'),
    VITE_PUSHER_HOST: z.string().optional(),
    VITE_PUSHER_PORT: z.coerce.number().optional(),
    VITE_PUSHER_SCHEME: z.enum(['http', 'https']).default('https'),

    // Laravel Reverb configuration (optional - only required when using reverb)
    VITE_REVERB_APP_KEY: z.string().optional(),
    VITE_REVERB_HOST: z.string().default('localhost'),
    VITE_REVERB_PORT: z.coerce.number().default(8080),
    VITE_REVERB_SCHEME: z.enum(['http', 'https']).default('http'),
});

// Validate environment variables
export function validateEnvironment() {
    try {
        const env = envSchema.parse(import.meta.env);

        // Additional validation based on broadcast driver
        const broadcastDriver = env.VITE_BROADCAST_DRIVER;

        if (broadcastDriver === 'pusher' && !env.VITE_PUSHER_APP_KEY) {
            throw new Error('VITE_PUSHER_APP_KEY is required when using Pusher broadcaster');
        }

        if (broadcastDriver === 'reverb' && !env.VITE_REVERB_APP_KEY) {
            throw new Error('VITE_REVERB_APP_KEY is required when using Reverb broadcaster');
        }

        console.info('✓ Environment variables validated successfully');
        return env;
    } catch (error) {
        if (error instanceof z.ZodError) {
            console.error('❌ Environment validation failed:');
            error.errors.forEach((err) => {
                console.error(`  - ${err.path.join('.')}: ${err.message}`);
            });
        } else {
            console.error('❌ Environment validation failed:', error.message);
        }

        // Return safe defaults for development
        return {
            VITE_APP_NAME: 'Laravel',
            VITE_BROADCAST_DRIVER: 'log' as const,
            VITE_PUSHER_APP_CLUSTER: 'mt1',
            VITE_PUSHER_SCHEME: 'https' as const,
            VITE_REVERB_HOST: 'localhost',
            VITE_REVERB_PORT: 8080,
            VITE_REVERB_SCHEME: 'http' as const,
        };
    }
}

// Validate and export environment
export const env = validateEnvironment();

// Type-safe environment object
export type AppEnvironment = ReturnType<typeof validateEnvironment>;
