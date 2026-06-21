/**
 * Global auth guard. Redirects unauthenticated users to /login,
 * and prevents authenticated users from seeing the login page.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  const { user, fetchUser } = useAuth()

  // Hydrate user once per session if not yet loaded.
  if (user.value === null) {
    await fetchUser()
  }

  const isAuthed = user.value !== null
  const isLoginPage = to.path === '/login'

  if (!isAuthed && !isLoginPage) {
    return navigateTo('/login')
  }

  if (isAuthed && isLoginPage) {
    return navigateTo('/')
  }
})
