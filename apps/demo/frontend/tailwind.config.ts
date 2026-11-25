import type { Config } from 'tailwindcss';

export default {
  content: ['./src/**/*.{html,js,svelte,ts}'],
  theme: {
    extend: {
      colors: {
        border: 'hsl(214 32% 91%)',
        input: 'hsl(214 32% 91%)',
        ring: 'hsl(192 82% 34%)',
        background: 'hsl(210 40% 98%)',
        foreground: 'hsl(222 47% 11%)',
        primary: {
          50: '#f5f8f9',
          100: '#e9f0f2',
          200: '#cde4ea',
          300: '#94e0f5',
          400: '#61d1f0',
          500: '#2ec2eb',
          600: '#14a8d1',
          700: '#118bac',
          800: '#0d6982',
          900: '#094b5d',
          950: '#0b2e37',
          DEFAULT: '#118bac',
          foreground: 'hsl(0 0% 100%)'
        },
        accent: {
          DEFAULT: 'hsl(32 95% 44%)',
          foreground: 'hsl(0 0% 100%)'
        },
        muted: {
          DEFAULT: 'hsl(210 40% 96%)',
          foreground: 'hsl(215 16% 47%)'
        },
        destructive: {
          DEFAULT: 'hsl(347 77% 50%)',
          foreground: 'hsl(0 0% 100%)'
        }
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
      },
      boxShadow: {
        soft: '0 10px 30px rgb(15 23 42 / 0.06)'
      }
    }
  },
  plugins: []
} satisfies Config;
