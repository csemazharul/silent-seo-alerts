import { __ } from '@common/helpers/i18nWrap'
import queryRequest from '@common/helpers/request'

/** Pulls the human-readable message out of an error envelope, or any thrown value. */
const toError = (value: unknown): Error => {
  if (value instanceof Error) return value

  const data = (value as { data?: unknown } | null)?.data

  return new Error(typeof data === 'string' && data !== '' ? data : __('Request failed.'))
}

/**
 * Unwraps the plugin's `{ status, code, data }` envelope so React Query hooks
 * work with plain payloads, and turns an error envelope into a thrown Error.
 */
export default async function call<T>(action: string, payload?: unknown): Promise<T> {
  // A non-2xx response rejects with the bare envelope rather than returning it,
  // and an envelope is not an Error, so `error.message` would be undefined.
  const response = await queryRequest<T>(action, payload ?? {}).catch(rejection => {
    throw toError(rejection)
  })

  if (response.status === 'error') {
    throw toError(response)
  }

  return response.data
}
