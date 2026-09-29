import { existsSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// Thème Filament du panneau d'administration (même chemin que ->viteTheme() dans App\Providers\AppServiceProvider :
// à changer aux deux endroits). Exposé sous l'alias `panel-theme` pour les modules (`@reference 'panel-theme';`).
const panelTheme = 'resources/css/filament/lunar/theme.css';

// Points d'entrée CSS des modules : chaque fichier .css à la racine de modules/<Nom>/resources/css (module.css par
// défaut, d'autres si le module charge des styles différents selon les pages), compilé à part et chargé par le
// module lui-même (voir Modules\Support\ModuleStyles). Les fichiers qu'ils importent vont dans partials/.
const moduleStyles = readdirSync('modules', { withFileTypes: true })
    .filter((module) => module.isDirectory())
    .map((module) => `modules/${module.name}/resources/css`)
    .filter((directory) => existsSync(directory))
    .flatMap((directory) => readdirSync(directory, { withFileTypes: true })
        .filter((file) => file.isFile() && file.name.endsWith('.css'))
        .map((file) => `${directory}/${file.name}`));

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', panelTheme, ...moduleStyles],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            'panel-theme': fileURLToPath(new URL(panelTheme, import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
