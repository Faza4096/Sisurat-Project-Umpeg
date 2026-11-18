import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['"Source Serif 4"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                gov: {
                    50: '#EEF4FB',
                    100: '#D8E6F4',
                    200: '#B3CCE7',
                    300: '#7FA9D2',
                    400: '#4B82B5',
                    500: '#2A5F95',
                    600: '#1E4A78',
                    700: '#173A5F',
                    800: '#112C48',
                    900: '#0B2036',
                    950: '#071526',
                },
                gold: {
                    50: '#FBF7EA',
                    100: '#F5EBC8',
                    200: '#EAD795',
                    300: '#DDBF5E',
                    400: '#D0A93B',
                    500: '#B98F27',
                    600: '#966E1F',
                    700: '#73531C',
                    800: '#523B18',
                    900: '#372811',
                },
            },
            boxShadow: {
                gov: '0 1px 2px rgba(11, 32, 54, 0.06), 0 8px 24px -12px rgba(11, 32, 54, 0.18)',
                card: '0 1px 2px rgba(11, 32, 54, 0.04)',
            },
        },
    },

    plugins: [forms],
};
