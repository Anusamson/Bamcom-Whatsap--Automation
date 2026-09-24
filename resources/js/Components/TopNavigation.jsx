import { useState, useEffect, useRef } from 'react';
import { Link, router } from '@inertiajs/react';
import { 
    Menu, 
    Bell, 
    User, 
    LogOut, 
    Settings, 
    Search,
    ChevronDown,
    Activity,
    CheckCheck,
    Check,
    Flame,
    UserPlus,
    Calendar,
    Clock,
    AlertTriangle,
    MessageSquare,
    TrendingUp,
    AlertCircle,
    ExternalLink
} from 'lucide-react';
import ThemeToggle from '@/Components/ThemeToggle';
import Dropdown from '@/Components/Dropdown';

export default function TopNavigation({ 
    user, 
    header, 
    onToggleMobile 
}) {
    const [unreadAlerts, setUnreadAlerts] = useState(0);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [recentNotifications, setRecentNotifications] = useState([]);
    const [loadingNotifications, setLoadingNotifications] = useState(false);
    const notificationRef = useRef(null);

    // Fetch unread count periodically (15s)
    const fetchUnreadCount = async () => {
        try {
            const res = await fetch(route('notifications.unread-count'), {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setUnreadAlerts(data.unread_notifications || 0);
            }
        } catch {
            // Ignore polling errors
        }
    };

    // Load recent notifications when dropdown is opened
    const fetchRecentNotifications = async () => {
        setLoadingNotifications(true);
        try {
            const res = await fetch(route('notifications.index'), {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                setRecentNotifications(data.notifications?.data || []);
                setUnreadAlerts(data.unread_count || 0);
            }
        } catch {
            // Ignore fetch errors
        } finally {
            setLoadingNotifications(false);
        }
    };

    const handleToggleNotifications = () => {
        const nextState = !notificationsOpen;
        setNotificationsOpen(nextState);
        if (nextState) {
            fetchRecentNotifications();
        }
    };

    const handleMarkAsRead = async (id, e) => {
        e?.stopPropagation();
        try {
            const res = await fetch(route('notifications.read', id), {
                method: 'PATCH',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (res.ok) {
                const data = await res.json();
                setUnreadAlerts(data.unread_count || 0);
                setRecentNotifications(prev =>
                    prev.map(n => n.id === id ? { ...n, read: true } : n)
                );
            }
        } catch {
            // Ignore
        }
    };

    const handleMarkAllRead = async () => {
        try {
            const res = await fetch(route('notifications.mark-all-read'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            if (res.ok) {
                setUnreadAlerts(0);
                setRecentNotifications(prev => prev.map(n => ({ ...n, read: true })));
            }
        } catch {
            // Ignore
        }
    };

    // Click outside to close dropdown
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (notificationRef.current && !notificationRef.current.contains(event.target)) {
                setNotificationsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Set initial and interval polling
    useEffect(() => {
        fetchUnreadCount();
        const timer = setInterval(fetchUnreadCount, 15000);
        return () => clearInterval(timer);
    }, []);

    const getIconForType = (type) => {
        switch (type) {
            case 'hot_lead': return <Flame className="h-4 w-4 text-amber-500" />;
            case 'new_assigned_lead': return <UserPlus className="h-4 w-4 text-indigo-500" />;
            case 'human_handover': return <AlertCircle className="h-4 w-4 text-rose-500" />;
            case 'inspection_request': return <Calendar className="h-4 w-4 text-cyan-500" />;
            case 'inspection_reminder': return <Clock className="h-4 w-4 text-emerald-500" />;
            case 'overdue_task': return <AlertTriangle className="h-4 w-4 text-red-500" />;
            case 'new_customer_reply': return <MessageSquare className="h-4 w-4 text-blue-500" />;
            case 'deal_activity': return <TrendingUp className="h-4 w-4 text-purple-500" />;
            default: return <Bell className="h-4 w-4 text-slate-500" />;
        }
    };

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

                    {/* Notifications with real dropdown and dynamic unread counter */}
                    <div className="relative" ref={notificationRef}>
                        <button
                            type="button"
                            onClick={handleToggleNotifications}
                            className="p-2 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition relative focus:outline-none focus:ring-2 focus:ring-blue-600"
                            title="System Notifications"
                        >
                            <Bell className="h-5 w-5" />
                            {unreadAlerts > 0 && (
                                <span className="absolute top-1 right-1 flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold shadow-sm ring-2 ring-white dark:ring-slate-900">
                                    {unreadAlerts > 99 ? '99+' : unreadAlerts}
                                </span>
                            )}
                        </button>

                        {/* Dropdown Popover */}
                        {notificationsOpen && (
                            <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 py-2 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                <div className="px-4 py-2 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-bold text-slate-900 dark:text-white">
                                            Notifications
                                        </span>
                                        {unreadAlerts > 0 && (
                                            <span className="px-1.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 text-[10px] font-semibold">
                                                {unreadAlerts} new
                                            </span>
                                        )}
                                    </div>
                                    {unreadAlerts > 0 && (
                                        <button
                                            type="button"
                                            onClick={handleMarkAllRead}
                                            className="text-[11px] font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                        >
                                            Mark all read
                                        </button>
                                    )}
                                </div>

                                {/* Items Container */}
                                <div className="max-h-80 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800/60">
                                    {loadingNotifications ? (
                                        <div className="py-8 text-center text-xs text-slate-400">
                                            Loading alerts...
                                        </div>
                                    ) : recentNotifications.length === 0 ? (
                                        <div className="py-8 text-center text-slate-400">
                                            <CheckCheck className="h-6 w-6 mx-auto mb-1 text-slate-300 dark:text-slate-600" />
                                            <p className="text-xs">No notifications yet</p>
                                        </div>
                                    ) : (
                                        recentNotifications.slice(0, 6).map((item) => (
                                            <div
                                                key={item.id}
                                                onClick={() => {
                                                    if (!item.read) handleMarkAsRead(item.id);
                                                    if (item.url) {
                                                        setNotificationsOpen(false);
                                                        router.visit(item.url);
                                                    }
                                                }}
                                                className={`p-3 text-left transition cursor-pointer flex items-start gap-2.5 ${
                                                    !item.read
                                                        ? 'bg-blue-50/50 dark:bg-blue-950/30 hover:bg-blue-50 dark:hover:bg-blue-950/50'
                                                        : 'hover:bg-slate-50 dark:hover:bg-slate-800/40'
                                                }`}
                                            >
                                                <div className="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 shrink-0 mt-0.5">
                                                    {getIconForType(item.type)}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center justify-between gap-1 mb-0.5">
                                                        <span className="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                                            {item.title}
                                                        </span>
                                                        <span className="text-[10px] text-slate-400 shrink-0">
                                                            {item.time_ago}
                                                        </span>
                                                    </div>
                                                    <p className="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2">
                                                        {item.message}
                                                    </p>
                                                </div>
                                                {!item.read && (
                                                    <span className="h-2 w-2 rounded-full bg-blue-600 shrink-0 mt-1" />
                                                )}
                                            </div>
                                        ))
                                    )}
                                </div>

                                {/* Footer Link */}
                                <div className="px-3 pt-2 border-t border-slate-100 dark:border-slate-800">
                                    <Link
                                        href={route('notifications.index')}
                                        onClick={() => setNotificationsOpen(false)}
                                        className="block text-center py-1.5 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition"
                                    >
                                        View all notifications
                                    </Link>
                                </div>
                            </div>
                        )}
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
                                <Dropdown.Link href={route('notifications.index')}>
                                    <div className="flex items-center gap-2">
                                        <Bell className="h-4 w-4 text-blue-600" />
                                        <span>Notifications</span>
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
