import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

// React 19 + React Compiler (principe : composants optimisés à la compilation).
export default defineConfig({
  plugins: [
    react({
      babel: {
        plugins: [['babel-plugin-react-compiler', {}]],
      },
    }),
  ],
  server: {
    port: 5173,
    // Proxy de l'API en dev : /api → Laravel (évite les soucis CORS/token).
    proxy: {
      '/api': {
        target: process.env.VITE_API_URL ?? 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
});
