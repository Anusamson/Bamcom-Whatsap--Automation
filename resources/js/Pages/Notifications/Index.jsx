import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Bell,
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
    ExternalLink,
    Filter,
    ShieldAlert
} from 'lucide-react';

export default function Index({ notifications, filter = 'all', unreadCount = 0 }) {
    const [markingAll, setMarkingAll] = useState(false);
    const [currentFilter, setCurrentFilter] = useState(filter);

    const handleFilterChange = (newFilter) => {
        setCurrentFilter(newFilter);
        router.get(route('notifications.index'), { filter: newFilter }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleMarkAsRead = (id) => {
        router.patch(route('notifications.read', id), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const handleMarkAllRead = () => {
        setMarkingAll(true);
        router.post(route('notifications.mark-all-read'), {}, {
            preserveScroll: true,
            onFinish: () => setMarkingAll(false),
        });
    };

    const getNotificationMeta = (type) => {
        switch (type) {
            case 'hot_lead':
                return {
                    icon: Flame,
                    color: 'text-amber-600 dark:text-amber-400',
                    bg: 'bg-amber-100 dark:bg-amber-950/50',
                    border: 'border-amber-200 dark:border-amber-800',
                    label: 'Hot Lead Alert',
                };
            case 'new_assigned_lead':
                return {
                    icon: UserPlus,
                    color: 'text-indigo-600 dark:text-indigo-400',
                    bg: 'bg-indigo-100 dark:bg-indigo-950/50',
                    border: 'border-indigo-200 dark:border-indigo-800',
                    label: 'Lead Assignment',
                };
            case 'human_handover':
                return {
                    icon: AlertCircle,
                    color: 'text-rose-600 dark:text-rose-400',
                    bg: 'bg-rose-100 dark:bg-rose-950/50',
                    border: 'border-rose-200 dark:border-rose-800',
                    label: 'Human Handover',
                };
            case 'inspection_request':
                return {
                    icon: Calendar,
                    color: 'text-cyan-600 dark:text-cyan-400',
                    bg: 'bg-cyan-100 dark:bg-cyan-950/50',
                    border: 'border-cyan-200 dark:border-cyan-800',
                    label: 'Inspection Scheduled',
                };
            case 'inspection_reminder':
                return {
                    icon: Clock,
                    color: 'text-emerald-600 dark:text-emerald-400',
                    bg: 'bg-emerald-100 dark:bg-emerald-950/50',
                    border: 'border-emerald-200 dark:border-emerald-800',
                    label: 'Inspection Reminder',
                };
            case 'overdue_task':
                return {
                    icon: AlertTriangle,
                    color: 'text-red-600 dark:text-red-400',
                    bg: 'bg-red-100 dark:bg-red-950/50',
                    border: 'border-red-200 dark:border-red-800',
                    label: 'Overdue Task',
                };
            case 'new_customer_reply':
                return {
                    icon: MessageSquare,
                    color: 'text-blue-600 dark:text-blue-400',
                    bg: 'bg-blue-100 dark:bg-blue-950/50',
                    border: 'border-blue-200 dark:border-blue-800',
                    label: 'Customer Reply',
                };
            case 'deal_activity':
                return {
                    icon: TrendingUp,
                    color: 'text-purple-600 dark:text-purple-400',
                    bg: 'bg-purple-100 dark:bg-purple-950/50',
                    border: 'border-purple-200 dark:border-purple-800',
                    label: 'Deal Activity',
                };
            default:
                return {
                    icon: Bell,
                    color: 'text-slate-600 dark:text-slate-400',
                    bg: 'bg-slate-100 dark:bg-slate-800',
                    border: 'border-slate-200 dark:border-slate-700',
                    label: 'Notification',
                };
        }
    };

    return (
        <AuthenticatedLayout header="Notifications & Alerts">
            <Head title="Notifications - Bamcom CRM" />

            <div className="py-6 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header & Controls */}
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div className="flex items-center gap-3">
                        <div className="h-12 w-12 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <Bell className="h-6 w-6" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-slate-900 dark:text-white">
                                Staff Notification Center
                            </h1>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Realtime alerts for leads, inspections, customer replies, tasks, and deal activities.
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 self-end sm:self-center">
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                onClick={handleMarkAllRead}
                                disabled={markingAll}
                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition"
                            >
                                <CheckCheck className="h-4 w-4 text-emerald-600" />
                                <span>{markingAll ? 'Marking...' : 'Mark All Read'}</span>
                            </button>
                        )}

                        <div className="flex items-center rounded-lg border border-slate-200 dark:border-slate-800 p-1 bg-slate-50 dark:bg-slate-950">
                            <button
                                type="button"
                                onClick={() => handleFilterChange('all')}
                                className={`px-3 py-1 text-xs font-semibold rounded-md transition ${
                                    currentFilter === 'all'
                                        ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'
                                }`}
                            >
                                All
                            </button>
                            <button
                                type="button"
                                onClick={() => handleFilterChange('unread')}
                                className={`px-3 py-1 text-xs font-semibold rounded-md transition flex items-center gap-1.5 ${
                                    currentFilter === 'unread'
                                        ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-xs'
                                        : 'text-slate-500 hover:text-slate-900 dark:hover:text-white'
                                }`}
                            >
                                <span>Unread</span>
                                {unreadCount > 0 && (
                                    <span className="h-4 min-w-[16px] px-1 rounded-full bg-red-600 text-white text-[10px] font-bold flex items-center justify-center">
                                        {unreadCount}
                                    </span>
                                )}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Notifications List */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                    {notifications.data.length === 0 ? (
                        <div className="text-center py-16 px-4">
                            <div className="h-16 w-16 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-3">
                                <CheckCheck className="h-8 w-8 text-emerald-500" />
                            </div>
                            <h3 className="text-sm font-semibold text-slate-900 dark:text-white">All caught up!</h3>
                            <p className="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                You have no {currentFilter === 'unread' ? 'unread ' : ''}notifications at this moment. You will be alerted when new events occur.
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100 dark:divide-slate-800/80">
                            {notifications.data.map((item) => {
                                const meta = getNotificationMeta(item.type);
                                const Icon = meta.icon;

                                return (
                                    <div
                                        key={item.id}
                                        className={`p-4 sm:p-5 transition flex items-start gap-4 ${
                                            !item.read
                                                ? 'bg-blue-50/40 dark:bg-blue-950/20'
                                                : 'hover:bg-slate-50 dark:hover:bg-slate-850'
                                        }`}
                                    >
                                        {/* Type Icon */}
                                        <div className={`h-10 w-10 rounded-xl shrink-0 flex items-center justify-center ${meta.bg} ${meta.color} border ${meta.border}`}>
                                            <Icon className="h-5 w-5" />
                                        </div>

                                        {/* Main Content */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap mb-1">
                                                <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ${meta.bg} ${meta.color} border ${meta.border}`}>
                                                    {meta.label}
                                                </span>
                                                {!item.read && (
                                                    <span className="h-2 w-2 rounded-full bg-blue-600 shrink-0" title="Unread" />
                                                )}
                                                <span className="text-[11px] text-slate-400 dark:text-slate-500 ml-auto">
                                                    {item.time_ago}
                                                </span>
                                            </div>

                                            <h4 className="text-sm font-semibold text-slate-900 dark:text-white leading-snug">
                                                {item.title}
                                            </h4>

                                            <p className="text-xs text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">
                                                {item.message}
                                            </p>

                                            {/* Action Links */}
                                            <div className="flex items-center gap-3 mt-3">
                                                {item.url && (
                                                    <Link
                                                        href={item.url}
                                                        onClick={() => {
                                                            if (!item.read) handleMarkAsRead(item.id);
                                                        }}
                                                        className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline"
                                                    >
                                                        <span>View Details</span>
                                                        <ExternalLink className="h-3 w-3" />
                                                    </Link>
                                                )}

                                                {!item.read && (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleMarkAsRead(item.id)}
                                                        className="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                                                    >
                                                        <Check className="h-3 w-3 text-emerald-500" />
                                                        <span>Mark as read</span>
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    {/* Pagination */}
                    {notifications.links && notifications.links.length > 3 && (
                        <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                            <span className="text-xs text-slate-500">
                                Showing {notifications.from || 0} to {notifications.to || 0} of {notifications.total} notifications
                            </span>
                            <div className="flex items-center gap-1">
                                {notifications.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-2.5 py-1 text-xs rounded-md transition ${
                                            link.active
                                                ? 'bg-blue-600 text-white font-semibold'
                                                : link.url
                                                ? 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
                                                : 'text-slate-300 dark:text-slate-600 pointer-events-none'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
