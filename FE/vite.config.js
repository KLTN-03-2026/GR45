import fs from 'node:fs'
import path from 'node:path'

import { fileURLToPath, URL } from 'node:url'

import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

function resolveAgentPackagesDir(feDir) {
  return path.join(feDir, 'agent-runtime-sdk', 'packages')
}

function feAgentSdkAliasesFromPackagesDir(packagesDir) {
  const aliases = {}
  if (!packagesDir || !fs.existsSync(packagesDir)) {
    return aliases
  }
  for (const dirName of fs.readdirSync(packagesDir)) {
    const pkgDir = path.join(packagesDir, dirName)
    if (!fs.statSync(pkgDir).isDirectory()) continue
    const pkgJsonPath = path.join(pkgDir, 'package.json')
    if (!fs.existsSync(pkgJsonPath)) continue
    try {
      const pkg = JSON.parse(fs.readFileSync(pkgJsonPath, 'utf8'))
      if (!pkg.name) continue
      const exportMap = pkg.exports?.constructor === Object ? pkg.exports : {}
      for (const [exportName, exportPath] of Object.entries(exportMap)) {
        if (exportName === '.') continue
        if (exportPath?.constructor !== String) continue
        const subpathEntry = path.join(pkgDir, exportPath)
        if (fs.existsSync(subpathEntry)) {
          aliases[`${pkg.name}${exportName.slice(1)}`] = subpathEntry
        }
      }
      const rootExport = exportMap['.']?.constructor === String
        ? path.join(pkgDir, exportMap['.'])
        : path.join(pkgDir, 'src', 'index.js')
      const entry = fs.existsSync(rootExport)
        ? rootExport
        : path.join(pkgDir, 'src', 'index.js')
      if (fs.existsSync(entry)) {
        aliases[pkg.name] = entry
      }
    } catch {
      /* skip */
    }
  }
  return aliases
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const feDir = path.dirname(fileURLToPath(import.meta.url))
  const sdkRoot = path.join(feDir, 'agent-runtime-sdk')
  const sdkRoots = fs.existsSync(sdkRoot) ? [sdkRoot] : []

  const env = loadEnv(mode, process.cwd(), '')
  const unified =
    env.DEV_UNIFIED_PORT === '1' ||
    env.DEV_UNIFIED_PORT === 'true'

  const apiProxyTarget =
    String(env.DEV_PROXY_API_TARGET ?? '').trim() ||
    (unified ? 'http://127.0.0.1:8000' : 'https://api.bussafe.io.vn')

  const reverbProxyTarget =
    String(env.DEV_PROXY_REVERB_TARGET ?? '').trim() ||
    (unified ? 'http://127.0.0.1:8080' : '')

  const ollamaProxyTarget =
    String(env.DEV_PROXY_OLLAMA_TARGET ?? '').trim() || 'http://127.0.0.1:11434'

  const proxy = {
    '/api': {
      target: apiProxyTarget,
      changeOrigin: true,
      secure: apiProxyTarget.startsWith('https'),
    },
  }

  if (reverbProxyTarget.length > 0) {
    proxy['/app'] = {
      target: reverbProxyTarget,
      changeOrigin: true,
      ws: true,
      secure: reverbProxyTarget.startsWith('https'),
    }
  }

  if (mode === 'development') {
    proxy['/ollama-local'] = {
      target: ollamaProxyTarget,
      changeOrigin: true,
      rewrite: (pathname) =>
        pathname.startsWith('/ollama-local')
          ? pathname.slice('/ollama-local'.length) || '/'
          : pathname,
    }
  }

  const packagesDir = resolveAgentPackagesDir(feDir)
  const feAgentAliases = feAgentSdkAliasesFromPackagesDir(packagesDir)
  const gr45RuntimeRoot = path.join(
    feDir,
    'agent-runtime-sdk',
    'gr45-fe-chat-runtime',
    'src',
  )
  delete feAgentAliases['@fe-agent/gr45-fe-chat-runtime']
  const gr45RuntimeAliases = {
    '@fe-agent/gr45-fe-chat-runtime/catalog': path.join(gr45RuntimeRoot, 'catalog', 'index.js'),
    '@fe-agent/gr45-fe-chat-runtime/chat-widget-agent': path.join(gr45RuntimeRoot, 'chat-widget-agent.js'),
    '@fe-agent/gr45-fe-chat-runtime/runtime': path.join(gr45RuntimeRoot, 'runtime.js'),
    '@fe-agent/gr45-fe-chat-runtime': path.join(gr45RuntimeRoot, 'index.js'),
  }

  // All @fe-agent/* package names resolved via aliases — must stay out of esbuild
  // dep pre-bundling so Vite serves them as raw ESM (no bundled CJS shim).
  const feAgentExcludes = [
    '@fe-agent/gr45-fe-chat-runtime',
    ...Object.keys(feAgentAliases),
  ]

  return {
    plugins: [
      vue(),
      vueDevTools(),
    ],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        ...gr45RuntimeAliases,
        ...feAgentAliases,
      },
    },
    server: {
      allowedHosts: true,
      fs: {
        allow: [feDir, ...sdkRoots],
      },
      proxy,
    },
    optimizeDeps: {
      exclude: ['onnxruntime-web', ...feAgentExcludes],
      include: [
        '@langchain/core',
        '@langchain/langgraph',
        '@langchain/langgraph-checkpoint',
        'zod',
      ],
    },
  }
})
