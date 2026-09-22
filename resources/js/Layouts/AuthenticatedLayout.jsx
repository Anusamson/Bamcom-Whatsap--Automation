import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import Sidebar from '@/Components/Sidebar';
import TopNavigation from '@/Components/TopNavigation';

/**
 * Base Dashboard Layout for Bamcom AI CRM.
 * Integrates responsive Sidebar, TopNavigation, and Content Area with light/dark theme support.
 */
export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [isSidebarOpen, setIsSidebarOpen] = useState(true);
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);

    return (
        <div className="min-h-screen bg-slate-50 dark:bg-slate-950 transition-colors duration-200">
            {/* Responsive Sidebar */}
            <Sidebar
                isOpen={isSidebarOpen}
                setIsOpen={setIsSidebarOpen}
                isMobileOpen={isMobileSidebarOpen}
                setIsMobileOpen={setIsMobileSidebarOpen}
                user={user}
            />

            {/* Main Application Wrapper */}
            <div
                className={`flex flex-col min-h-screen transition-all duration-300 ease-in-out ${
                    isSidebarOpen ? 'lg:pl-64' : 'lg:pl-20'
                }`}
            >
                {/* Top Navigation */}
                <TopNavigation
                    user={user}
                    header={header}
                    onToggleMobile={() => setIsMobileSidebarOpen(true)}
                />

                {/* Responsive Content Area */}
                <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
                    {children}
                </main>

                {/* Foundation Footer */}
                <footer className="border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 py-3 px-4 sm:px-6 lg:px-8 transition-colors">
                    <div className="flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 dark:text-slate-400 gap-2">
                        <div className="flex items-center gap-1.5">
                            <span className="font-semibold text-slate-700 dark:text-slate-200">Bamcom AI CRM</span>
                            <span>&bull;</span>
                            <span>Phase 1 Architecture Foundation</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400">
                                <span className="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                                Laravel 13
                            </span>
                            <span className="inline-flex items-center gap-1 text-red-600 dark:text-red-400">
                                <span className="h-1.5 w-1.5 rounded-full bg-red-600"></span>
                                Redis / Predis
                            </span>
                            <span className="inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                <span className="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                React + Inertia
                            </span>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    );
}
