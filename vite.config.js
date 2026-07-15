import { defineConfig } from 'vite';
import * as Vue from 'vue';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'node:path';

function statamicVueExternals() {
    const resolvedVirtualModuleId = '\0vue-external';
    const vueExports = Object.keys(Vue).filter((key) => key !== 'default' && /^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test(key));

    return {
        name: 'statamic-externals',
        enforce: 'pre',

        resolveId(id) {
            if (id === 'vue') {
                return resolvedVirtualModuleId;
            }

            return null;
        },

        load(id) {
            if (id !== resolvedVirtualModuleId) {
                return null;
            }

            return `
                const Vue = window.Vue;
                export default Vue;
                export const { ${vueExports.join(', ')} } = Vue;
            `;
        },
    };
}

export default defineConfig({
    plugins: [
        statamicVueExternals(),
        vue(),
    ],
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: {
                cp: resolve(__dirname, 'resources/js/cp.js'),
            },
        },
    },
});
