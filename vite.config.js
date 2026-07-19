import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import process from 'node:process'

export default defineConfig({
  base: process.env.VITE_BASE_PATH || '/',
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/calendar-proxy.php': {
        target: 'https://nx92511.your-storageshare.de',
        changeOrigin: true,
        rewrite: () => '/remote.php/dav/public-calendars/5JpmMnqbqQrMCyYL?export',
      },
      '/news-feed.php': {
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
