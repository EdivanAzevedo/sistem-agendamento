// npm audit gate with time-boxed exceptions (audit-exceptions.json), mirroring .trivyignore.yaml:
// HIGH/CRITICAL advisories fail the build unless excepted; an expired exception stops applying.
import { spawnSync } from 'node:child_process'
import { readFileSync } from 'node:fs'

const { stdout } = spawnSync('npm', ['audit', '--json'], { encoding: 'utf8' })
const report = JSON.parse(stdout)
const { exceptions } = JSON.parse(
  readFileSync(new URL('../audit-exceptions.json', import.meta.url), 'utf8'),
)
const today = new Date().toISOString().slice(0, 10)

const advisories = new Map()
for (const vulnerability of Object.values(report.vulnerabilities ?? {})) {
  for (const via of vulnerability.via) {
    if (typeof via !== 'object' || !['high', 'critical'].includes(via.severity)) continue
    const id = via.url.split('/').pop()
    advisories.set(id, { id, package: via.name, severity: via.severity, title: via.title })
  }
}

const active = new Set(exceptions.filter((e) => e.expires >= today).map((e) => e.advisory))
const failing = [...advisories.values()].filter((a) => !active.has(a.id))

for (const e of exceptions) {
  if (e.expires < today)
    console.warn(`Expired exception (no longer applied): ${e.advisory} (${e.expires})`)
  else if (!advisories.has(e.advisory)) console.warn(`Unused exception, remove it: ${e.advisory}`)
  else console.log(`Excepted until ${e.expires}: ${e.advisory} (${e.package}) — ${e.reason}`)
}

if (failing.length > 0) {
  console.error('HIGH/CRITICAL advisories without a valid exception:')
  for (const a of failing) console.error(`  ${a.id} ${a.severity} ${a.package}: ${a.title}`)
  process.exit(1)
}

console.log('No HIGH/CRITICAL advisories without a valid exception.')
