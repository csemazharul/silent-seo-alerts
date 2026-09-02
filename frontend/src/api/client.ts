import queryRequest from '@common/helpers/request'

/**
 * Unwraps the plugin's `{ status, code, data }` envelope so React Query hooks
 * work with plain payloads, and turns an error envelope into a thrown Error.
 */
export default async function call<T>(action: string, payload?: unknown): Promise<T> {
  const response = await queryRequest<T>(action, payload ?? {})

  if (response.status === 'error') {
    throw new Error(typeof response.data === 'string' ? response.data : 'Request failed')
  }

  return response.data
}
