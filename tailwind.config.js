/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        strava: {
          orange: '#FC4C02',
          'orange-dark': '#E34402',
          'orange-light': '#FF6B35',
        },
        pattern: {
          steady: '#3B82F6',
          intervals: '#F97316',
          tempo: '#EF4444',
          'long-run': '#22C55E',
          unclassified: '#6B7280',
        },
      },
    },
  },
  plugins: [],
};
