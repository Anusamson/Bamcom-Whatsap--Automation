import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Megaphone,
    Plus,
    Search,
    Play,
    Pause,
    XCircle,
    Clock,
    Users,
    ChevronRight,
    Eye,
    Send,
    CheckCircle2,
    BookOpen,
    ShieldAlert,
    AlertTriangle,
    Layers
} from 'lucide-react';
import { useState } from 'react';

export default function Index({
    campaigns = { data: [], links: [] },
    metrics = {},
    filters = {},
    statuses = []
}) {
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(route('campaigns.index'), { search, status }, { preserveState: true });
    };

    const handleStatusFilter = (newStatus) => {
        setStatus(newStatus);
        router.get(route('campaigns.index'), { search, status: newStatus }, { preserveState: true });
    };

    const handleLaunch = (id) => {
        if (confirm('Are you sure you want to launch this campaign now? Batched sends will begin.')) {
            router.post(route('campaigns.launch', id));
        }
    };

    const handlePause = (id) => {
        router.post(route('campaigns.pause', id));
    };

    const handleResume = (id) => {
        router.post(route('campaigns.resume', id));
    };

    const handleCancel = (id) => {
        const reason = prompt('Please enter a cancellation reason:', 'Cancelled by administrator');
        if (reason) {
            router.post(route('campaigns.cancel', id), { reason });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="WhatsApp Campaigns" />

            <div className="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="flex items-center space-x-3">
                            <div className="p-2 bg-emerald-100 dark:bg-emerald-950/60 rounded-xl text-emerald-600 dark:text-emerald-400">
                                <Megaphone className="w-6 h-6" />
                            </div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                                WhatsApp Campaigns
                            </h1>
                        </div>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Broadcast targeted marketing and property nurture campaigns with rate-limited batch delivery
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('audiences.index')}
                            className="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-xl shadow-sm transition"
                        >
                            <Users className="w-4 h-4 text-indigo-500" />
                            <span>Audiences & Segments</span>
                        </Link>

                        <Link
                            href={route('campaigns.create')}
                            className="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl shadow-sm transition"
                        >
                            <Plus className="w-4 h-4" />
                            <span>New Campaign</span>
                        </Link>
                    </div>
                </div>

                {/* Metrics Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Total Campaigns
                            </span>
                            <Megaphone className="w-4 h-4 text-emerald-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">
                            {metrics.total_campaigns ?? 0}
                        </p>
                        <span className="text-xs text-emerald-600 font-medium">
                            {metrics.running_campaigns ?? 0} running now
                        </span>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Total Messages Sent
                            </span>
                            <Send className="w-4 h-4 text-blue-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {metrics.total_sent ?? 0}
                        </p>
                        <span className="text-xs text-gray-500">
                            {metrics.total_delivered ?? 0} delivered
                        </span>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Read Receipts
                            </span>
                            <CheckCircle2 className="w-4 h-4 text-purple-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-purple-600 dark:text-purple-400">
                            {metrics.total_read ?? 0}
                        </p>
                        <span className="text-xs text-gray-500">
                            {metrics.total_delivered > 0
                                ? Math.round((metrics.total_read / metrics.total_delivered) * 100) + '% read rate'
                                : '0% read rate'}
                        </span>
                    </div>

                    <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Opted Out / Excluded
                            </span>
                            <ShieldAlert className="w-4 h-4 text-rose-500" />
                        </div>
                        <p className="mt-2 text-2xl font-bold text-rose-600 dark:text-rose-400">
                            {metrics.total_opted_out ?? 0}
                        </p>
                        <span className="text-xs text-gray-500">Protected by guardrail</span>
                    </div>
                </div>

                {/* Filter and Search Bar */}
                <div className="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 flex flex-col md:flex-row gap-4 items-center justify-between">
                    <form onSubmit={handleSearch} className="flex-1 w-full flex items-center gap-2">
                        <div className="relative flex-1">
                            <Search className="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search campaigns by name or description..."
                                className="w-full pl-9 pr-4 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"
                            />
                        </div>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-xl transition"
                        >
                            Filter
                        </button>
                    </form>

                    <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-2 md:pb-0">
                        <button
                            onClick={() => handleStatusFilter('all')}
                            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition ${
                                status === 'all'
                                    ? 'bg-emerald-600 text-white'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
                            }`}
                        >
                            All
                        </button>
                        {statuses.map((s) => (
                            <button
                                key={s.value}
                                onClick={() => handleStatusFilter(s.value)}
                                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition whitespace-nowrap ${
                                    status === s.value
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200'
                                }`}
                            >
                                {s.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Campaigns List */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {campaigns.data?.length > 0 ? (
                        campaigns.data.map((campaign) => {
                            const progressPercent = campaign.total_recipients > 0
                                ? Math.min(100, Math.round((campaign.processed_recipients / campaign.total_recipients) * 100))
                                : 0;

                            return (
                                <div
                                    key={campaign.id}
                                    className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700/60 p-5 flex flex-col justify-between hover:shadow-md transition"
                                >
                                    <div>
                                        <div className="flex items-start justify-between gap-2 mb-2">
                                            <span
                                                className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider ${
                                                    campaign.status === 'running'
                                                        ? 'bg-emerald-100 text-emerald-800 animate-pulse'
                                                        : campaign.status === 'completed'
                                                        ? 'bg-purple-100 text-purple-800'
                                                        : campaign.status === 'scheduled'
                                                        ? 'bg-blue-100 text-blue-800'
                                                        : campaign.status === 'paused'
                                                        ? 'bg-amber-100 text-amber-800'
                                                        : 'bg-gray-100 text-gray-700'
                                                }`}
                                            >
                                                {campaign.status}
                                            </span>

                                            <div className="flex items-center gap-1">
                                                {campaign.status === 'draft' && (
                                                    <button
                                                        onClick={() => handleLaunch(campaign.id)}
                                                        className="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-lg transition"
                                                        title="Launch Campaign Now"
                                                    >
                                                        <Play className="w-4 h-4" />
                                                    </button>
                                                )}
                                                {campaign.status === 'running' && (
                                                    <button
                                                        onClick={() => handlePause(campaign.id)}
                                                        className="p-1.5 text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded-lg transition"
                                                        title="Pause Campaign"
                                                    >
                                                        <Pause className="w-4 h-4" />
                                                    </button>
                                                )}
                                                {campaign.status === 'paused' && (
                                                    <button
                                                        onClick={() => handleResume(campaign.id)}
                                                        className="p-1.5 text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/40 rounded-lg transition"
                                                        title="Resume Campaign"
                                                    >
                                                        <Play className="w-4 h-4" />
                                                    </button>
                                                )}
                                                {['draft', 'scheduled', 'running', 'paused'].includes(campaign.status) && (
                                                    <button
                                                        onClick={() => handleCancel(campaign.id)}
                                                        className="p-1.5 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition"
                                                        title="Cancel Campaign"
                                                    >
                                                        <XCircle className="w-4 h-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        <h3 className="text-lg font-bold text-gray-900 dark:text-white line-clamp-1">
                                            {campaign.name}
                                        </h3>

                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2 min-h-[32px]">
                                            {campaign.description || 'No description provided.'}
                                        </p>

                                        {/* Audience & Template Info */}
                                        <div className="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/60 space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                                            <div className="flex items-center justify-between">
                                                <span className="text-gray-400">Audience:</span>
                                                <span className="font-semibold text-gray-900 dark:text-white">
                                                    {campaign.audience?.name || 'All Eligible'}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <span className="text-gray-400">Message Type:</span>
                                                <span className="capitalize font-medium">
                                                    {campaign.message_type === 'template' ? (
                                                        <span className="text-indigo-600 font-semibold">
                                                            Template: {campaign.template?.name || 'Approved Meta'}
                                                        </span>
                                                    ) : (
                                                        'Custom Text'
                                                    )}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Progress Bar */}
                                        <div className="mt-4 space-y-1">
                                            <div className="flex justify-between text-[11px] text-gray-500">
                                                <span>Progress ({progressPercent}%)</span>
                                                <span>
                                                    {campaign.processed_recipients} / {campaign.total_recipients}
                                                </span>
                                            </div>
                                            <div className="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                                <div
                                                    className="bg-emerald-500 h-2 rounded-full transition-all duration-300"
                                                    style={{ width: `${progressPercent}%` }}
                                                />
                                            </div>
                                        </div>

                                        {/* Delivery Stats Grid */}
                                        <div className="mt-4 grid grid-cols-3 gap-2 pt-3 border-t border-gray-100 dark:border-gray-700/60 text-center text-xs">
                                            <div className="bg-gray-50 dark:bg-gray-900/40 p-2 rounded-xl">
                                                <span className="block text-[10px] uppercase text-gray-400 font-semibold">Sent</span>
                                                <span className="font-bold text-gray-900 dark:text-white">{campaign.sent_count}</span>
                                            </div>
                                            <div className="bg-gray-50 dark:bg-gray-900/40 p-2 rounded-xl">
                                                <span className="block text-[10px] uppercase text-gray-400 font-semibold">Delivered</span>
                                                <span className="font-bold text-teal-600">{campaign.delivered_count}</span>
                                            </div>
                                            <div className="bg-gray-50 dark:bg-gray-900/40 p-2 rounded-xl">
                                                <span className="block text-[10px] uppercase text-gray-400 font-semibold">Read</span>
                                                <span className="font-bold text-purple-600">{campaign.read_count}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="mt-5 pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between">
                                        <span className="text-[11px] text-gray-400">
                                            Batch size: {campaign.batch_size}
                                        </span>

                                        <Link
                                            href={route('campaigns.show', campaign.id)}
                                            className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 hover:underline"
                                        >
                                            View Report
                                            <ChevronRight className="w-3.5 h-3.5" />
                                        </Link>
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="col-span-full py-12 text-center bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700">
                            <Megaphone className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                            <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                                No WhatsApp campaigns found
                            </h3>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Launch your first broadcast campaign to engage high-intent property buyers.
                            </p>
                            <Link
                                href={route('campaigns.create')}
                                className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl shadow-sm transition"
                            >
                                <Plus className="w-4 h-4" />
                                Create Campaign
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
