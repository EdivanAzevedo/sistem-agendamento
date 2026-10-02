import createClient, { type Middleware } from 'openapi-fetch'

import type { components, paths } from './schema'

export type ProblemDetails = components['schemas']['ProblemDetails']
export type ValidationProblemDetails = components['schemas']['ValidationProblemDetails']

const PROBLEM_CONTENT_TYPE = 'application/problem+json'

/**
 * Thrown for every unsuccessful API response.
 * `problem` holds the RFC 9457 body when the API produced one, and is null otherwise
 * (for example, an error page from a proxy in front of the API).
 */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly problem: ProblemDetails | ValidationProblemDetails | null,
  ) {
    super(problem?.title ?? `HTTP ${status}`)
    this.name = 'ApiError'
  }

  /** Stable failure identifier: the slug at the end of `type` (null for generic HTTP failures). */
  get problemType(): string | null {
    const type = this.problem?.type
    if (!type || type === 'about:blank') return null
    return type.slice(type.lastIndexOf('/') + 1)
  }

  /** Validation messages per field; empty when the failure is not a validation error. */
  get fieldErrors(): Record<string, string[]> {
    return this.problem && 'errors' in this.problem ? this.problem.errors : {}
  }

  /** Code that support uses to find the request in the server logs. */
  get traceId(): string | null {
    return this.problem?.trace_id ?? null
  }
}

/** Turns every unsuccessful response into an ApiError, so failures are handled in one place. */
export const throwApiErrors: Middleware = {
  async onResponse({ response }) {
    if (response.ok) return undefined

    const isProblem =
      response.headers.get('Content-Type')?.startsWith(PROBLEM_CONTENT_TYPE) ?? false
    const problem = isProblem ? ((await response.clone().json()) as ProblemDetails) : null

    throw new ApiError(response.status, problem)
  },
}

/** Typed client for the API, generated from the versioned OpenAPI contract. */
export function createApiClient(fetchImpl?: (input: Request) => Promise<Response>) {
  const client = createClient<paths>({
    baseUrl: '/api/v1',
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
    ...(fetchImpl ? { fetch: fetchImpl } : {}),
  })
  client.use(throwApiErrors)

  return client
}

export const api = createApiClient()
