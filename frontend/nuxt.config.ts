// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-01-01',
  devtools: { enabled: false },

  // SPA mode — pure client-side rendering (no SSR), per migration decision.
  ssr: false,

  modules: ['@nuxtjs/tailwindcss'],

  app: {
    head: {
      title: 'SentraGuard AgentOps Console',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
      link: [
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap',
        },
      ],
    },
  },

  runtimeConfig: {
    public: {
      // Empty = same-origin (relative URLs). Nginx proxies /api, /sanctum,
      // /login, /logout, /download to Laravel, so Sanctum cookies are
      // first-party and SameSite issues disappear. Override only if the SPA
      // is ever hosted on a truly separate origin.
      apiBase: process.env.NUXT_PUBLIC_API_BASE || '',
    },
  },

  css: ['~/assets/css/main.css'],
})
