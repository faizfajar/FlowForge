import Echo from 'laravel-echo';
import { api, refreshAccessToken } from './lib/axios';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    activityTimeout: Number(import.meta.env.VITE_REVERB_ACTIVITY_TIMEOUT ?? 30000),
    pongTimeout: Number(import.meta.env.VITE_REVERB_PONG_TIMEOUT ?? 10000),
    unavailableTimeout: Number(import.meta.env.VITE_REVERB_UNAVAILABLE_TIMEOUT ?? 10000),
    authorizer: (channel) => ({
        authorize: async (socketId, callback) => {
            try {
                const response = await api.post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                });

                callback(false, response.data);
            } catch (error) {
                if (error.response?.status === 401) {
                    try {
                        await refreshAccessToken();

                        const response = await api.post('/broadcasting/auth', {
                            socket_id: socketId,
                            channel_name: channel.name,
                        });

                        callback(false, response.data);

                        return;
                    } catch (refreshError) {
                        callback(true, refreshError);

                        return;
                    }
                }

                callback(true, error);
            }
        },
    }),
});
