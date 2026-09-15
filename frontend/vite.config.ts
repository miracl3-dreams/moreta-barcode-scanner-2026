import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vite'
import type { Connect, PreviewServer, ViteDevServer, PluginOption } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import basicSsl from '@vitejs/plugin-basic-ssl'

// Keep in sync with app.config.ts
const APP_BASE = '/barcode_scanner'
const API_TARGET = 'http://127.0.0.1:8003'
const USE_HTTPS = process.env.VITE_HTTPS === '1'
const zxingBrowser = fileURLToPath(new URL('./node_modules/@zxing/browser/esm/index.js', import.meta.url))
const zxingLibrary = fileURLToPath(new URL('./node_modules/@zxing/library/esm/index.js', import.meta.url))

function rewriteRootToAppBase() {
  const rewrite: Connect.NextHandleFunction = (req, res, next) => {
    const [pathname, query] = (req.url ?? '').split('?')
    const suffix = query ? `?${query}` : ''

    if (pathname === '/' || pathname === '') {
      res.writeHead(302, { Location: `${APP_BASE}/${suffix}` })
      res.end()
      return
    }

    if (pathname === '/webmoreta' || pathname.startsWith('/webmoreta/')) {
      const rest = pathname.slice('/webmoreta'.length)
      res.writeHead(302, { Location: `${APP_BASE}${rest || '/'}${suffix}` })
      res.end()
      return
    }

    if (pathname === `${APP_BASE}/login.php`) {
      res.writeHead(302, { Location: `${APP_BASE}/login${suffix}` })
      res.end()
      return
    }

    if (pathname === `${APP_BASE}/view_equip_interchange_receipt.php`) {
      res.writeHead(302, { Location: `${APP_BASE}/data-entry/eir-signing${suffix}` })
      res.end()
      return
    }

    if (
      !pathname.startsWith(`${APP_BASE}/`) &&
      pathname !== APP_BASE &&
      !pathname.startsWith('/api') &&
      !pathname.startsWith('/sanctum') &&
      !pathname.startsWith('/@') &&
      !pathname.includes('.')
    ) {
      res.writeHead(302, { Location: `${APP_BASE}${pathname}${suffix}` })
      res.end()
      return
    }

    next()
  }

  function prepend(server: ViteDevServer | PreviewServer) {
    server.middlewares.stack.unshift({ route: '', handle: rewrite })
  }

  return {
    name: 'rewrite-root-to-app-base',
    configureServer(server: ViteDevServer) {
      return () => prepend(server)
    },
    configurePreviewServer(server: PreviewServer) {
      return () => prepend(server)
    },
  }
}

const plugins: PluginOption[] = [rewriteRootToAppBase(), react(), tailwindcss()]
if (USE_HTTPS) {
  plugins.unshift(basicSsl())
}

const proxy = {
  '/api': {
    target: API_TARGET,
    changeOrigin: false,
    timeout: 600000,
  },
  '/sanctum': {
    target: API_TARGET,
    changeOrigin: false,
  },
}

// Vite always accepts localhost and bare IP hosts. Cloudflare quick tunnels
// change hostname every run, so allow the trycloudflare suffix. Extra named
// hosts (ngrok, etc.) still go through VITE_ALLOWED_HOSTS.
const allowedHosts = [
  '.trycloudflare.com',
  ...(process.env.VITE_ALLOWED_HOSTS ?? '')
    .split(',')
    .map((host) => host.trim())
    .filter(Boolean),
]

export default defineConfig({
  appType: 'spa',
  base: `${APP_BASE}/`,
  plugins,
  resolve: {
    alias: {
      '@zxing/browser': zxingBrowser,
      '@zxing/library': zxingLibrary,
    },
    dedupe: ['@zxing/library'],
  },
  optimizeDeps: {
    exclude: ['@zxing/browser', '@zxing/library'],
  },
  server: {
    host: true,
    port: 5175,
    allowedHosts,
    proxy,
  },
  preview: {
    host: true,
    port: 5175,
    allowedHosts,
    proxy,
  },
})
