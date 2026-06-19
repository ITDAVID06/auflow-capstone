import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { Toaster, toast } from 'sonner';
import ErrorBoundary from './components/ErrorBoundary';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
let hasRegisteredGlobalInertiaHandlers = false;
let activeVisitCount = 0;
let lastErrorKey = '';
let lastErrorAt = 0;

const showErrorOnce = (key: string, callback: () => void): void => {
    const now = Date.now();
    if (lastErrorKey === key && now - lastErrorAt < 1200) {
        return;
    }

    lastErrorKey = key;
    lastErrorAt = now;
    callback();
};

const scheduleReload = (key: string, message: string): void => {
    showErrorOnce(key, () => {
        toast.error(message);
        window.setTimeout(() => {
            router.reload();
        }, 1500);
    });
};

const handleHttpStatus = (status: number | undefined, isInertiaVisit: boolean): void => {
    if (status === 419) {
        scheduleReload('status-419', 'Your session expired. Refreshing...');
        return;
    }

    if (status === 403) {
        showErrorOnce('status-403', () => {
            toast.error("You don't have permission to do that.");
        });
        return;
    }

    if (status === 404 && isInertiaVisit) {
        showErrorOnce('status-404', () => {
            toast.error('That page could not be found.');
        });
        return;
    }

    if (status === 409) {
        scheduleReload('status-409', 'This page is outdated. Reloading...');
        return;
    }

    if (status === undefined || status >= 500) {
        showErrorOnce('status-generic', () => {
            toast.error('Something went wrong. Please try again or contact support.');
        });
    }
};

const extractStatusFromException = (exception: unknown): number | undefined => {
    const asRecord = exception as { response?: { status?: number } };
    return asRecord.response?.status;
};

const registerGlobalInertiaErrorHandlers = (): void => {
    if (hasRegisteredGlobalInertiaHandlers) {
        return;
    }

    hasRegisteredGlobalInertiaHandlers = true;

    router.on('start', () => {
        activeVisitCount += 1;
    });

    router.on('finish', () => {
        activeVisitCount = Math.max(0, activeVisitCount - 1);
    });

    // Keep 422 validation handling local to individual forms/pages.
    router.on('error', () => {
        return;
    });

    router.on('invalid', (event) => {
        const status = event.detail.response?.status;
        handleHttpStatus(status, activeVisitCount > 0);
    });

    router.on('exception', (event) => {
        const status = extractStatusFromException(event.detail.exception);
        handleHttpStatus(status, activeVisitCount > 0);
        return false;
    });
};

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        registerGlobalInertiaErrorHandlers();
        const root = createRoot(el);

        root.render(
            <ErrorBoundary>
                <App {...props} />
                <Toaster
                    position="top-right"
                    richColors
                    expand
                    duration={3000}
                />
            </ErrorBoundary>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
