import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-slate-50 pt-6 sm:justify-center sm:pt-0 dark:bg-slate-950">
            <div>
                <Link href="/" className="flex flex-col items-center gap-3">
                    <div className="p-3.5 bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800">
                        <img
                            src="/images/bamcom-logo-cropped.png"
                            alt="Bamcom CRM portal"
                            className="h-14 w-auto object-contain"
                        />
                    </div>
                    <span className="text-xs font-bold tracking-wider text-slate-700 dark:text-slate-300 uppercase">
                        Bamcom CRM portal
                    </span>
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-6 shadow-md sm:max-w-md sm:rounded-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                {children}
            </div>
        </div>
    );
}
