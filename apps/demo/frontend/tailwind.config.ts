import type { Config } from 'tailwindcss';

export default {
  content: ['./src/**/*.{html,js,svelte,ts}'],
  theme: {
    extend: {
      colors: {
        border: 'hsl(214 24% 88%)',
        input: 'hsl(214 24% 88%)',
        ring: 'hsl(173 65% 35%)',
        background: 'hsl(210 25% 98%)',
        foreground: 'hsl(222 32% 12%)',
        primary: {
          DEFAULT: 'hsl(173 68% 32%)',
          foreground: 'hsl(0 0% 100%)'
        },
        accent: {
          DEFAULT: 'hsl(18 84% 58%)',
          foreground: 'hsl(0 0% 100%)'
        },
        muted: {
          DEFAULT: 'hsl(210 20% 94%)',
          foreground: 'hsl(215 15% 42%)'
        },
        destructive: {
          DEFAULT: 'hsl(0 72% 50%)',
          foreground: 'hsl(0 0% 100%)'
        }
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
      },
      boxShadow: {
        soft: '0 18px 60px rgb(15 23 42 / 0.08)'
      }
    }
  },
  plugins: []
} satisfies Config;
