import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

// NOTE: leaflet is browser-only (needs `window`) and crashes SSR. Map components
// (LocationPicker.vue, OrdersMap.vue) import it as a TYPE only at module scope and
// lazy-load the runtime via `await import('leaflet')` inside onMounted — so it never
// executes during the Node SSR render. Keep it that way; do NOT add a static
// `import L from 'leaflet'` back to those components.
// Also do NOT disable code splitting for the SSR build (codeSplitting:false /
// inlineDynamicImports) — it would flatten the onMounted dynamic imports to eager
// static and re-introduce the "window is not defined" crash.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts', 'resources/js/miniapp/main.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
    // Rolldown (Vite 8) circular bog'liq vue-ekotizimini chunklarga bo'lganda
    // @vue/runtime-dom init funksiyasini bir chunkda yaratib, boshqasidan import
    // qilishni o'tkazib yuboradi -> "init_runtime_dom_esm_bundler is not defined"
    // (NONDETERMINISTIK: har build'da har xil chunk crash bo'ladi, butun SPA o'lik).
    // Yechim: butun reaktiv cluster (vue + @vue + reka-ui + @inertiajs + vueuse)
    // bitta `vendor` chunkga jamlanadi -> init wrapper hech qachon chunk chegarasini
    // kesib o'tmaydi. leaflet TASHQARIDA qoladi: u SSR'da window'siz lazy yuklanishi
    // shart (yuqoridagi NOTE), vendor'ga qo'shilsa eager bo'lib ketadi.
    resolve: {
        dedupe: ['vue', '@vue/runtime-dom', '@vue/runtime-core', '@vue/shared'],
    },
    // Dev (Vite 8 / rolldown optimizeDeps) yuqoridagi build'dagi manualChunks'ni
    // ishlatmaydi. U vue-i18n'ni alohida pre-bundle chunkga ajratadi va u shared
    // runtime-dom chunkdagi `init_runtime_dom_esm_bundler()` ni import qilmasdan
    // chaqiradi -> "init_runtime_dom_esm_bundler is not defined", butun SPA o'lik.
    // Yechim: vue-i18n'ni pre-bundle'dan chiqaramiz -> manba ESM sifatida beriladi,
    // 'vue' (deduped, yagona optimized chunk) ni import qiladi, init wrapper chunk
    // chegarasini kesib o'tmaydi.
    optimizeDeps: {
        exclude: ['vue-i18n'],
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks(id: string) {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }
                    // leaflet TASHQARIDA: SSR'da window'siz lazy yuklanishi shart.
                    if (/[\\/]node_modules[\\/](leaflet|@vue-leaflet)[\\/]/.test(id)) {
                        return undefined;
                    }
                    // Qolgan butun vendor (vue, @vue, reka-ui, @inertiajs, vue-i18n,
                    // @intlify, vueuse...) bitta chunkda -> rolldown init wrapper'lari
                    // chunk chegarasini kesib o'tmaydi.
                    return 'vendor';
                },
            },
        },
    },
});
