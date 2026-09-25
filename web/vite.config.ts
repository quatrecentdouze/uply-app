import { fileURLToPath, URL } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

const apiOrigin = 'http://127.0.0.1:8000';
const proxiedToApi = { target: apiOrigin, changeOrigin: false };

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    proxy: {
      '/api': proxiedToApi,
      '/sanctum': proxiedToApi,
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/shared/testing/setup.ts'],
    restoreMocks: true,
    unstubGlobals: true,
  },
});
