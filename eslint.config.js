/**
 * ESLint Configuration — Arsip Layar Frontend
 *
 * Architecture context:
 * - Single main JS file: public/assets/js/vue_enhance.js (IIFE, inline Vue templates)
 * - Service worker: sw.js
 * - No .vue SFC files — Vue components are string templates inside JS
 * - No build system — CDN-loaded Vue 3, Tailwind CSS, Plyr, HLS.js
 * - No Tailwind config file — Tailwind loaded via CDN with inline config
 */

import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import tailwindcss from 'eslint-plugin-tailwindcss';
import vuejsAccessibility from 'eslint-plugin-vuejs-accessibility';

export default [
  // ─── Global ignores ───
  {
    ignores: [
      'node_modules/**',
      'sandbox/**',
      'storage/**',
      'media/**',
      'public/assets/plyr.svg',
      '*.min.js',
      'public/sw.js',
    ],
  },

  // ─── Base JS rules (all .js files) ───
  js.configs.recommended,

  // ─── Vue rules (for inline template strings in vue_enhance.js) ───
  ...vue.configs['flat/recommended'],

  // ─── Vue Accessibility rules ───
  ...vuejsAccessibility.configs['flat/recommended'],

  // ─── Tailwind CSS class ordering (object config, not array) ───
  tailwindcss.configs.recommended,

  // ─── Per-file overrides ───
  {
    files: ['public/assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        navigator: 'readonly',
        location: 'readonly',
        console: 'readonly',
        setTimeout: 'readonly',
        setInterval: 'readonly',
        clearTimeout: 'readonly',
        clearInterval: 'readonly',
        requestAnimationFrame: 'readonly',
        fetch: 'readonly',
        FormData: 'readonly',
        XMLHttpRequest: 'readonly',
        URLSearchParams: 'readonly',
        HTMLElement: 'readonly',
        HTMLFormElement: 'readonly',
        HTMLInputElement: 'readonly',
        Event: 'readonly',
        MediaSource: 'readonly',
        // CDN-loaded libraries
        Vue: 'readonly',
        Plyr: 'readonly',
        Hls: 'readonly',
        // PHP-rendered globals
        TailwindCSS: 'readonly',
      },
    },
    rules: {
      // ── JavaScript quality ──
      'no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^(e|_|ev)$' }],
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-debugger': 'warn',
      'no-alert': 'warn',
      'no-var': 'error',
      'prefer-const': 'warn',
      'prefer-template': 'off', // IIFE style uses concatenation intentionally
      'eqeqeq': ['warn', 'smart'],
      'no-implicit-globals': 'off', // IIFE pattern is intentional
      'strict': 'off', // IIFE has 'use strict' inside

      // ── Vue template rules (for inline string templates) ──
      'vue/multi-word-component-names': 'off', // Not applicable — inline templates
      'vue/no-v-html': 'warn',
      'vue/require-v-for-key': 'warn',
      'vue/no-unused-components': 'warn',
      'vue/no-unused-vars': 'warn',
      'vue/valid-v-if': 'warn',
      'vue/valid-v-else-if': 'warn',
      'vue/valid-v-else': 'warn',
      'vue/valid-v-for': 'warn',
      'vue/valid-v-on': 'warn',
      'vue/valid-v-bind': 'warn',
      'vue/valid-v-model': 'warn',
      'vue/valid-v-show': 'warn',
      'vue/valid-v-cloak': 'off',
      'vue/no-v-for-template-on-child': 'off',
      'vue/html-self-closing': 'off', // Inline templates use mixed style
      'vue/max-attributes-per-line': 'off', // Inline templates are single-line strings
      'vue/html-closing-bracket-newline': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/attribute-hyphenation': 'off',
      'vue/component-name-in-template-casing': 'off',

      // ── Tailwind class rules ──
      'tailwindcss/classnames-order': 'warn',
      'tailwindcss/enforces-negative-arbitrary-values': 'warn',
      'tailwindcss/enforces-shorthand': 'warn',
      'tailwindcss/important-modifier-suffix': 'off',
      'tailwindcss/no-arbitrary-value': 'off', // Needed for dynamic styles
      'tailwindcss/no-unnecessary-arbitrary-value': 'off',
      'tailwindcss/no-contradicting-classname': 'error',
      'tailwindcss/no-custom-classname': 'off', // Project uses custom CSS classes extensively

      // ── Accessibility rules ──
      'vuejs-accessibility/alt-text': 'warn',
      'vuejs-accessibility/anchor-has-content': 'warn',
      'vuejs-accessibility/aria-props': 'error',
      'vuejs-accessibility/aria-role': 'error',
      'vuejs-accessibility/aria-unsupported-elements': 'error',
      'vuejs-accessibility/click-events-have-key-events': 'warn',
      'vuejs-accessibility/form-control-has-label': 'warn',
      'vuejs-accessibility/heading-has-content': 'warn',
      'vuejs-accessibility/label-has-for': 'warn',
      'vuejs-accessibility/media-has-caption': 'off', // Video player captions are optional
      'vuejs-accessibility/no-autofocus': 'warn',
      'vuejs-accessibility/no-redundant-roles': 'warn',
      'vuejs-accessibility/role-has-required-aria-props': 'error',
      'vuejs-accessibility/tabindex-no-positive': 'warn',
    },
  },

  // ─── Service worker (different globals) ───
  {
    files: ['sw.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        self: 'readonly',
        caches: 'readonly',
        fetch: 'readonly',
      },
    },
    rules: {
      'no-console': 'off',
      'tailwindcss/classnames-order': 'off',
      'tailwindcss/*': 'off',
      'vuejs-accessibility/*': 'off',
      'vue/*': 'off',
    },
  },
];
