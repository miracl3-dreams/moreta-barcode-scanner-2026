import { spawn } from 'node:child_process'

process.env.VITE_HTTPS = '1'

const child = spawn('npx', ['vite'], {
  stdio: 'inherit',
  env: process.env,
  shell: true,
})

child.on('exit', (code) => process.exit(code ?? 0))
