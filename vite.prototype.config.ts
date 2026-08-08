// PROTOTYPE HARNESS CONFIG (#330) — throwaway. Serves prototype-harness/ as a plain
// Vite app so the Schedule variants can be looked at with `pnpm prototype`, with no
// Laravel, no database and no login in the way.
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import { defineConfig } from 'vite';

export default defineConfig({
    root: path.resolve(__dirname, './prototype-harness'),
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    server: {
        port: 5199,
        // Bound to every interface so the phone view (point 5) can be checked on a
        // real phone over the LAN, not just in a narrow browser window.
        host: true,
    },
});
