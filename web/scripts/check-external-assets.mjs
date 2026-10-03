// Fails when the production build references another origin. The Content Security Policy only
// allows 'self', and third-party requests (e.g. a font CDN) would expose visitors' IPs.
import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const dist = fileURLToPath(new URL('../dist/', import.meta.url))
const files = [
  ...readdirSync(dist)
    .filter((f) => f.endsWith('.html'))
    .map((f) => join(dist, f)),
  ...readdirSync(join(dist, 'assets'))
    .filter((f) => f.endsWith('.css'))
    .map((f) => join(dist, 'assets', f)),
]

// Absolute or protocol-relative URLs in CSS url()/@import and in HTML src/href attributes.
const external =
  /(?:url\(\s*['"]?|@import\s+(?:url\(\s*)?['"]?|\b(?:src|href)\s*=\s*['"])((?:https?:)?\/\/[^'")\s>]+)/gi

const offenders = files.flatMap((file) =>
  [...readFileSync(file, 'utf8').matchAll(external)].map(
    (m) => `${file.slice(dist.length)}: ${m[1]}`,
  ),
)

if (offenders.length > 0) {
  console.error('External resources in the build (blocked by the CSP, and they leak visitor data):')
  for (const offender of offenders) console.error(`  ${offender}`)
  process.exit(1)
}

console.log(`No external resources in ${files.length} built HTML/CSS files.`)
