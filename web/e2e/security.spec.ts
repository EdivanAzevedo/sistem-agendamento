import { expect, test, type Page } from '@playwright/test'

/**
 * Collects CSP violations, uncaught errors and console errors raised while a page loads.
 * `expected` lists the few known, harmless violations a page may cause; anything else fails.
 */
async function watchForProblems(page: Page, expected: RegExp[] = []): Promise<string[]> {
  const problems: string[] = []
  const record = (problem: string) => {
    if (!expected.some((pattern) => pattern.test(problem))) problems.push(problem)
  }

  page.on('console', (message) => {
    if (message.type() === 'error') record(`console: ${message.text()}`)
  })
  page.on('pageerror', (error) => record(`uncaught: ${error.message}`))
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', (event) => {
      console.error(
        `CSP violation: ${event.violatedDirective} blocked ${event.blockedURI} at ${event.sourceFile}`,
      )
    })
  })

  return problems
}

test('the SPA renders under the production CSP', async ({ page }) => {
  const problems = await watchForProblems(page)

  await page.goto('/')

  await expect(page.getByRole('heading', { name: 'Agende seu horário' })).toBeVisible()
  expect(problems).toEqual([])
})

test('the API docs render under their own CSP', async ({ page }) => {
  const problems = await watchForProblems(page, [
    // Zod (bundled in Scalar) probes `new Function('')` to detect whether it may compile
    // validators; when the CSP blocks it, Zod falls back to its interpreter. Harmless, and far
    // better than allowing 'unsafe-eval'.
    /^console: CSP violation: script-src(-elem)? blocked eval at https:\/\/cdn\.jsdelivr\.net\/npm\/@scalar\/api-reference@/,
    /^console: Refused to evaluate a string as JavaScript because 'unsafe-eval'/,
  ])

  await page.goto('/docs/api')

  await expect(page.getByRole('heading', { name: 'Sistema de Agendamento — API' })).toBeVisible({
    timeout: 15_000,
  })
  expect(problems).toEqual([])
})

test('every response carries the security headers', async ({ request }) => {
  for (const path of ['/', '/api/v1/does-not-exist', '/docs/api']) {
    const headers = (await request.get(path)).headers()

    expect
      .soft(headers['strict-transport-security'], path)
      .toBe('max-age=31536000; includeSubDomains')
    expect.soft(headers['content-security-policy'], path).toContain("frame-ancestors 'none'")
    expect.soft(headers['x-content-type-options'], path).toBe('nosniff')
    expect.soft(headers['x-frame-options'], path).toBe('DENY')
    expect.soft(headers['referrer-policy'], path).toBe('same-origin')
    expect.soft(headers['permissions-policy'], path).toContain('camera=()')
    expect.soft(headers['cross-origin-opener-policy'], path).toBe('same-origin')
    expect.soft(headers['server'], path).toBeUndefined()
  }
})

test('only the API docs relax the CSP, with a nonce instead of inline scripts', async ({
  request,
}) => {
  const spaPolicy = (await request.get('/')).headers()['content-security-policy']
  expect(spaPolicy).toContain("script-src 'self';")

  const docs = await request.get('/docs/api')
  const docsPolicy = docs.headers()['content-security-policy'] ?? ''
  const nonce = /'nonce-([^']+)'/.exec(docsPolicy)?.[1]

  expect(nonce).toBeTruthy()
  expect(docsPolicy).not.toMatch(/script-src[^;]*'unsafe-inline'/)
  expect(await docs.text()).toContain(`nonce="${nonce}"`)
})
