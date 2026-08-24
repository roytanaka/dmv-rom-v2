// PROTOTYPE HARNESS CONFIG (#405) — throwaway. Serves prototype-harness/ as a plain
// Vite app so the after-shift entry variants can be looked at with `pnpm prototype`,
// with no Laravel, no database and no login in the way. Same shape as the config the
// Schedule-views prototype used for #330.
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
        // Bound to every interface so the phone case can be checked on a real phone over
        // the LAN, not just in a narrow browser window. #405 makes mobile load-bearing:
        // a volunteer signs out on their own device.
        host: true,
    },
});
