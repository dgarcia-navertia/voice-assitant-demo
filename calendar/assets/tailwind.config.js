// Tema Navertia. Los colores viven como variables CSS (ver assets/app.css) para
// que el modo claro/oscuro cambie solo con [data-theme]. Paleta de marca:
//   primary   #075056   accent #DC3225   ink #26180F   secondary #6A959A
// Se compila con `make css` (Tailwind standalone en Docker; sin CDN en prod).
const v = (name) => `rgb(var(--${name}) / <alpha-value>)`;
const scale = (prefix, steps) =>
    Object.fromEntries(steps.map((s) => [s, v(`${prefix}-${s}`)]));

module.exports = {
    darkMode: ['selector', '[data-theme="dark"]'],
    // Tailwind solo genera las clases que encuentra escritas en estos ficheros.
    content: ['./src/app/Views/**/*.php', './src/public/static/js/app.js'],
    theme: {
        extend: {
            colors: {
                // Tokens de marca (identicos en claro y oscuro salvo donde se indica).
                primary: {
                    DEFAULT: v('primary'),
                    hover: v('primary-hover'),
                    fg: v('primary-fg'),
                },
                accent: {
                    DEFAULT: v('accent'),
                    hover: v('accent-hover'),
                },
                ink: v('ink'),
                secondary: v('secondary'),
                surface: v('surface'),
                // Escalas semanticas que se invierten en modo oscuro.
                gray: scale('gray', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]),
                brand: scale('brand', [50, 100, 200, 300, 400, 500, 600, 700, 800, 900]),
                red: scale('red', [50, 100, 200, 500, 600, 700, 800]),
                green: scale('green', [50, 100, 200, 600, 700, 800]),
                amber: scale('amber', [50, 100, 200, 500, 600, 700, 800]),
                yellow: scale('yellow', [50, 100, 200, 700, 800, 900]),
                purple: scale('purple', [50, 100, 200, 700]),
            },
            fontFamily: {
                sans: ['ui-sans-serif', 'system-ui', '-apple-system', '"SF Pro Text"', '"Segoe UI"', 'Roboto', '"Helvetica Neue"', 'sans-serif'],
            },
            borderRadius: {
                glass: '1.5rem',
            },
            boxShadow: {
                glass: '0 8px 32px rgba(7, 80, 86, 0.10), inset 0 1px 0 rgba(255,255,255,0.55)',
            },
        },
    },
    plugins: [
        // Estilo base decente para todos los campos de formulario.
        require('@tailwindcss/forms')({ strategy: 'base' }),
    ],
};
