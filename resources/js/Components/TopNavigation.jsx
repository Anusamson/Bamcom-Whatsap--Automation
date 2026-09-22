import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { 
    Menu, 
    Bell, 
    User, 
    LogOut, 
    Settings, 
    Search,
    ChevronDown,
    Activity
} from 'lucide-react';
import ThemeToggle from '@/Components/ThemeToggle';
import Dropdown from '@/Components/Dropdown';

export default function TopNavigation({ 
    user, 
    header, 
    onToggleMobile 
}) {
    const [unreadAlerts, setUnreadAlerts] = useState(2);

    return (
        <header className="sticky top-0 z-20 h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 transition-colors">
            <div className="flex items-center justify-between h-full px-4 sm:px-6 lg:px-8">
                {/* Left section: Hamburger (mobile) + Breadcrumbs / Header */}
                <div className="flex items-center gap-4">
                    <button
                        type="button"
                        onClick={onToggleMobile}
                        className="lg:hidden p-2 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600"
                        aria-label="Open mobile menu"
                    >
                        <Menu className="h-6 w-6" />
                    </button>

                    <div className="flex items-center gap-2">
                        <span className="hidden sm:inline-block text-xs font-semibold uppercase tracking-wider text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-900">
                            Bamcom AI CRM
                        </span>
                        {header && (
                            <div className="text-base sm:text-lg font-bold text-slate-900 dark:text-white truncate">
                                {header}
                            </div>
                        )}
                    </div>
                </div>

                {/* Right section: Search + Health shortcut + Theme toggle + Notifications + User Menu */}
                <div className="flex items-center gap-2 sm:gap-3">
                    {/* Live Health API Shortcut */}
                    <a
                        href={route('api.v1.health')}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                        title="View API v1 Live Health JSON"
                    >
                        <Activity className="h-3.5 w-3.5 text-emerald-500" />
                        <span>API v1 Health</span>
                    </a>

                    {/* Theme Mode Toggle (Light/Dark) */}
                    <ThemeToggle />

                    {/* Notifications with red indicator */}
                    <div className="relative">
                        <button
                            type="button"
                            className="p-2 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition relative focus:outline-none focus:ring-2 focus:ring-blue-600"
                            title="System Notifications"
                        >
                            <Bell className="h-5 w-5" />
                            {unreadAlerts > 0 && (
                                <span className="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
                                    <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                    <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span>
                                </span>
                            )}
                        </button>
                    </div>

                    {/* User Profile Dropdown */}
                    <div className="relative ml-1">
                        <Dropdown>
                            <Dropdown.Trigger>
                                <button
                                    type="button"
                                    className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition focus:outline-none focus:ring-2 focus:ring-blue-600"
                                >
                                    <div className="h-8 w-8 rounded-lg bg-gradient-to-tr from-blue-700 to-red-600 flex items-center justify-center text-white font-bold text-sm shadow">
                                        {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                                    </div>
                                    <div className="hidden sm:flex flex-col text-left leading-tight">
                                        <span className="text-xs font-semibold text-slate-900 dark:text-white truncate max-w-[120px]">
                                            {user?.name || 'Bamcom User'}
                                        </span>
                                        <span className="text-[10px] text-slate-500 dark:text-slate-400 capitalize">
                                            {user?.role || 'Administrator'}
                                        </span>
                                    </div>
                                    <ChevronDown className="hidden sm:block h-3.5 w-3.5 text-slate-400" />
                                </button>
                            </Dropdown.Trigger>

                            <Dropdown.Content align="right" width="48">
                                <div className="px-4 py-2 border-b border-slate-100 dark:border-slate-800">
                                    <p className="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                        {user?.name}
                                    </p>
                                    <p className="text-[11px] text-slate-500 dark:text-slate-400 truncate">
                                        {user?.email}
                                    </p>
                                </div>
                                <Dropdown.Link href={route('profile.edit')}>
                                    <div className="flex items-center gap-2">
                                        <User className="h-4 w-4 text-blue-600" />
                                        <span>Account Profile</span>
                                    </div>
                                </Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    <div className="flex items-center gap-2 text-red-600 dark:text-red-400">
                                        <LogOut className="h-4 w-4" />
                                        <span>Log Out</span>
                                    </div>
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </div>
            </div>
        </header>
    );
}
