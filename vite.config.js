import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import process from 'node:process'

export default defineConfig({
  base: process.env.VITE_BASE_PATH || '/',
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/news-feed.php': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/termine-feed.php': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/termine-attachment.php': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/beitritt-formular.php': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/beitritt-absenden.php': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
      '/uploads/news': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
