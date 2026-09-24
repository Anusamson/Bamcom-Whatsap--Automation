import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Bamcom CRM portal';

createInertiaApp({
    title: (title) => {
        if (!title) return appName;
        const cleaned = title.replace(/\s*-\s*(Bamcom AI CRM|Bamcom CRM portal|Bamcom CRM|BAMCOM Real Estate CRM).*$/i, '').trim();
        return `${cleaned} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
