import { useState } from '#imports'

interface User {
  id: number
  name: string
  email: string
  role: string | null
  is_super_admin: boolean
}

/**
 * Sanctum SPA cookie auth client.
 *
 * Flow:
 *   1. GET /sanctum/csrf-cookie  -> sets XSRF-TOKEN cookie
 *   2. POST /api/auth/login      -> sets session cookie
 *   3. Subsequent requests send cookies + X-XSRF-TOKEN header automatically
 *
 * apiBase is '' (empty) = same-origin. Nginx proxies /api, /sanctum, etc.
 * to Laravel backend, so relative URLs work without cross-origin issues.
 */
const apiBase = ''

export const useAuth = () => {
  const user = useState<User | null>('auth_user', () => null)

  /** Read a cookie value by name (browser only). */
  const getCookie = (name: string): string => {
    if (!import.meta.client) return ''
    const match = document.cookie.match(new RegExp('(^|;\\s*)' + name + '=([^;]*)'))
    return match ? decodeURIComponent(match[2]) : ''
  }

  /** Fetch the CSRF cookie before any state-changing request. */
  const csrf = async () => {
    await $fetch('/sanctum/csrf-cookie', {
      baseURL: apiBase,
      credentials: 'include',
    })
  }

  /** Build headers including the XSRF token Laravel expects. */
  const authHeaders = (): Record<string, string> => {
    const token = getCookie('XSRF-TOKEN')
    return token ? { 'X-XSRF-TOKEN': token, Accept: 'application/json' } : { Accept: 'application/json' }
  }

  const login = async (email: string, password: string, remember = false) => {
    await csrf()
    const res = await $fetch<{ user: User }>('/api/auth/login', {
      baseURL: apiBase,
      method: 'POST',
      credentials: 'include',
      headers: authHeaders(),
      body: { email, password, remember },
    })
    user.value = res.user
    return res.user
  }

  const logout = async () => {
    await $fetch('/api/auth/logout', {
      baseURL: apiBase,
      method: 'POST',
      credentials: 'include',
      headers: authHeaders(),
    }).catch(() => {})
    user.value = null
  }

  /** Hydrate the current user (used by middleware / app boot). */
  const fetchUser = async (): Promise<User | null> => {
    try {
      const res = await $fetch<{ user: User }>('/api/auth/me', {
        baseURL: apiBase,
        credentials: 'include',
        headers: { Accept: 'application/json' },
      })
      user.value = res.user
      return res.user
    } catch {
      user.value = null
      return null
    }
  }

  return { user, login, logout, fetchUser, csrf, authHeaders }
}
