import { access, cp, rm } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const source = path.join(root, 'public', 'build')
const webRoot = process.env.PUBLIC_HTML
    ? path.resolve(process.env.PUBLIC_HTML)
    : path.resolve(root, '..', 'public_html')
const destination = path.join(webRoot, 'build')

try {
    await access(source)
} catch {
    console.error(`No build found at ${source}. Run vite build first.`)
    process.exit(1)
}

try {
    await access(webRoot)
} catch {
    console.log(`public_html was not found at ${webRoot}, so the build stays in public/build.`)
    process.exit(0)
}

await rm(destination, { recursive: true, force: true })
await cp(source, destination, { recursive: true })
console.log(`Copied build to ${destination}`)
