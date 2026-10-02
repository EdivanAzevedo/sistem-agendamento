import { describe, expect, it } from 'vitest'

import { ApiError, throwApiErrors } from '../client'

function problem(status: number, body: Record<string, unknown>): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/problem+json' },
  })
}

async function handle(response: Response): Promise<unknown> {
  const onResponse = throwApiErrors.onResponse
  if (!onResponse) throw new Error('the middleware must handle responses')

  return onResponse({ response } as Parameters<typeof onResponse>[0])
}

async function caught(response: Response): Promise<ApiError> {
  const error = await handle(response).catch((e: unknown) => e)
  expect(error).toBeInstanceOf(ApiError)

  return error as ApiError
}

describe('throwApiErrors', () => {
  it('lets successful responses through', async () => {
    await expect(handle(new Response('{}', { status: 200 }))).resolves.toBeUndefined()
  })

  it('exposes validation problems with their field messages', async () => {
    const error = await caught(
      problem(422, {
        type: 'https://agendamento.test/problems/validation-error',
        title: 'Dados inválidos',
        status: 422,
        errors: { email: ['O campo e-mail é obrigatório.'] },
        trace_id: '4bf92f3577b34da6a3ce929d0e0e4736',
      }),
    )

    expect(error.status).toBe(422)
    expect(error.message).toBe('Dados inválidos')
    expect(error.problemType).toBe('validation-error')
    expect(error.fieldErrors).toEqual({ email: ['O campo e-mail é obrigatório.'] })
    expect(error.traceId).toBe('4bf92f3577b34da6a3ce929d0e0e4736')
  })

  it('identifies business-rule failures by the slug of their type', async () => {
    const error = await caught(
      problem(409, {
        type: 'https://agendamento.test/problems/slot-taken',
        title: 'Horário indisponível',
        status: 409,
        trace_id: '4bf92f3577b34da6a3ce929d0e0e4736',
      }),
    )

    expect(error.problemType).toBe('slot-taken')
    expect(error.fieldErrors).toEqual({})
  })

  it('has no problem type for generic HTTP failures', async () => {
    const error = await caught(
      problem(404, {
        type: 'about:blank',
        title: 'Não encontrado',
        status: 404,
        trace_id: '4bf92f3577b34da6a3ce929d0e0e4736',
      }),
    )

    expect(error.problemType).toBeNull()
    expect(error.message).toBe('Não encontrado')
  })

  it('still fails clearly when the response is not a problem document', async () => {
    const error = await caught(
      new Response('<html>Bad Gateway</html>', {
        status: 502,
        headers: { 'Content-Type': 'text/html' },
      }),
    )

    expect(error.status).toBe(502)
    expect(error.problem).toBeNull()
    expect(error.message).toBe('HTTP 502')
    expect(error.traceId).toBeNull()
  })
})
