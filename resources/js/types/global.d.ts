import type { Auth } from '@/types/auth';
import type { ImportResult } from '@/types/thesis';
import type { NotificationFeed, ThesisSettings } from '@/types/thesis';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: {
                success: string | null;
                error: string | null;
                importResult: ImportResult | null;
            };
            thesis: ThesisSettings | null;
            notificationFeed: NotificationFeed | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
