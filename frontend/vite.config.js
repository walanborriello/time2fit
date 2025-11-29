import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    host: '0.0.0.0',
    allowedHosts: ['time2fit.local', 'localhost'],
    // Proxy non più necessario: Nginx gestisce tutto
    force: true, // Forza il reload
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
  },
  clearScreen: false,
});

