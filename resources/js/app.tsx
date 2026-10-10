import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import IndustryLayout from '@/layouts/industry-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// The name the server is configured with, so renaming the application never
// needs a rebuild. There is no document while a page is rendered on the server,
// where the name the build was given stands in.
const appName =
    (typeof document === 'undefined'
        ? null
        : document.querySelector<HTMLMetaElement>(
              'meta[name="application-name"]',
          )?.content) ||
    import.meta.env.VITE_APP_NAME ||
    'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            // The sign-in screen stands alone: it is the front door, and lays
            // out the whole viewport itself.
            case name === 'auth/login':
            // The error page stands alone: it has to render for a signed-out
            // visitor too, where the app shell has no user to draw.
            case name === 'error':
            // Public verification stands alone: it must render for someone who
            // has only a printed page and no account.
            case name === 'verify':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [IndustryLayout, SettingsLayout];
            case name.startsWith('thesis/'):
            case name.startsWith('admin/'):
            case name.startsWith('superadmin/'):
                return IndustryLayout;
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
