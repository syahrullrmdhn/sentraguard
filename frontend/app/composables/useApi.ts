import { useRuntimeConfig } from '#imports'

/**
 * Authenticated API client wrapper around $fetch.
 * Always sends cookies + XSRF token so Sanctum SPA auth works for every call.
 *
 * apiBase is '' (empty) = same-origin. Nginx proxies /api to Laravel.
 */
const apiBase = ''

export const useApi = () => {

  const getCookie = (name: string): string => {
    if (!import.meta.client) return ''
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'))
    return match ? decodeURIComponent(match[2]) : ''
  }

  const request = <T>(url: string, opts: any = {}): Promise<T> => {
    const token = getCookie('XSRF-TOKEN')
    return $fetch<T>(url, {
      baseURL: apiBase,
      credentials: 'include',
      ...opts,
      headers: {
        Accept: 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        ...(opts.headers || {}),
      },
    })
  }

  return {
    get: <T>(url: string, params?: any) => request<T>(url, { method: 'GET', query: params }),
    post: <T>(url: string, body?: any) => request<T>(url, { method: 'POST', body }),
    put: <T>(url: string, body?: any) => request<T>(url, { method: 'PUT', body }),
    del: <T>(url: string) => request<T>(url, { method: 'DELETE' }),
  }
}
