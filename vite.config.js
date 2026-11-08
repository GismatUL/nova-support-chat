import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Builds one IIFE that Nova loads with Nova::script(). Vue is NOT bundled:
// Nova 4 and 5 expose their own Vue 3 instance as window.Vue, and components must use that one.
export default defineConfig({
  plugins: [vue()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
  },
  build: {
    outDir: 'dist/js',
    emptyOutDir: true,
    lib: {
      entry: 'resources/js/tool.js',
      name: 'NovaSupportChat',
      formats: ['iife'],
      fileName: () => 'tool.js',
    },
    rollupOptions: {
      external: ['vue'],
      output: {
        globals: { vue: 'Vue' },
      },
    },
  },
})
