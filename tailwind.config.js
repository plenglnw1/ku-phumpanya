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
            colors: {
                phumpanya: {
                    50: '#f4f7f4',
                    100: '#c5d4c0',
                    800: '#3d6b4f',
                    900: '#2D5A43',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                serif: ['Libre Baskerville', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
