import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';

export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js', 'resources/js/components/ui/*', '.sandcastle'],
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            // Every time picker goes through TimeField / DateTimeField, which offer only the
            // 5-minute grid (#639, ADR-0028). A native time input lists all sixty minutes.
            'vue/no-restricted-static-attribute': [
                'error',
                ...['time', 'datetime-local'].map((value) => ({
                    key: 'type',
                    value,
                    element: '/^[Ii]nput$/',
                    message: 'Use TimeField or DateTimeField: times step in 5 minutes (ADR-0028).',
                })),
            ],
        },
    },
    prettier,
);
