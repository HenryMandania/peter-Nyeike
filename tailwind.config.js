import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',

        // Your app views + scripts
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',

        // Filament classes & components
        './app/Filament/**/*.php',

        // Your custom css folder for global overrides
        './resources/css/filament/**/*.css',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [],
};
