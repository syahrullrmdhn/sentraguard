import type { Config } from 'tailwindcss'

export default <Partial<Config>>{
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './app.vue',
    './error.vue',
  ],
  theme: {
    extend: {
      colors: {
        paper: 'var(--color-paper)',
        'paper-soft': 'var(--color-paper-soft)',
        ink: 'var(--color-ink)',
        'ink-soft': 'var(--color-ink-soft)',
        accent: 'var(--color-accent)',
        'accent-2': 'var(--color-accent-2)',
        danger: 'var(--color-danger)',
        ok: 'var(--color-ok)',
        neutral: 'var(--color-neutral)',
      },
      fontFamily: {
        sans: ['Space Grotesk', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        mono: ['Space Mono', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        brutal: '4px 4px 0 0 var(--color-ink)',
        'brutal-sm': '2px 2px 0 0 var(--color-ink)',
        'brutal-lg': '6px 6px 0 0 var(--color-ink)',
      },
    },
  },
}
