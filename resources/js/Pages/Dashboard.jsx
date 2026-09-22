import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { 
    Database, 
    Zap, 
    Server, 
    Shield, 
    ExternalLink, 
    CheckCircle2, 
    Layers, 
    Code2,
    Sparkles,
    ArrowUpRight,
    Terminal
} from 'lucide-react';

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth.user;

    const foundationCards = [
        {
            title: 'Database Foundation',
            subtitle: 'MySQL 8.0+ / utf8mb4',
            status: 'Configured',
            statusColor: 'text-blue-700 bg-blue-50 dark:text-blue-300 dark:bg-blue-950/50 border-blue-200 dark:border-blue-900',
            description: 'Relational data store with Eloquent ORM, UUID/ID models, and migration scaffolding.',
            icon: Database,
            accent: 'border-l-4 border-l-blue-600',
            stats: 'MySQL & Migrations Ready',
        },
        {
            title: 'Cache & Queue Engine',
            subtitle: 'Redis with Predis Client',
            status: 'Ready',
            statusColor: 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-950/50 border-red-200 dark:border-red-900',
            description: 'High-throughput caching and async background job queues configured for high/default/low priorities.',
            icon: Zap,
            accent: 'border-l-4 border-l-red-600',
            stats: 'Redis & Queues Configured',
        },
        {
            title: 'API v1 Routing & DTOs',
            subtitle: 'Sanctum + Versioned Routes',
            status: 'Active',
            statusColor: 'text-blue-700 bg-blue-50 dark:text-blue-300 dark:bg-blue-950/50 border-blue-200 dark:border-blue-900',
            description: 'RESTful API v1 routing layer with standardized JSON envelopes, DTOs, and health probes.',
            icon: Server,
            accent: 'border-l-4 border-l-blue-600',
            stats: '6 API v1 Endpoints',
        },
        {
            title: 'Service & Enums Layer',
            subtitle: 'Clean Domain Architecture',
            status: 'Enforced',
            statusColor: 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-900',
            description: 'Strongly-typed Enums (UserRole, UserStatus, QueuePriority) and transactional BaseService classes.',
            icon: Shield,
            accent: 'border-l-4 border-l-emerald-600',
            stats: 'Services & Enums Ready',
        },
    ];

    return (
        <AuthenticatedLayout
            header="Foundation Dashboard"
        >
            <Head title="Dashboard - Bamcom AI CRM" />

            <div className="space-y-6">
                {/* Hero Welcome Banner */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-900 via-blue-800 to-slate-900 text-white p-6 sm:p-8 shadow-xl">
                    {/* Decorative Background Elements */}
                    <div className="absolute right-0 top-0 -mt-8 -mr-8 h-64 w-64 rounded-full bg-red-600/20 blur-3xl pointer-events-none" />
                    <div className="absolute right-32 bottom-0 -mb-8 h-48 w-48 rounded-full bg-blue-400/20 blur-2xl pointer-events-none" />
                    
                    <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <div className="space-y-2 max-w-2xl">
                            <div className="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-white/10 backdrop-blur border border-white/20 text-xs font-semibold tracking-wide text-blue-200">
                                <Sparkles className="h-3.5 w-3.5 text-red-400" />
                                <span>Phase 1 Architecture Complete</span>
                            </div>
                            <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                                Welcome to Bamcom AI CRM
                            </h1>
                            <p className="text-sm sm:text-base text-blue-100/90 leading-relaxed">
                                The architectural foundation is initialized with Laravel 13, PHP 8.3+, React, Inertia, Redis, and Tailwind CSS.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href={route('api.v1.health')}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-sm font-semibold shadow-lg shadow-red-600/30 transition transform hover:-translate-y-0.5"
                            >
                                <Terminal className="h-4 w-4" />
                                <span>Run Health Probe</span>
                                <ArrowUpRight className="h-3.5 w-3.5 opacity-80" />
                            </a>
                            <Link
                                href={route('profile.edit')}
                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-sm font-semibold backdrop-blur border border-white/20 transition"
                            >
                                <span>Manage Profile</span>
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Architecture Metrics Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                    {foundationCards.map((card) => {
                        const Icon = card.icon;
                        return (
                            <div
                                key={card.title}
                                className={`rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 shadow-sm transition-all hover:shadow-md ${card.accent}`}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-blue-700 dark:text-blue-400">
                                        <Icon className="h-5 w-5" />
                                    </div>
                                    <span className={`text-[11px] font-semibold px-2 py-0.5 rounded-full border ${card.statusColor}`}>
                                        {card.status}
                                    </span>
                                </div>
                                <div className="mt-4">
                                    <h3 className="text-base font-bold text-slate-900 dark:text-white">
                                        {card.title}
                                    </h3>
                                    <p className="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5">
                                        {card.subtitle}
                                    </p>
                                    <p className="text-xs text-slate-600 dark:text-slate-300 mt-2.5 line-clamp-2">
                                        {card.description}
                                    </p>
                                </div>
                                <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                                    <span className="font-semibold text-slate-700 dark:text-slate-200">
                                        {card.stats}
                                    </span>
                                    <CheckCircle2 className="h-4 w-4 text-emerald-500" />
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Foundation Architecture Details */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Architecture Specifications */}
                    <div className="lg:col-span-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                            <div>
                                <h2 className="text-base font-bold text-slate-900 dark:text-white">
                                    System Foundation Specifications
                                </h2>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    Core runtime configurations and architectural patterns
                                </p>
                            </div>
                            <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-900">
                                Phase 1 Ready
                            </span>
                        </div>

                        <div className="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Backend Framework
                                </span>
                                <div className="mt-1 flex items-center justify-between">
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">Laravel 13</span>
                                    <span className="text-xs text-blue-600 dark:text-blue-400 font-medium">PHP 8.3+</span>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Service architecture with base transactional executor.
                                </p>
                            </div>

                            <div className="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Queue & Redis Client
                                </span>
                                <div className="mt-1 flex items-center justify-between">
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">Predis Client</span>
                                    <span className="text-xs text-red-600 dark:text-red-400 font-medium">Port 6379</span>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Configured for Redis sessions, queues, and fast caching.
                                </p>
                            </div>

                            <div className="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Frontend Stack
                                </span>
                                <div className="mt-1 flex items-center justify-between">
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">React 18 + Inertia</span>
                                    <span className="text-xs text-blue-600 dark:text-blue-400 font-medium">Tailwind CSS</span>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Single-page app experience with server-driven routing.
                                </p>
                            </div>

                            <div className="p-4 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700/60">
                                <span className="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                    Design System Theme
                                </span>
                                <div className="mt-1 flex items-center justify-between">
                                    <span className="text-sm font-bold text-slate-900 dark:text-white">Blue, Red & White</span>
                                    <span className="text-xs text-slate-600 dark:text-slate-300 font-medium">Dark / Light</span>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                    Persisted theme mode with instant FOUC prevention.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Right: Active Session Profile */}
                    <div className="rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <h2 className="text-base font-bold text-slate-900 dark:text-white pb-3 border-b border-slate-200 dark:border-slate-800">
                                Active Session
                            </h2>
                            <div className="mt-4 flex items-center gap-4">
                                <div className="h-12 w-12 rounded-xl bg-gradient-to-tr from-blue-700 to-red-600 flex items-center justify-center text-white font-extrabold text-lg shadow-md">
                                    {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                                </div>
                                <div className="min-w-0">
                                    <h3 className="text-sm font-bold text-slate-900 dark:text-white truncate">
                                        {user?.name || 'Administrator'}
                                    </h3>
                                    <p className="text-xs text-slate-500 dark:text-slate-400 truncate">
                                        {user?.email || 'admin@bamcom.ai'}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-5 space-y-3">
                                <div className="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 dark:border-slate-800">
                                    <span className="text-slate-500 dark:text-slate-400">Assigned Role</span>
                                    <span className="px-2 py-0.5 rounded-full font-semibold bg-red-50 text-red-700 dark:bg-red-950/60 dark:text-red-300 border border-red-200 dark:border-red-900 capitalize">
                                        {user?.role || 'Administrator'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 dark:border-slate-800">
                                    <span className="text-slate-500 dark:text-slate-400">Account Status</span>
                                    <span className="px-2 py-0.5 rounded-full font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900 capitalize">
                                        {user?.status || 'Active'}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between text-xs py-1.5 border-b border-slate-100 dark:border-slate-800">
                                    <span className="text-slate-500 dark:text-slate-400">Auth Guard</span>
                                    <span className="font-medium text-slate-700 dark:text-slate-300">
                                        Web Session + Sanctum
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-800">
                            <Link
                                href={route('profile.edit')}
                                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition"
                            >
                                <span>Edit Account Settings</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
